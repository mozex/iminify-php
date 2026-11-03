<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Mozex\Iminify\Client;
use Mozex\Iminify\Exceptions\DailyLimitReachedException;
use Mozex\Iminify\Exceptions\IminifyException;
use Mozex\Iminify\Exceptions\RateLimitedException;
use Mozex\Iminify\Exceptions\ServerException;
use Mozex\Iminify\Exceptions\TransportException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Exceptions\WaitTimeoutException;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Http\ApiResponse;
use Mozex\Iminify\Spatie\IminifyOptimizer;
use Mozex\Iminify\Testing\FakeClient;
use Mozex\Iminify\Testing\Fixtures;
use Mozex\Iminify\Tests\Fakes\FakeOptimizer;
use Mozex\Iminify\Tests\Fakes\RecordingHttpClient;
use Mozex\Iminify\Tests\Fakes\RecordingLogger;
use Psr\Log\NullLogger;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image as SpatieImage;
use Spatie\ImageOptimizer\Image;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Spatie\ImageOptimizer\Optimizers\Svgo;

const JPEG_BYTES = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00 the original file, a good deal longer than its optimized copy";
const JPEG_SMALLER = "\xFF\xD8\xFF\xE0\x00\x10JFIF smaller";

beforeEach(function () {
    $this->folder = downloadFolder();
    $this->path = $this->folder.'/photo.jpg';
    file_put_contents($this->path, JPEG_BYTES);
    $this->logger = new RecordingLogger;
});

afterEach(function () {
    removeFolder($this->folder);
});

