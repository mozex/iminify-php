<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Utils;
use Mozex\Iminify\Exceptions\IminifyException;
use Mozex\Iminify\Exceptions\TransportException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Responses\Download;
use Mozex\Iminify\Responses\RateLimit;
use Mozex\Iminify\Tests\Fakes\ShortWriteStream;

describe('a download', function () {
    it('reads what the answer says about the file', function () {
        $download = downloadOf('bytes', [
            'content-type' => 'image/webp',
            'content-length' => '5',
            'content-disposition' => 'inline; filename="team offsite.webp"',
            'x-ratelimit-remaining' => '12',
        ]);

        expect($download->name)->toBe('team offsite.webp')
            ->and($download->mimeType)->toBe('image/webp')
            ->and($download->size)->toBe(5)
            ->and($download->rateLimit->remaining)->toBe(12)
            ->and($download->contents())->toBe('bytes');
    });

    it('reads the name from each form of the header, the UTF-8 one first, and strips any folder', function (?string $header, ?string $name) {
        expect(downloadOf('x', $header === null ? [] : ['content-disposition' => $header])->name)->toBe($name);
    })->with([
        ['attachment; filename=plain.png', 'plain.png'],
        ['attachment; filename="quoted name.png"', 'quoted name.png'],
        ['attachment; filename="a\\"b.png"', 'a"b.png'],
        ["inline; filename=\"fallback.png\"; filename*=UTF-8''caf%C3%A9.png", 'café.png'],
        ['attachment; filename="../../etc/passwd"', 'passwd'],
        ['attachment; filename="..\\\\..\\\\evil.exe"', 'evil.exe'],
        ['attachment; filename=..\\..\\evil.exe', 'evil.exe'],
        ["attachment; filename*=UTF-8''..%2F..%2Fevil.php", 'evil.php'],
        ['attachment; filename=".."', null],
        ['inline', null],
        [null, null],
    ]);

    it('has no size to hold a file to when the body is encoded', function () {
        expect(downloadOf('x', ['content-length' => '1', 'content-encoding' => 'gzip'])->size)->toBeNull()
            ->and(downloadOf('x', ['content-length' => 'lots'])->size)->toBeNull();
    });
});

