<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\Utils;
use Mozex\Iminify\Exceptions\TransportException;
use Mozex\Iminify\Http\Upload;
use Mozex\Iminify\Tests\Fakes\ShortWriteStream;

describe('copying an upload', function () {
    it('stops rather than send a file that came to less than it measured', function () {
        $stream = FnStream::decorate(Utils::streamFor('bytes'), ['getSize' => fn (): int => 100]);
        $target = fopen('php://memory', 'r+b');

        expect(fn () => Upload::fromStream($stream, 'a.jpg')->copyTo($target))->toThrow(TransportException::class, '5 bytes of the 100');
    });

    it('ends a stream that fails mid-read in its own exception', function () {
        $stream = FnStream::decorate(Utils::streamFor('bytes'), ['read' => fn (int $length): string => throw new RuntimeException('Disk gone')]);
        $target = fopen('php://memory', 'r+b');

        expect(fn () => Upload::fromStream($stream, 'a.jpg')->copyTo($target))->toThrow(TransportException::class, 'Disk gone');
    });

    it('stops at a write that comes up short, as on a full disk', function () {
        expect(fn () => Upload::fromContents('bytes', 'a.jpg')->copyTo(ShortWriteStream::open()))->toThrow(TransportException::class, 'temporary stream');
    });

    it('copies a stream that says nothing of its size, having nothing to hold it to', function () {
        $stream = FnStream::decorate(Utils::streamFor('bytes'), ['getSize' => fn (): ?int => null]);
        $target = fopen('php://memory', 'r+b');

        Upload::fromStream($stream, 'a.jpg')->copyTo($target);

        rewind($target);

        expect(stream_get_contents($target))->toBe('bytes');
    });
});

describe('the size of an upload', function () {
    it('reads the file at a path', function () {
        $path = (string) tempnam(sys_get_temp_dir(), 'iminify');
        file_put_contents($path, 'twelve bytes');

        try {
            expect(Upload::fromPath($path, 'a.jpg')->size())->toBe(12);
        } finally {
            unlink($path);
        }
    });

    it('counts bytes in memory', function () {
        expect(Upload::fromContents('five!', 'a.jpg')->size())->toBe(5);
    });

    it('counts what is left of an open stream, read from where it stands', function () {
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, 'twelve bytes');
        fseek($stream, 7);

        $upload = Upload::fromStream($stream, 'a.jpg');
        $target = fopen('php://memory', 'r+b');

        expect($upload->size())->toBe(5)
            ->and(ftell($stream))->toBe(7);

        $upload->copyTo($target);
        rewind($target);

        expect(stream_get_contents($target))->toBe('bytes');

        fclose($stream);
        fclose($target);
    });

    it('counts what is left of a PSR-7 stream', function () {
        $stream = Utils::streamFor('twelve bytes');
        $stream->seek(10);

        expect(Upload::fromStream($stream, 'a.jpg')->size())->toBe(2);
    });

    it('answers null for a stream closed since, or one that cannot seek', function () {
        $closed = fopen('php://temp', 'r+b');
        $upload = Upload::fromStream($closed, 'a.jpg');
        fclose($closed);

        $pipes = [];
        $process = proc_open([PHP_BINARY, '-r', 'echo 1;'], [1 => ['pipe', 'w']], $pipes);

        expect($upload->size())->toBeNull()
            ->and(Upload::fromStream(new NoSeekStream(Utils::streamFor('bytes')), 'a.jpg')->size())->toBeNull()
            ->and(Upload::fromStream($pipes[1], 'a.jpg')->size())->toBeNull();

        fclose($pipes[1]);
        proc_close($process);
    });
});