/**
 * An image's answer once it has finished, sized as the test's file is.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function optimizedImage(array $overrides = [], string $format = 'jpg', int $size = 0): array
{
    $file = ['name' => "image.{$format}", 'format' => $format, 'width' => 1, 'height' => 1, 'download_url' => 'https://www.iminify.com/api/v1/images/a/download'];

    return Fixtures::image($overrides + [
        'status' => 'completed',
        'original' => ['size' => strlen(JPEG_BYTES)] + $file,
        'optimized' => ['size' => $size ?: strlen(JPEG_SMALLER)] + $file,
    ]);
}

function optimizing(FakeClient $client, string $path, RecordingLogger $logger, array $options = []): FakeOptimizer
{
    $optimizer = new FakeOptimizer($client, $options);
    $optimizer->handle(new Image($path), $logger);

    return $optimizer;
}

describe('which files it takes', function () {
    it('takes what comes back in its own format, by what the file is rather than its name', function (string $bytes, bool $expected) {
        file_put_contents($this->folder.'/any.tmp', $bytes);

        expect((new IminifyOptimizer)->canHandle(new Image($this->folder.'/any.tmp')))->toBe($expected);
    })->with([
        'a JPEG' => [JPEG_BYTES, true],
        'a PNG' => ["\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00", true],
        'a GIF' => ["GIF89a\x01\x00\x01\x00\x00\x00\x00;", true],
        'a WebP' => ["RIFF\x1a\x00\x00\x00WEBPVP8 \x0e\x00\x00\x00", true],
        'an AVIF, which Iminify does not read' => ["\x00\x00\x00\x1cftypavif\x00\x00\x00\x00avifmif1", false],
        'a HEIC, which comes back in another format' => ["\x00\x00\x00\x18ftypheic\x00\x00\x00\x00mif1heic", false],
        'a TIFF, which comes back in another format' => ["II*\x00\x08\x00\x00\x00", false],
        'an SVG' => ['<svg xmlns="http://www.w3.org/2000/svg"></svg>', false],
        'text' => ['not an image at all', false],
        'an empty file' => ['', false],
    ]);
});

describe('what it sends', function () {
    it('names the file by its type, whatever its path says, and keeps the format and alt text out of it', function () {
        $path = $this->folder.'/phpA1B2.tmp';
        file_put_contents($path, JPEG_BYTES);
        $http = new RecordingHttpClient([
            jsonResponse(['data' => Fixtures::queuedImage()]),
            jsonResponse(['data' => optimizedImage()]),
            new Response(200, ['Content-Type' => 'image/jpeg'], JPEG_SMALLER),
            new Response(204),
        ]);

        (new FakeOptimizer(clientAnswering($http), ['level' => 'ultra', 'keep_metadata' => true]))->handle(new Image($path), $this->logger);

        $body = (string) $http->requests[0]->getBody();
        $fields = formFields($body);

        expect($body)->toContain('filename="image.jpg"')
            ->toContain(JPEG_BYTES)
            ->and($fields['alt_text'])->toBe(['0'])
            ->and($fields['ephemeral'])->toBe(['1'])
            ->and($fields['level'])->toBe(['ultra'])
            ->and($fields['keep_metadata'])->toBe(['1'])
            ->and(array_key_exists('format', $fields))->toBeFalse()
            ->and(array_key_exists('size_limit', $fields))->toBeFalse()
            ->and(file_get_contents($path))->toBe(JPEG_SMALLER);
    });

    it('sends neither a level nor a metadata choice unless told', function () {
        $http = new RecordingHttpClient([
            jsonResponse(['data' => optimizedImage(['status' => 'already-optimized', 'id' => 'a'])]),
            jsonResponse(['data' => optimizedImage(['status' => 'already-optimized', 'id' => 'a'])]),
            new Response(204),
        ]);

        (new FakeOptimizer(clientAnswering($http)))->handle(new Image($this->path), $this->logger);

        $fields = formFields((string) $http->requests[0]->getBody());

        expect(array_key_exists('level', $fields))->toBeFalse()
            ->and(array_key_exists('keep_metadata', $fields))->toBeFalse();
    });
});

describe('writing back', function () {
    it('replaces the file with the smaller copy, then deletes the image', function () {
        $client = new FakeClient([Fixtures::queuedImage(['id' => 'abc']), optimizedImage(['id' => 'abc']), Fixtures::file(JPEG_SMALLER), Fixtures::noContent()]);

        optimizing($client, $this->path, $this->logger);

        expect(file_get_contents($this->path))->toBe(JPEG_SMALLER)
            ->and(array_map(fn (ApiRequest $request): string => "{$request->method} {$request->path}", $client->requests()))->toBe([
                'POST images',
                'GET images/abc',
                'GET images/abc/download',
                'DELETE images/abc',
            ]);
    });

    it('takes jpeg for jpg, the two spellings the API has for one format', function () {
        $client = new FakeClient([Fixtures::queuedImage(), optimizedImage(format: 'jpeg'), Fixtures::file(JPEG_SMALLER), Fixtures::noContent()]);

        optimizing($client, $this->path, $this->logger);

        expect(file_get_contents($this->path))->toBe(JPEG_SMALLER);
    });

    it('writes through a link to the file it points at', function () {
        $real = $this->folder.'/real.jpg';
        $link = $this->folder.'/link.jpg';
        rename($this->path, $real);
        symlink($real, $link);
        $client = new FakeClient([Fixtures::queuedImage(), optimizedImage(), Fixtures::file(JPEG_SMALLER), Fixtures::noContent()]);

        optimizing($client, $link, $this->logger);

        expect(is_link($link))->toBeTrue()
            ->and(file_get_contents($real))->toBe(JPEG_SMALLER);
    })->skipOnWindows();

    it('leaves a file Iminify could not shrink alone, downloading nothing', function () {
        $answer = optimizedImage(['status' => 'already-optimized', 'id' => 'abc']);
        $client = new FakeClient([$answer, $answer, Fixtures::noContent()]);

        optimizing($client, $this->path, $this->logger);

        $client->assertNotSent('GET', 'images/abc/download');
        $client->assertSentTimes('DELETE', 'images/abc', 1);

        expect(file_get_contents($this->path))->toBe(JPEG_BYTES);
    });

    it('leaves the file alone when the copy is no smaller', function () {
        $client = new FakeClient([Fixtures::queuedImage(), optimizedImage(['id' => 'abc'], size: strlen(JPEG_BYTES)), Fixtures::noContent()]);

        optimizing($client, $this->path, $this->logger);

        $client->assertNotSent('GET', 'images/abc/download');

        expect(file_get_contents($this->path))->toBe(JPEG_BYTES);
    });
});

describe('what never reaches the file', function () {
    /**
     * Each refuses the write, leaves the bytes as they were, and still deletes the image.
     */
    it('keeps the file when the answer says it should not be touched', function (Closure $answer, string $message) {
        $client = new FakeClient([Fixtures::queuedImage(['id' => 'abc']), $answer()]);
        $client->addAnswers([Fixtures::noContent()]);

        try {
            optimizing($client, $this->path, $this->logger);
            $this->fail('Nothing was thrown.');
        } catch (IminifyException $exception) {
            expect($exception->getMessage())->toContain($message);
        }

        $client->assertNotSent('GET', 'images/abc/download');
        $client->assertSentTimes('DELETE', 'images/abc', 1);

        expect(file_get_contents($this->path))->toBe(JPEG_BYTES);
    })->with([
        'a failed image' => [fn (): array => optimizedImage(['id' => 'abc', 'status' => 'failed', 'optimized' => null]), '[failed]'],
        'a cancelled image' => [fn (): array => optimizedImage(['id' => 'abc', 'status' => 'cancelled', 'optimized' => null]), '[cancelled]'],
        'a status this client does not know' => [fn (): array => optimizedImage(['id' => 'abc', 'status' => 'something-new']), '[unknown]'],
        'a completed image with no copy' => [fn (): array => optimizedImage(['id' => 'abc', 'optimized' => null]), '[completed]'],
        'another format' => [fn (): array => optimizedImage(['id' => 'abc'], format: 'webp'), '[webp]'],
        'an upload that arrived cut off' => [fn (): array => optimizedImage(['id' => 'abc', 'original' => ['name' => 'image.jpg', 'format' => 'jpg', 'size' => 10, 'width' => 1, 'height' => 1, 'download_url' => 'https://www.iminify.com/x']]), 'received 10 bytes'],
    ]);

    it('keeps the file when the download is not the size the API gave, even with no length stated', function (string $bytes) {
        $client = new FakeClient([Fixtures::queuedImage(['id' => 'abc']), optimizedImage(['id' => 'abc']), new ApiResponse(200, [], Utils::streamFor($bytes)), Fixtures::noContent()]);

        expect(fn () => optimizing($client, $this->path, $this->logger))->toThrow(UnexpectedResponseException::class);

        $client->assertSentTimes('DELETE', 'images/abc', 1);

        expect(file_get_contents($this->path))->toBe(JPEG_BYTES);
    })->with(['short' => ['abc'], 'long' => [JPEG_SMALLER.' and more']]);

    it('keeps the file when what came back is not an image of its type, whatever the answer says', function (string $bytes) {
        $client = new FakeClient([Fixtures::queuedImage(['id' => 'abc']), optimizedImage(['id' => 'abc'], size: strlen($bytes)), new ApiResponse(200, [], Utils::streamFor($bytes)), Fixtures::noContent()]);

        expect(fn () => optimizing($client, $this->path, $this->logger))->toThrow(IminifyException::class, 'isn\'t a file of type');

        $client->assertSentTimes('DELETE', 'images/abc', 1);

        expect(file_get_contents($this->path))->toBe(JPEG_BYTES);
    })->with([
        'a login page' => ['<html>Sign in</html>'],
        'an error in JSON' => ['{"error":"no"}'],
        'a PNG under a JPEG name' => ["\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01"],
    ]);

    it('keeps a file replaced with the same number of bytes while the download streamed', function () {
        $replacement = strrev(JPEG_BYTES);
        $body = FnStream::decorate(Utils::streamFor(JPEG_SMALLER), [
            'read' => function (int $length) use ($replacement): string {
                file_put_contents($this->path, $replacement);

                return JPEG_SMALLER;
            },
            'eof' => fn (): bool => file_get_contents($this->path) === $replacement,
            'isSeekable' => fn (): bool => false,
        ]);
        $client = new FakeClient([Fixtures::queuedImage(['id' => 'abc']), optimizedImage(['id' => 'abc']), new ApiResponse(200, [], $body), Fixtures::noContent()]);

        expect(fn () => optimizing($client, $this->path, $this->logger))->toThrow(IminifyException::class, 'changed while')
            ->and(file_get_contents($this->path))->toBe($replacement);
    });

    it('spends nothing on a file it could not write back', function () {
        $client = new FakeClient;
        chmod($this->path, 0444);

        try {
            expect(fn () => optimizing($client, $this->path, $this->logger))->toThrow(IminifyException::class, "can't be replaced");
        } finally {
            chmod($this->path, 0644);
        }

        $client->assertNothingSent();
    })->skipOnWindows();

    it('spends nothing on a file in a folder it could not write to', function () {
        $folder = $this->folder.'/locked';
        mkdir($folder);
        $path = $folder.'/photo.jpg';
        file_put_contents($path, JPEG_BYTES);
        chmod($folder, 0555);
        $client = new FakeClient;

        try {
            expect(fn () => optimizing($client, $path, $this->logger))->toThrow(IminifyException::class, "can't be replaced");
        } finally {
            chmod($folder, 0755);
            unlink($path);
            rmdir($folder);
        }

        $client->assertNothingSent();
    })->skipOnWindows();

    it('sends nothing for a file of a type it does not take', function () {
        file_put_contents($this->path, 'not an image');
        $client = new FakeClient;

        expect(fn () => optimizing($client, $this->path, $this->logger))->toThrow(InvalidArgumentException::class);

        $client->assertNothingSent();
    });
});