describe('saving a download', function () {
    beforeEach(function () {
        $this->folder = downloadFolder();
    });

    afterEach(function () {
        removeFolder($this->folder);
    });

    it('writes the file, returns its size and leaves nothing else behind', function () {
        $path = $this->folder.'/photo.webp';

        expect(downloadOf('the-bytes', ['content-length' => '9'])->saveTo($path))->toBe(9)
            ->and(file_get_contents($path))->toBe('the-bytes')
            ->and(glob($this->folder.'/{,.}*.tmp', GLOB_BRACE))->toBe([]);
    });

    it('replaces a file already there', function () {
        $path = $this->folder.'/photo.webp';
        file_put_contents($path, 'old');

        downloadOf('new')->saveTo($path);

        expect(file_get_contents($path))->toBe('new');
    });

    it('keeps the permissions of the file it replaces', function () {
        $path = $this->folder.'/photo.webp';
        file_put_contents($path, 'old');
        chmod($path, 0600);

        downloadOf('new')->saveTo($path);

        expect(fileperms($path) & 0777)->toBe(0600);
    })->skipOnWindows();

    it('holds the temporary file to the permissions of the one it replaces before the first byte', function () {
        $path = $this->folder.'/photo.webp';
        file_put_contents($path, 'old');
        chmod($path, 0600);
        $seen = null;
        $folder = $this->folder;
        $stream = FnStream::decorate(Utils::streamFor('new'), [
            'read' => function (int $length) use (&$seen, $folder): string {
                $seen = fileperms((string) glob($folder.'/.iminify-*.tmp')[0]) & 0777;

                return 'new';
            },
            'eof' => function () use (&$seen): bool {
                return $seen !== null;
            },
            'isSeekable' => fn (): bool => false,
        ]);

        (new Download($stream, null, null, null, new RateLimit))->saveTo($path);

        expect($seen)->toBe(0600);
    })->skipOnWindows();

    it('refuses a target it could not replace in place, leaving it and nothing else behind', function () {
        $path = $this->folder.'/photo.webp';
        file_put_contents($path, 'old');
        chmod($path, 0444);

        try {
            expect(fn () => downloadOf('new')->saveTo($path))->toThrow(IminifyException::class, "isn't writable")
                ->and(file_get_contents($path))->toBe('old')
                ->and(glob($this->folder.'/{,.}*.tmp', GLOB_BRACE))->toBe([]);
        } finally {
            chmod($path, 0644);
        }
    });

    it('refuses a folder it cannot write to', function () {
        $folder = $this->folder.'/locked';
        mkdir($folder, 0555);

        try {
            expect(fn () => downloadOf('new')->saveTo($folder.'/photo.webp'))->toThrow(IminifyException::class, "isn't writable");
        } finally {
            chmod($folder, 0755);
            rmdir($folder);
        }
    })->skipOnWindows();

    it('writes through a link, and keeps the link', function () {
        $target = $this->folder.'/target.webp';
        $link = $this->folder.'/link.webp';
        file_put_contents($target, 'old');
        symlink($target, $link);

        try {
            downloadOf('new')->saveTo($link);

            expect(is_link($link))->toBeTrue()
                ->and(file_get_contents($target))->toBe('new');
        } finally {
            unlink($link);
        }
    })->skipOnWindows();

    it('hands the finished file to a check before anything is replaced', function () {
        $path = $this->folder.'/photo.webp';
        file_put_contents($path, 'old');
        $seen = null;

        downloadOf('new')->saveTo($path, null, function (string $temporary) use (&$seen, $path): void {
            $seen = [file_get_contents($temporary), file_get_contents($path)];
        });

        expect($seen)->toBe(['new', 'old'])
            ->and(file_get_contents($path))->toBe('new');
    });

    it('keeps what is at the path when the check refuses, and leaves nothing behind', function () {
        $path = $this->folder.'/photo.webp';
        file_put_contents($path, 'old');

        expect(fn () => downloadOf('new')->saveTo($path, null, function (): void {
            throw new IminifyException('Not this one.');
        }))->toThrow(IminifyException::class, 'Not this one.')
            ->and(file_get_contents($path))->toBe('old')
            ->and(glob($this->folder.'/{,.}*.tmp', GLOB_BRACE))->toBe([]);
    });

    it('holds an answer that states no length to the size it is given', function (string $bytes) {
        $path = $this->folder.'/photo.webp';
        file_put_contents($path, 'old');

        expect(fn () => downloadOf($bytes)->saveTo($path, 5))->toThrow(UnexpectedResponseException::class, 'of 5')
            ->and(file_get_contents($path))->toBe('old');
    })->with(['shorter' => ['abc'], 'longer' => ['abcdefgh']]);

    it('reads a body that cannot be rewound', function () {
        $body = fn (): NoSeekStream => new NoSeekStream(Utils::streamFor('bytes'));
        $path = $this->folder.'/photo.webp';

        expect((new Download($body(), null, null, null, new RateLimit))->contents())->toBe('bytes')
            ->and((new Download($body(), null, null, null, new RateLimit))->saveTo($path))->toBe(5)
            ->and(file_get_contents($path))->toBe('bytes');
    });

    it('stops at a write that comes up short, as on a full disk', function () {
        expect(fn () => downloadOf('bytes')->writeTo(ShortWriteStream::open()))->toThrow(IminifyException::class, 'could not be written');
    });

    it('says so when the file cannot be replaced, and leaves nothing behind', function () {
        $path = $this->folder.'/photo.webp';
        mkdir($path);

        try {
            expect(fn () => downloadOf('new')->saveTo($path))->toThrow(IminifyException::class, "can't be saved")
                ->and(glob($this->folder.'/{,.}*.tmp', GLOB_BRACE))->toBe([]);
        } finally {
            rmdir($path);
        }
    });

    it('fails in its own exception when a framework turns warnings into exceptions', function () {
        $readOnly = fopen('php://memory', 'rb');
        set_error_handler(function (int $level, string $message): bool {
            if ((error_reporting() & $level) !== 0) {
                throw new ErrorException($message);
            }

            return true;
        });

        try {
            expect(fn () => downloadOf('bytes')->writeTo($readOnly))->toThrow(IminifyException::class, 'could not be written');
        } finally {
            restore_error_handler();
        }
    });

    it('can be saved after its contents were read', function () {
        $path = $this->folder.'/photo.webp';
        $download = downloadOf('bytes');

        expect($download->contents())->toBe('bytes')
            ->and($download->saveTo($path))->toBe(5);
    });

    it('leaves the file there untouched when the transfer came up short', function (string $bytes, array $headers) {
        $path = $this->folder.'/photo.webp';
        file_put_contents($path, 'good');

        expect(fn () => downloadOf($bytes, $headers)->saveTo($path))->toThrow(UnexpectedResponseException::class, 'The download ended after')
            ->and(file_get_contents($path))->toBe('good')
            ->and(glob($this->folder.'/{,.}*.tmp', GLOB_BRACE))->toBe([]);
    })->with([
        'empty' => ['', []],
        'shorter than it said' => ['abc', ['content-length' => '10']],
    ]);

    it('ends a lost connection in its own exception when a framework has turned its warning into one', function () {
        $body = FnStream::decorate(Utils::streamFor('bytes'), ['read' => fn (int $length): string => throw new ErrorException('Connection lost')]);

        expect(fn () => (new Download($body, null, null, null, new RateLimit))->contents())->toThrow(TransportException::class, 'Connection lost');
    });

    it('ends a connection lost mid-file in its own exception, leaving the file there untouched', function () {
        $path = $this->folder.'/photo.webp';
        file_put_contents($path, 'good');
        $lost = fn (): Download => new Download(
            FnStream::decorate(Utils::streamFor('bytes'), ['read' => fn (int $length): string => throw new RuntimeException('Connection lost')]),
            null,
            null,
            null,
            new RateLimit,
        );

        expect(fn () => $lost()->saveTo($path))->toThrow(TransportException::class, 'Connection lost')
            ->and(fn () => $lost()->contents())->toThrow(TransportException::class, 'Connection lost')
            ->and(file_get_contents($path))->toBe('good')
            ->and(glob($this->folder.'/{,.}*.tmp', GLOB_BRACE))->toBe([]);
    });

    it('says so when the folder is missing', function () {
        expect(fn () => downloadOf('x')->saveTo($this->folder.'/missing/photo.webp'))
            ->toThrow(IminifyException::class, "its folder doesn't exist");
    });

    it('writes to an open stream', function () {
        $stream = fopen('php://memory', 'r+b');

        expect(downloadOf('bytes')->writeTo($stream))->toBe(5);

        rewind($stream);

        expect(stream_get_contents($stream))->toBe('bytes')
            ->and(fn () => downloadOf('x')->writeTo('not-a-stream'))->toThrow(InvalidArgumentException::class, 'open stream');
    });
});
