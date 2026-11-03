<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;
use Mozex\Iminify\Exceptions\IminifyException;
use Mozex\Iminify\Exceptions\TransportException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Responses\Download;
use Mozex\Iminify\Responses\RateLimit;

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