describe('its deadline', function () {
    it('cancels and deletes an image still queued at the deadline', function () {
        $client = new FakeClient([
            Fixtures::queuedImage(['id' => 'abc']),
            Fixtures::queuedImage(['id' => 'abc']),
            Fixtures::queuedImage(['id' => 'abc', 'status' => 'cancelled']),
            Fixtures::noContent(),
        ]);

        expect(fn () => optimizing($client, $this->path, $this->logger, ['timeout' => 1]))->toThrow(WaitTimeoutException::class);

        $client->assertSentTimes('POST', 'images/abc/cancel', 1);
        $client->assertSentTimes('DELETE', 'images/abc', 1);

        expect(file_get_contents($this->path))->toBe(JPEG_BYTES);
    });

    it('still tries to delete one that started before it could be cancelled', function () {
        $client = new FakeClient([
            Fixtures::queuedImage(['id' => 'abc']),
            Fixtures::queuedImage(['id' => 'abc']),
            Fixtures::refusal('image_not_cancellable', 409),
            Fixtures::noContent(),
        ]);

        expect(fn () => optimizing($client, $this->path, $this->logger, ['timeout' => 1]))->toThrow(WaitTimeoutException::class);

        $client->assertSentTimes('DELETE', 'images/abc', 1);

        expect($this->logger->at('warning'))->toBe([]);
    });

    it('leaves one still optimizing for Iminify to delete, and says so without hiding the timeout', function () {
        $client = new FakeClient([
            Fixtures::queuedImage(['id' => 'abc']),
            Fixtures::queuedImage(['id' => 'abc', 'status' => 'optimizing']),
            Fixtures::refusal('image_in_progress', 409, 'Still working.'),
        ]);

        expect(fn () => optimizing($client, $this->path, $this->logger, ['timeout' => 1]))->toThrow(WaitTimeoutException::class);

        $client->assertNotSent('POST', 'images/abc/cancel');

        expect($this->logger->at('warning'))->toHaveCount(1)
            ->and($this->logger->at('warning')[0])->toContain('abc')
            ->and($this->logger->at('warning')[0])->toContain('Still working.')
            ->and(file_get_contents($this->path))->toBe(JPEG_BYTES);
    });

    it('takes a timeout as a whole number of seconds', function (mixed $timeout) {
        $client = new FakeClient;

        expect(fn () => optimizing($client, $this->path, $this->logger, ['timeout' => $timeout]))->toThrow(InvalidArgumentException::class, 'timeout');

        $client->assertNothingSent();
    })->with([
        'zero' => [0],
        'below zero' => [-5],
        'not a number' => ['soon'],
        'past a day' => [86401],
        'past what a number holds' => [PHP_INT_MAX],
    ]);

    it('reads a timeout written as a numeric string', function () {
        $answer = optimizedImage(['status' => 'already-optimized']);
        $client = new FakeClient([$answer, $answer, Fixtures::noContent()]);

        optimizing($client, $this->path, $this->logger, ['timeout' => '60']);

        expect(file_get_contents($this->path))->toBe(JPEG_BYTES);
    });
});

