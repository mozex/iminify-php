<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Mozex\Iminify\Enums\AltTextStatus;
use Mozex\Iminify\Enums\CompressionLevel;
use Mozex\Iminify\Enums\DownloadType;
use Mozex\Iminify\Enums\ImageStatus;
use Mozex\Iminify\Enums\OutputFormat;
use Mozex\Iminify\Enums\Priority;
use Mozex\Iminify\Exceptions\ConflictException;
use Mozex\Iminify\Exceptions\DailyLimitReachedException;
use Mozex\Iminify\Exceptions\RateLimitedException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Exceptions\WaitTimeoutException;
use Mozex\Iminify\Http\Upload;
use Mozex\Iminify\Iminify;
use Mozex\Iminify\Responses\Image;
use Mozex\Iminify\Testing\Fixtures;
use Mozex\Iminify\Tests\Fakes\RecordingHttpClient;
use Psr\Http\Message\RequestInterface;

const IMAGE_ID = '9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35';
const IMAGES_URL = 'https://www.iminify.com/api/v1/images';

describe('sending an image', function () {
    it('uploads a path with its options and answers the queued image', function () {
        $path = sys_get_temp_dir().'/iminify-'.bin2hex(random_bytes(6)).'.jpg';
        file_put_contents($path, 'the-bytes');
        $http = new RecordingHttpClient([jsonResponse(['data' => Fixtures::queuedImage(['id' => IMAGE_ID])], 202, ['X-RateLimit-Remaining' => '299'])]);

        try {
            $image = clientAnswering($http)->images()->upload($path, [
                'level' => CompressionLevel::Ultra,
                'format' => OutputFormat::Webp,
                'priority' => Priority::Background,
                'ephemeral' => true,
                'alt_text' => false,
                'responsive_widths' => [480, 960],
                'folder' => null,
            ]);
        } finally {
            unlink($path);
        }

        $request = $http->requests[0];
        $body = (string) $request->getBody();

        expect($request->getMethod())->toBe('POST')
            ->and((string) $request->getUri())->toBe(IMAGES_URL)
            ->and($request->getHeaderLine('Content-Type'))->toStartWith('multipart/form-data; boundary=')
            ->and($body)->toContain('filename="'.basename($path).'"')
            ->toContain("\r\n\r\nthe-bytes\r\n")
            ->and(formFields($body))->toBe([
                'level' => ['ultra'],
                'format' => ['webp'],
                'priority' => ['background'],
                'ephemeral' => ['1'],
                'alt_text' => ['0'],
                'responsive_widths[]' => ['480', '960'],
            ])
            ->and($image->status)->toBe(ImageStatus::Queued)
            ->and($image->id)->toBe(IMAGE_ID)
            ->and($image->rateLimit->remaining)->toBe(299);
    });

    it('uploads a stream under the name it was given, and bytes through an Upload', function () {
        $stream = (new HttpFactory)->createStream('stream-bytes');
        $http = new RecordingHttpClient([jsonResponse(['data' => Fixtures::queuedImage()], 202), jsonResponse(['data' => Fixtures::queuedImage()], 202)]);
        $images = clientAnswering($http)->images();

        $images->upload($stream, name: 'a.png');
        $images->upload(Upload::fromContents('memory-bytes', 'b.png'));

        expect((string) $http->requests[0]->getBody())->toContain('filename="a.png"')->toContain('stream-bytes')
            ->and((string) $http->requests[1]->getBody())->toContain('filename="b.png"')->toContain('memory-bytes');
    });

    it('refuses an upload it could only send wrong', function () {
        $images = clientAnswering(new RecordingHttpClient)->images();

        expect(fn () => $images->upload((new HttpFactory)->createStream('x')))->toThrow(InvalidArgumentException::class, 'needs a name')
            ->and(fn () => $images->upload(123))->toThrow(InvalidArgumentException::class, 'An upload takes')
            ->and(fn () => $images->upload('/no/such/file.jpg'))->toThrow(InvalidArgumentException::class, 'no readable file');
    });

    it('has the API fetch an address, sending JSON', function () {
        $http = new RecordingHttpClient([jsonResponse(['data' => Fixtures::queuedImage()], 202)]);

        clientAnswering($http)->images()->fromUrl('https://example.com/a.jpg', ['level' => CompressionLevel::Smart, 'ephemeral' => true, 'priority' => Priority::Background]);

        expect($http->requests[0]->getHeaderLine('Content-Type'))->toBe('application/json')
            ->and(json_decode((string) $http->requests[0]->getBody(), true))->toBe([
                'url' => 'https://example.com/a.jpg',
                'level' => 'smart',
                'ephemeral' => true,
                'priority' => 'background',
            ]);
    });
});