describe('refusals', function () {
    it('waits out a short rate limit and sends the file again', function () {
        $answer = optimizedImage(['status' => 'already-optimized']);
        $client = new FakeClient([Fixtures::refusal('rate_limited', 429, retryAfter: 7), $answer, $answer, Fixtures::noContent()]);

        $optimizer = optimizing($client, $this->path, $this->logger);

        $client->assertSentTimes('POST', 'images', 2);

        expect($optimizer->paused)->toBe([7]);
    });

    it('waits out a short rate limit on the download too', function () {
        $client = new FakeClient([Fixtures::queuedImage(['id' => 'abc']), optimizedImage(['id' => 'abc']), Fixtures::refusal('rate_limited', 429, retryAfter: 4), Fixtures::file(JPEG_SMALLER), Fixtures::noContent()]);

        $optimizer = optimizing($client, $this->path, $this->logger);

        expect($optimizer->paused)->toBe([4])
            ->and(file_get_contents($this->path))->toBe(JPEG_SMALLER);
    });

    it('counts what it slept, so a sleep that returns at once still ends the waiting', function () {
        $client = new FakeClient(array_fill(0, 20, Fixtures::refusal('rate_limited', 429, retryAfter: 1)));
        $optimizer = new FakeOptimizer($client, ['timeout' => 3]);

        expect(fn () => $optimizer->handle(new Image($this->path), $this->logger))->toThrow(RateLimitedException::class);

        $client->assertSentTimes('POST', 'images', 3);

        expect($optimizer->paused)->toBe([1, 1]);
    });

    it('gives up on a rate limit longer than the time it has left', function () {
        $client = new FakeClient([Fixtures::refusal('rate_limited', 429, retryAfter: 90)]);

        expect(fn () => optimizing($client, $this->path, $this->logger, ['timeout' => 60]))->toThrow(RateLimitedException::class);

        $client->assertSentTimes('POST', 'images', 1);

        expect(file_get_contents($this->path))->toBe(JPEG_BYTES);
    });

    it('fails at once on a spent daily allowance, which can last hours', function () {
        $client = new FakeClient([Fixtures::refusal('daily_limit_reached', 429, retryAfter: 3)]);
        $optimizer = new FakeOptimizer($client);

        expect(fn () => $optimizer->handle(new Image($this->path), $this->logger))->toThrow(DailyLimitReachedException::class);

        $client->assertSentTimes('POST', 'images', 1);

        expect($optimizer->paused)->toBe([]);
    });

    it('lets a refused upload through with nothing to clean up', function () {
        $client = new FakeClient([Fixtures::refusal('start_failed', 503)]);

        expect(fn () => optimizing($client, $this->path, $this->logger))->toThrow(ServerException::class)
            ->and($client->requests())->toHaveCount(1);
    });

    it('never lets the clean-up replace what went wrong', function () {
        $client = new FakeClient([
            Fixtures::queuedImage(['id' => 'abc']),
            new TransportException('The first error.'),
            new RuntimeException('The second error.'),
        ]);

        expect(fn () => optimizing($client, $this->path, $this->logger))->toThrow(TransportException::class, 'The first error.')
            ->and($this->logger->at('warning')[0])->toContain('The second error.')
            ->and(file_get_contents($this->path))->toBe(JPEG_BYTES);
    });
});