describe('the calls on one image', function () {
    it('sends each to its address and answers the image', function (string $method, array $arguments, string $verb, string $path, ?array $json) {
        $http = new RecordingHttpClient([jsonResponse(['data' => Fixtures::image()])]);

        $image = clientAnswering($http)->images()->{$method}(...$arguments);

        expect($image)->toBeInstanceOf(Image::class)
            ->and($http->requests[0]->getMethod())->toBe($verb)
            ->and((string) $http->requests[0]->getUri())->toBe(IMAGES_URL.'/'.IMAGE_ID.$path)
            ->and(json_decode((string) $http->requests[0]->getBody(), true))->toBe($json);
    })->with([
        'get' => ['get', [IMAGE_ID], 'GET', '', null],
        'rename' => ['rename', [IMAGE_ID, 'new-name.webp'], 'PATCH', '', ['name' => 'new-name.webp']],
        'recompress' => ['recompress', [IMAGE_ID, ['level' => CompressionLevel::Lossless]], 'POST', '/recompress', ['level' => 'lossless']],
        'retry' => ['retry', [IMAGE_ID], 'POST', '/retry', []],
        'cancel' => ['cancel', [IMAGE_ID], 'POST', '/cancel', []],
        'writeAltText' => ['writeAltText', [IMAGE_ID, ['alt_text_language' => 'fr']], 'POST', '/alt-text', ['alt_text_language' => 'fr']],
        'share' => ['share', [IMAGE_ID], 'POST', '/share', []],
    ]);

    it('sends the two that answer nothing', function (string $method, string $path) {
        $http = new RecordingHttpClient([new Response(204)]);

        clientAnswering($http)->images()->{$method}(IMAGE_ID);

        expect($http->requests[0]->getMethod())->toBe('DELETE')
            ->and((string) $http->requests[0]->getUri())->toBe(IMAGES_URL.'/'.IMAGE_ID.$path);
    })->with([
        'delete' => ['delete', ''],
        'unshare' => ['unshare', '/share'],
    ]);

    it('takes the image itself where it takes an id', function () {
        $http = new RecordingHttpClient([jsonResponse(['data' => Fixtures::image()])]);

        clientAnswering($http)->images()->get(Image::from(Fixtures::image(['id' => 'from-the-image'])));

        expect((string) $http->requests[0]->getUri())->toBe(IMAGES_URL.'/from-the-image');
    });

    it('keeps a hostile id inside its one path segment', function () {
        $http = new RecordingHttpClient([jsonResponse(['data' => Fixtures::image()])]);

        clientAnswering($http)->images()->get('../account');

        expect((string) $http->requests[0]->getUri())->toBe(IMAGES_URL.'/..%2Faccount')
            ->and(fn () => clientAnswering($http)->images()->delete(''))->toThrow(InvalidArgumentException::class, 'must not be blank');
    });

    it('ends an answer it cannot read in its own exception', function (array $data) {
        $http = new RecordingHttpClient([jsonResponse(['data' => $data])]);

        expect(fn () => clientAnswering($http)->images()->get(IMAGE_ID))->toThrow(UnexpectedResponseException::class, 'could not read');
    })->with([
        'a list, as a followed redirect answers' => [[Fixtures::image()]],
        'nothing' => [[]],
        'no id' => [array_diff_key(Fixtures::image(), ['id' => true])],
        'no status' => [array_diff_key(Fixtures::image(), ['status' => true])],
        'no original' => [array_diff_key(Fixtures::image(), ['original' => true])],
        'a file without its size' => [Fixtures::image(['optimized' => ['name' => 'a.webp', 'format' => 'webp']])],
        'a number as text' => [Fixtures::image(['saved_bytes' => 'many'])],
        'no date' => [array_diff_key(Fixtures::image(), ['created_at' => true])],
        'a date that is none' => [Fixtures::image(['updated_at' => 'yesterday-ish'])],
        'an empty date, which PHP alone reads as now' => [Fixtures::image(['created_at' => ''])],
        'a relative date' => [Fixtures::image(['updated_at' => 'tomorrow'])],
        'a date with more after it' => [Fixtures::image(['updated_at' => '2026-10-01T09:30:00+00:00 +1 year'])],
    ]);

    it('ends a list it cannot read in its own exception too', function () {
        $http = new RecordingHttpClient([jsonResponse(['message' => 'A proxy talking']), jsonResponse(['data' => [['id' => 'a']]]), jsonResponse(['data' => ['not-an-image']])]);
        $images = clientAnswering($http)->images();

        expect(fn () => $images->list())->toThrow(UnexpectedResponseException::class, 'could not read')
            ->and(fn () => $images->list())->toThrow(UnexpectedResponseException::class, 'could not read')
            ->and(fn () => $images->renameMany(['a'], 'b'))->toThrow(UnexpectedResponseException::class, 'could not read');
    });

    it('lets the API refusal reach the caller', function () {
        $http = new RecordingHttpClient([jsonResponse(['message' => 'Still running.', 'code' => 'image_in_progress'], 409)]);

        expect(fn () => clientAnswering($http)->images()->delete(IMAGE_ID))->toThrow(ConflictException::class, 'Still running.');
    });
});

describe('lists and batches', function () {
    it('lists a page of images with the filters it was given', function () {
        $http = new RecordingHttpClient([jsonResponse([
            'data' => [Fixtures::image(['id' => 'a'])],
            'links' => [],
            'meta' => ['current_page' => 1, 'last_page' => 2, 'per_page' => 15, 'total' => 16],
        ], 200, ['X-RateLimit-Limit' => '300'])]);

        $page = clientAnswering($http)->images()->list(['status' => ImageStatus::Failed, 'per_page' => 15, 'scan' => null]);

        expect((string) $http->requests[0]->getUri())->toBe(IMAGES_URL.'?status=failed&per_page=15')
            ->and($page->items[0]->id)->toBe('a')
            ->and($page->nextPage)->toBe(2)
            ->and($page->rateLimit->limit)->toBe(300);
    });

    it('renames several at once, taking ids and images alike', function () {
        $http = new RecordingHttpClient([jsonResponse(['data' => [Fixtures::image(['id' => 'a']), Fixtures::image(['id' => 'b'])]])]);

        $renamed = clientAnswering($http)->images()->renameMany(['a', Image::from(Fixtures::image(['id' => 'b']))], 'holiday');

        expect((string) $http->requests[0]->getUri())->toBe(IMAGES_URL.'/rename')
            ->and(json_decode((string) $http->requests[0]->getBody(), true))->toBe(['ids' => ['a', 'b'], 'name' => 'holiday'])
            ->and(array_map(fn (Image $image): string => $image->id, $renamed))->toBe(['a', 'b']);
    });
});

describe('downloading', function () {
    it('fetches a file with the key, and hands back what it read', function (string $method, array $arguments, string $verb, string $path, ?array $json) {
        $http = new RecordingHttpClient([new Response(200, ['Content-Type' => 'image/webp', 'Content-Disposition' => 'inline; filename="a.webp"'], 'bytes')]);

        $download = clientAnswering($http)->images()->{$method}(...$arguments);

        expect($download->contents())->toBe('bytes')
            ->and($download->name)->toBe('a.webp')
            ->and($http->requests[0]->getMethod())->toBe($verb)
            ->and((string) $http->requests[0]->getUri())->toBe(IMAGES_URL.$path)
            ->and($http->requests[0]->getHeaderLine('Authorization'))->toBe('Bearer test-key')
            ->and(json_decode((string) $http->requests[0]->getBody(), true))->toBe($json);
    })->with([
        'the optimized copy' => ['download', [IMAGE_ID], 'GET', '/'.IMAGE_ID.'/download?type=optimized', null],
        'the original' => ['download', [IMAGE_ID, DownloadType::Original], 'GET', '/'.IMAGE_ID.'/download?type=original', null],
        'one responsive size' => ['downloadResponsive', [IMAGE_ID, 480], 'GET', '/'.IMAGE_ID.'/responsive/480', null],
        'every responsive size' => ['downloadResponsiveZip', [IMAGE_ID], 'GET', '/'.IMAGE_ID.'/responsive.zip', null],
        'several images' => ['downloadMany', [['a', 'b']], 'POST', '/download', ['ids' => ['a', 'b']]],
    ]);
});