describe('how a chain treats it', function () {
    it('leaves the file and logs the reason by default, and rethrows when asked', function () {
        $refused = fn (): FakeClient => new FakeClient([Fixtures::refusal('daily_limit_reached', 429, 'Out for today.')]);

        (new OptimizerChain)
            ->addOptimizer(new FakeOptimizer($refused()))
            ->useLogger($this->logger)
            ->optimize($this->path);

        expect(file_get_contents($this->path))->toBe(JPEG_BYTES)
            ->and($this->logger->at('error')[0])->toContain('Out for today.')
            ->and(fn () => (new OptimizerChain)->addOptimizer(new FakeOptimizer($refused()))->throws()->optimize($this->path))->toThrow(DailyLimitReachedException::class);
    });

    it('never reaches a config holding only the optimizer, a trap of the base factory', function () {
        $classes = fn (OptimizerChain $chain): array => array_map(get_class(...), $chain->getOptimizers());

        expect($classes(OptimizerChainFactory::create([IminifyOptimizer::class => ['api_key' => 'a-key']])))->not->toContain(IminifyOptimizer::class)
            ->and($classes(OptimizerChainFactory::create([Svgo::class => [], IminifyOptimizer::class => ['api_key' => 'a-key']])))->toBe([Svgo::class, IminifyOptimizer::class]);
    });

    it('is built the way both factories build one, and makes its client from the options', function (Closure $build) {
        $optimizer = new class extends IminifyOptimizer
        {
            public function clientNow(): Client
            {
                return $this->client();
            }
        };

        $build($optimizer);

        $client = $optimizer->clientNow();

        expect((fn (): array => $this->headers)->call($client->transporter())['Authorization'])->toBe('Bearer a-key')
            ->and((fn (): array => $this->config)->call(httpClientOf($client))['timeout'])->toBe(45);
    })->with([
        'with options after' => [fn (IminifyOptimizer $optimizer) => $optimizer->setOptions(['api_key' => 'a-key', 'timeout' => 45])],
        'with options first' => [fn (IminifyOptimizer $optimizer) => $optimizer->__construct(['api_key' => 'a-key', 'timeout' => 45])],
    ]);

    it('refuses to start without a key, in words that say where to get one', function () {
        (new IminifyOptimizer)->handle(new Image($this->path), new NullLogger);
    })->throws(InvalidArgumentException::class, 'Settings > API Tokens');

    it('survives a queue, and keeps nothing a queue could not carry', function () {
        $chain = (new OptimizerChain)->addOptimizer(new IminifyOptimizer(['api_key' => 'a-key', 'timeout' => 45]));

        $restored = unserialize(serialize($chain))->getOptimizers()[0];

        expect($restored)->toBeInstanceOf(IminifyOptimizer::class)
            ->and($restored->options)->toBe(['api_key' => 'a-key', 'timeout' => 45])
            ->and(array_filter(get_object_vars($restored), is_object(...)))->toBe([]);
    });

    it('keeps the key out of a dump', function () {
        ob_start();
        var_dump(new IminifyOptimizer(['api_key' => 'a-secret-key', 'timeout' => 45]));
        $dump = (string) ob_get_clean();

        expect(str_contains($dump, 'a-secret-key'))->toBeFalse()
            ->and($dump)->toContain('[hidden]')
            ->and($dump)->toContain('timeout');
    });
});

describe('through spatie/image', function () {
    it('writes back the smaller file when saving an image with the chain', function () {
        $image = imagecreatetruecolor(32, 32);
        imagejpeg($image, $this->path, 100);

        $client = null;
        $chain = (new OptimizerChain)->throws()->addOptimizer(new FakeOptimizer(function (string $path) use (&$client): Client {
            $file = ['name' => 'image.jpg', 'format' => 'jpg', 'width' => 32, 'height' => 32, 'download_url' => 'https://www.iminify.com/x'];

            return $client = new FakeClient([
                Fixtures::queuedImage(),
                Fixtures::image(['status' => 'completed', 'original' => ['size' => filesize($path)] + $file, 'optimized' => ['size' => strlen(JPEG_SMALLER)] + $file]),
                Fixtures::file(JPEG_SMALLER),
                Fixtures::noContent(),
            ]);
        }));

        SpatieImage::useImageDriver(ImageDriver::Gd)->loadFile($this->path)->optimize($chain)->save();

        expect(file_get_contents($this->path))->toBe(JPEG_SMALLER)
            ->and($client)->toBeInstanceOf(FakeClient::class);
    });
});