describe('waiting', function () {
    it('reads the image until it has finished, backing off between looks', function () {
        [$sleep, $slept] = recordingSleep();
        $http = new RecordingHttpClient([
            jsonResponse(['data' => Fixtures::queuedImage()]),
            jsonResponse(['data' => Fixtures::queuedImage(['status' => 'optimizing'])]),
            jsonResponse(['data' => Fixtures::queuedImage(['status' => 'optimizing'])]),
            jsonResponse(['data' => Fixtures::image()]),
        ]);

        $image = factoryAnswering($http)->withSleep($sleep)->make()->images()->wait(IMAGE_ID);

        expect($image->status)->toBe(ImageStatus::Completed)
            ->and($http->requests)->toHaveCount(4)
            ->and($slept->getArrayCopy())->toBe([1.0, 1.5, 2.25]);
    });

    it('ends on a failed or cancelled image too, without reading further', function (string $status) {
        $http = new RecordingHttpClient([jsonResponse(['data' => Fixtures::queuedImage(['status' => $status])])]);

        expect(clientAnswering($http)->images()->wait(IMAGE_ID)->status->value)->toBe($status)
            ->and($http->requests)->toHaveCount(1);
    })->with(['failed', 'cancelled', 'already-optimized']);

    it('never waits longer than five seconds between looks', function () {
        [$sleep, $slept] = recordingSleep();
        $http = new RecordingHttpClient(array_fill(0, 12, jsonResponse(['data' => Fixtures::queuedImage()])));
        $http->responses[] = jsonResponse(['data' => Fixtures::image()]);

        factoryAnswering($http)->withSleep($sleep)->make()->images()->wait(IMAGE_ID);

        expect(max($slept->getArrayCopy()))->toBe(5.0);
    });

    it('waits on for more when asked, such as the alt text', function () {
        $http = new RecordingHttpClient([
            jsonResponse(['data' => Fixtures::image(['alt_text' => ['status' => 'pending', 'text' => null, 'language' => 'en', 'suggested_name' => null]])]),
            jsonResponse(['data' => Fixtures::image()]),
        ]);

        $image = clientAnswering($http)->images()->wait(IMAGE_ID, until: fn (Image $image): bool => $image->altText?->status !== AltTextStatus::Pending);

        expect($image->altText->status)->toBe(AltTextStatus::Written)
            ->and($http->requests)->toHaveCount(2);
    });

    it('holds to the finish whatever more it was asked to wait for', function () {
        $http = new RecordingHttpClient([
            jsonResponse(['data' => Fixtures::queuedImage()]),
            jsonResponse(['data' => Fixtures::queuedImage(['status' => 'optimizing'])]),
            jsonResponse(['data' => Fixtures::image(['alt_text' => null])]),
        ]);

        $image = clientAnswering($http)->images()->wait(IMAGE_ID, until: fn (Image $image): bool => $image->altText?->status !== AltTextStatus::Pending);

        expect($image->status)->toBe(ImageStatus::Completed)
            ->and($http->requests)->toHaveCount(3);
    });

    it('ends on a failed image, whose alt text stays pending for good', function () {
        $http = new RecordingHttpClient([jsonResponse(['data' => Fixtures::queuedImage([
            'status' => 'failed',
            'alt_text' => ['status' => 'pending', 'text' => null, 'language' => 'en', 'suggested_name' => null],
        ])])]);

        $image = clientAnswering($http)->images()->wait(IMAGE_ID, until: fn (Image $image): bool => $image->altText?->status !== AltTextStatus::Pending);

        expect($image->status)->toBe(ImageStatus::Failed)
            ->and($http->requests)->toHaveCount(1);
    });

    it('counts the real clock as well as what it slept', function () {
        $http = new RecordingHttpClient(array_fill(0, 2, jsonResponse(['data' => Fixtures::queuedImage()])));

        expect(fn () => clientAnswering($http)->images()->wait(IMAGE_ID, 1))->toThrow(WaitTimeoutException::class)
            ->and($http->requests)->toHaveCount(1);
    });

    it('waits out a rate limit and reads again', function () {
        [$sleep, $slept] = recordingSleep();
        $http = new RecordingHttpClient([
            jsonResponse(['message' => 'Slow down.', 'code' => 'rate_limited'], 429, ['Retry-After' => '7']),
            jsonResponse(['data' => Fixtures::image()]),
        ]);

        $image = factoryAnswering($http)->withSleep($sleep)->make()->images()->wait(IMAGE_ID);

        expect($image->status)->toBe(ImageStatus::Completed)
            ->and($slept->getArrayCopy())->toBe([7.0]);
    });

    it('gives up at once when the wait for a rate limit would pass the timeout', function () {
        [$sleep, $slept] = recordingSleep();
        $http = new RecordingHttpClient([jsonResponse(['message' => 'Slow down.', 'code' => 'rate_limited'], 429, ['Retry-After' => '50'])]);

        expect(fn () => factoryAnswering($http)->withSleep($sleep)->make()->images()->wait(IMAGE_ID, 10))
            ->toThrow(WaitTimeoutException::class, 'within 10 seconds')
            ->and($slept->getArrayCopy())->toBe([]);
    });

    it('lets a spent day reach the caller rather than waiting hours', function () {
        $http = new RecordingHttpClient([jsonResponse(['message' => 'Spent.', 'code' => 'daily_limit_reached'], 429, ['Retry-After' => '40000'])]);

        expect(fn () => clientAnswering($http)->images()->wait(IMAGE_ID))->toThrow(DailyLimitReachedException::class);
    });

    it('stops at the timeout with the image as last read, when sleeping takes no time', function () {
        $http = new RecordingHttpClient(array_fill(0, 40, jsonResponse(['data' => Fixtures::queuedImage()])));

        try {
            clientAnswering($http)->images()->wait(IMAGE_ID, 10);
            $this->fail('Nothing was thrown.');
        } catch (WaitTimeoutException $exception) {
            expect($exception->last)->toBeInstanceOf(Image::class)
                ->and($exception->last->status)->toBe(ImageStatus::Queued)
                ->and(count($http->requests))->toBeLessThan(10);
        }
    });

    it('keeps the image it was given as the last read when the very first read is rate limited past the timeout', function () {
        $given = Image::from(Fixtures::queuedImage());
        $http = new RecordingHttpClient([jsonResponse(['message' => 'Slow down.', 'code' => 'rate_limited'], 429, ['Retry-After' => '50'])]);

        try {
            clientAnswering($http)->images()->wait($given, 10);
            $this->fail('Nothing was thrown.');
        } catch (WaitTimeoutException $exception) {
            expect($exception->last)->toBe($given);
        }
    });

    it('asks the API again after a rate limit it is told nothing about', function () {
        [$sleep, $slept] = recordingSleep();
        $http = new RecordingHttpClient([
            jsonResponse(['message' => 'Slow down.', 'code' => 'rate_limited'], 429),
            jsonResponse(['data' => Fixtures::image()]),
        ]);

        factoryAnswering($http)->withSleep($sleep)->make()->images()->wait(IMAGE_ID);

        expect($slept->getArrayCopy())->toBe([1.0]);
    });

    it('sleeps for real when nothing replaces the sleep', function () {
        $http = new RecordingHttpClient([jsonResponse(['data' => Fixtures::queuedImage()]), jsonResponse(['data' => Fixtures::image()])]);
        $client = Iminify::factory()->withApiKey('k')->withHttpClient($http)->withRequestFactory(new HttpFactory)->withStreamFactory(new HttpFactory)->make();
        $started = hrtime(true);

        $client->images()->wait(IMAGE_ID);

        expect((hrtime(true) - $started) / 1e9)->toBeGreaterThanOrEqual(0.9);
    });
});

it('uploads an image, waits for it and saves the result', function () {
    $source = tempnam(sys_get_temp_dir(), 'iminify');
    $target = tempnam(sys_get_temp_dir(), 'iminify');
    file_put_contents($source, 'the-upload');
    file_put_contents($target, 'old');
    $http = new RecordingHttpClient([
        jsonResponse(['data' => Fixtures::queuedImage(['id' => IMAGE_ID])], 202),
        jsonResponse(['data' => Fixtures::queuedImage(['id' => IMAGE_ID, 'status' => 'optimizing'])]),
        jsonResponse(['data' => Fixtures::image()]),
        new Response(200, ['Content-Type' => 'image/webp', 'Content-Length' => '11'], 'the-smaller'),
    ]);

    try {
        $images = clientAnswering($http)->images();
        $finished = $images->wait($images->upload($source, name: 'photo.jpg'));
        $written = $images->download($finished)->saveTo($target);
        $saved = file_get_contents($target);
    } finally {
        unlink($source);
        unlink($target);
    }

    expect($finished->status)->toBe(ImageStatus::Completed)
        ->and($written)->toBe(11)
        ->and($saved)->toBe('the-smaller')
        ->and(array_map(fn (RequestInterface $request): string => $request->getMethod().' '.$request->getUri()->getPath(), $http->requests))->toBe([
            'POST /api/v1/images',
            'GET /api/v1/images/'.IMAGE_ID,
            'GET /api/v1/images/'.IMAGE_ID,
            'GET /api/v1/images/'.IMAGE_ID.'/download',
        ]);
});

it('does not catch a rate limit on a call that is not waiting', function () {
    $http = new RecordingHttpClient([jsonResponse(['message' => 'Slow down.', 'code' => 'rate_limited'], 429, ['Retry-After' => '7'])]);

    expect(fn () => clientAnswering($http)->images()->get(IMAGE_ID))->toThrow(RateLimitedException::class);
});
