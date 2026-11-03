<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mozex\Iminify\Client;
use Mozex\Iminify\Exceptions\AccessDeniedException;
use Mozex\Iminify\Exceptions\ApiException;
use Mozex\Iminify\Exceptions\AuthenticationException;
use Mozex\Iminify\Exceptions\ConflictException;
use Mozex\Iminify\Exceptions\DailyLimitReachedException;
use Mozex\Iminify\Exceptions\IminifyException;
use Mozex\Iminify\Exceptions\NotFoundException;
use Mozex\Iminify\Exceptions\PayloadTooLargeException;
use Mozex\Iminify\Exceptions\ProRequiredException;
use Mozex\Iminify\Exceptions\RateLimitedException;
use Mozex\Iminify\Exceptions\ServerException;
use Mozex\Iminify\Exceptions\TransportException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Exceptions\ValidationException;
use Mozex\Iminify\Factory;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Http\Upload;
use Mozex\Iminify\Iminify;
use Mozex\Iminify\Support\ClientIdentifier;
use Mozex\Iminify\Tests\Fakes\FollowingHttpClient;
use Mozex\Iminify\Tests\Fakes\RecordingHttpClient;
use Psr\Http\Client\ClientInterface;

describe('every request', function () {
    it('goes to the base address with the key, asks for JSON and names the client', function () {
        $http = new RecordingHttpClient([jsonResponse(['data' => []])]);

        clientAnswering($http)->transporter()->send(ApiRequest::get('images', ['status' => 'failed', 'page' => 2, 'scan' => null]));

        $request = $http->requests[0];

        expect((string) $request->getUri())->toBe('https://www.iminify.com/api/v1/images?status=failed&page=2')
            ->and($request->getHeaderLine('Authorization'))->toBe('Bearer test-key')
            ->and($request->getHeaderLine('Accept'))->toBe('application/json')
            ->and($request->getHeaderLine('Iminify-Client'))->toMatch('/\Aphp\/\d+\.\d+\.\d+(?:-[0-9A-Za-z.]{1,8})?\z/');
    });

    it('keeps the key out of a dump of the client and the factory', function () {
        $factory = factoryAnswering(new RecordingHttpClient);
        $client = $factory->make();

        expect(print_r($client, true))->not->toContain('test-key')
            ->and(print_r($client->transporter(), true))->toContain('[hidden]')
            ->and(print_r($factory, true))->not->toContain('test-key')
            ->and(print_r($factory, true))->toContain('[hidden]');
    });

    it('sends JSON, and an empty body as an empty object', function () {
        $http = new RecordingHttpClient(array_fill(0, 3, jsonResponse(['data' => []])));
        $transporter = clientAnswering($http)->transporter();

        $transporter->send(ApiRequest::post('images', ['url' => 'https://a.test/b.webp', 'level' => null]));
        $transporter->send(ApiRequest::post('images/1/retry'));
        $transporter->send(ApiRequest::patch('monitors/1', ['pages' => null, 'name' => 'a']));

        expect((string) $http->requests[0]->getBody())->toBe('{"url":"https://a.test/b.webp"}')
            ->and($http->requests[0]->getHeaderLine('Content-Type'))->toBe('application/json')
            ->and((string) $http->requests[1]->getBody())->toBe('{}')
            ->and((string) $http->requests[2]->getBody())->toBe('{"pages":null,"name":"a"}');
    });

    it('encodes an id so it stays one path segment, and refuses a blank or dotted one', function () {
        expect(ApiRequest::segment('a/../account'))->toBe('a%2F..%2Faccount')
            ->and(fn () => ApiRequest::segment(''))->toThrow(InvalidArgumentException::class)
            ->and(fn () => ApiRequest::segment(' '))->toThrow(InvalidArgumentException::class)
            ->and(fn () => ApiRequest::segment('..'))->toThrow(InvalidArgumentException::class);
    });
});

describe('a multipart upload', function () {
    it('carries the fields and the bytes with a boundary only its header names', function () {
        $http = new RecordingHttpClient([jsonResponse(['data' => []], 202)]);

        clientAnswering($http)->transporter()->send(ApiRequest::upload(
            'images',
            Upload::fromContents('the-bytes', 'photo.jpg'),
            ['ephemeral' => true, 'alt_text' => false, 'level' => 'smart', 'width' => 800, 'responsive_widths' => [480, 960], 'folder' => null],
        ));

        $request = $http->requests[0];
        expect(preg_match('/\Amultipart\/form-data; boundary=([0-9a-f]{32})\z/', $request->getHeaderLine('Content-Type'), $match))->toBe(1);
        $b = $match[1];

        expect((string) $request->getBody())->toBe(
            "--{$b}\r\nContent-Disposition: form-data; name=\"ephemeral\"\r\n\r\n1\r\n".
            "--{$b}\r\nContent-Disposition: form-data; name=\"alt_text\"\r\n\r\n0\r\n".
            "--{$b}\r\nContent-Disposition: form-data; name=\"level\"\r\n\r\nsmart\r\n".
            "--{$b}\r\nContent-Disposition: form-data; name=\"width\"\r\n\r\n800\r\n".
            "--{$b}\r\nContent-Disposition: form-data; name=\"responsive_widths[]\"\r\n\r\n480\r\n".
            "--{$b}\r\nContent-Disposition: form-data; name=\"responsive_widths[]\"\r\n\r\n960\r\n".
            "--{$b}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"photo.jpg\"\r\nContent-Type: application/octet-stream\r\n\r\nthe-bytes\r\n".
            "--{$b}--\r\n"
        );
    });

    it('cannot have its header ended by a hostile file name', function () {
        $http = new RecordingHttpClient([jsonResponse(['data' => []], 202)]);

        clientAnswering($http)->transporter()->send(ApiRequest::upload('images', Upload::fromContents('x', "a\"\r\nX-Evil: 1\r\n\r\n.jpg")));

        expect((string) $http->requests[0]->getBody())
            ->toContain('filename="a%22%0D%0AX-Evil: 1%0D%0A%0D%0A.jpg"');
    });

    it('refuses a field that a form cannot carry', function () {
        $transporter = clientAnswering(new RecordingHttpClient)->transporter();

        expect(fn () => $transporter->send(ApiRequest::upload('images', Upload::fromContents('x', 'a.jpg'), ['level' => new stdClass])))
            ->toThrow(InvalidArgumentException::class, 'A form field takes a scalar');
    });

    it('streams from a path, an open stream and a PSR-7 stream', function () {
        $path = tempnam(sys_get_temp_dir(), 'iminify');
        file_put_contents($path, 'from-path');
        $handle = fopen('php://memory', 'r+b');
        fwrite($handle, 'from-resource');
        rewind($handle);
        $http = new RecordingHttpClient(array_fill(0, 3, jsonResponse(['data' => []], 202)));
        $transporter = clientAnswering($http)->transporter();

        try {
            $transporter->send(ApiRequest::upload('images', Upload::fromPath($path, 'a.jpg')));
            $transporter->send(ApiRequest::upload('images', Upload::fromStream($handle, 'b.jpg')));
            $transporter->send(ApiRequest::upload('images', Upload::fromStream((new HttpFactory)->createStream('from-psr'), 'c.jpg')));
        } finally {
            unlink($path);
        }

        expect((string) $http->requests[0]->getBody())->toContain('filename="a.jpg"')->toContain('from-path')
            ->and((string) $http->requests[1]->getBody())->toContain('filename="b.jpg"')->toContain('from-resource')
            ->and((string) $http->requests[2]->getBody())->toContain('filename="c.jpg"')->toContain('from-psr');
    });

    it('refuses a stream closed before it could be sent, rather than sending its description', function () {
        $handle = fopen('php://memory', 'r+b');
        $upload = Upload::fromStream($handle, 'a.jpg');
        fclose($handle);
        $http = new RecordingHttpClient;

        expect(fn () => clientAnswering($http)->transporter()->send(ApiRequest::upload('images', $upload)))
            ->toThrow(InvalidArgumentException::class, 'was closed')
            ->and($http->requests)->toBe([]);
    });

    it('stops rather than send a file it could not copy whole', function () {
        $readOnly = fopen('php://memory', 'rb');

        expect(fn () => Upload::fromContents('bytes', 'a.jpg')->copyTo($readOnly))->toThrow(TransportException::class, 'temporary stream');
    });

    it('never holds a large file in memory', function () {
        $path = tempnam(sys_get_temp_dir(), 'iminify');
        $handle = fopen($path, 'r+b');
        ftruncate($handle, 40 * 1024 * 1024);
        fclose($handle);
        $http = new RecordingHttpClient([jsonResponse(['data' => []], 202)]);
        $transporter = clientAnswering($http)->transporter();
        gc_collect_cycles();
        memory_reset_peak_usage();
        $before = memory_get_usage();

        try {
            $transporter->send(ApiRequest::upload('images', Upload::fromPath($path, 'large.jpg')));
        } finally {
            unlink($path);
        }

        expect(memory_get_peak_usage() - $before)->toBeLessThan(8 * 1024 * 1024)
            ->and($http->requests[0]->getBody()->getSize())->toBeGreaterThan(40 * 1024 * 1024);
    });

    it('takes the name from the path and refuses what it cannot read or name', function () {
        $path = tempnam(sys_get_temp_dir(), 'iminify');

        try {
            expect(Upload::fromPath($path)->name)->toBe(basename($path));
        } finally {
            unlink($path);
        }

        expect(fn () => Upload::fromPath('/no/such/file.jpg'))->toThrow(InvalidArgumentException::class, 'no readable file')
            ->and(fn () => Upload::fromContents('x', ' '))->toThrow(InvalidArgumentException::class, 'file name')
            ->and(fn () => Upload::fromStream('not-a-stream', 'a.jpg'))->toThrow(InvalidArgumentException::class, 'open stream');
    });
});

describe('an error answer', function () {
    it('reaches the exception its code and status call for', function (int $status, string $code, string $class) {
        $http = new RecordingHttpClient([jsonResponse(['message' => 'The reason.', 'code' => $code], $status, ['Retry-After' => '7'])]);

        try {
            clientAnswering($http)->transporter()->send(ApiRequest::get('images'));
            $this->fail('Nothing was thrown.');
        } catch (ApiException $exception) {
            expect($exception::class)->toBe($class)
                ->and($exception->getMessage())->toBe('The reason.')
                ->and($exception->status)->toBe($status)
                ->and($exception->errorCode)->toBe($code)
                ->and($exception->retryAfter)->toBe(7);
        }
    })->with([
        [401, 'unauthenticated', AuthenticationException::class],
        [403, 'email_not_verified', AccessDeniedException::class],
        [403, 'account_banned', AccessDeniedException::class],
        [403, 'pro_required', ProRequiredException::class],
        [404, 'not_found', NotFoundException::class],
        [405, 'method_not_allowed', ApiException::class],
        [409, 'image_in_progress', ConflictException::class],
        [413, 'payload_too_large', PayloadTooLargeException::class],
        [422, 'validation_failed', ValidationException::class],
        [429, 'rate_limited', RateLimitedException::class],
        [429, 'daily_limit_reached', DailyLimitReachedException::class],
        [500, 'server_error', ServerException::class],
        [503, 'start_failed', ServerException::class],
    ]);

    it('names each field that failed validation', function () {
        $http = new RecordingHttpClient([jsonResponse([
            'message' => 'The selected level is invalid.',
            'code' => 'validation_failed',
            'errors' => ['level' => ['The selected level is invalid.']],
        ], 422)]);

        try {
            clientAnswering($http)->transporter()->send(ApiRequest::get('images'));
            $this->fail('Nothing was thrown.');
        } catch (ValidationException $exception) {
            expect($exception->errors)->toBe(['level' => ['The selected level is invalid.']]);
        }
    });

    it('still ends in its status when a proxy answered with a page of its own', function (int $status, string $class) {
        $http = new RecordingHttpClient([new Response($status, ['Content-Type' => 'text/html'], '<html>Bad gateway</html>')]);

        try {
            clientAnswering($http)->transporter()->send(ApiRequest::get('images'));
            $this->fail('Nothing was thrown.');
        } catch (ApiException $exception) {
            expect($exception::class)->toBe($class)
                ->and($exception->errorCode)->toBe('http_error')
                ->and($exception->getMessage())->toContain((string) $status);
        }
    })->with([
        [502, ServerException::class],
        [429, RateLimitedException::class],
    ]);

    it('wraps a failure to connect, keeping the original as the previous exception', function () {
        $failure = new ConnectException('Connection refused', new Request('GET', 'https://www.iminify.com/api/v1/images'));
        $http = new RecordingHttpClient([$failure]);

        try {
            clientAnswering($http)->transporter()->send(ApiRequest::get('images'));
            $this->fail('Nothing was thrown.');
        } catch (TransportException $exception) {
            expect($exception)->toBeInstanceOf(IminifyException::class)
                ->and($exception->getMessage())->toContain('Connection refused')
                ->and($exception->getPrevious())->toBe($failure);
        }
    });

    it('refuses a redirect rather than following it', function () {
        $http = new RecordingHttpClient([new Response(301, ['Location' => 'https://www.iminify.com/api/v1/images'])]);

        expect(fn () => clientAnswering($http)->transporter()->send(ApiRequest::post('images')))
            ->toThrow(UnexpectedResponseException::class, 'Set the base URI')
            ->and($http->requests)->toHaveCount(1);
    });
});

describe('a successful answer', function () {
    it('carries its rate limit, a missing header read as null', function () {
        $http = new RecordingHttpClient([
            jsonResponse(['data' => []], 200, ['X-RateLimit-Limit' => '300', 'X-RateLimit-Remaining' => '299']),
            jsonResponse(['data' => []]),
        ]);
        $transporter = clientAnswering($http)->transporter();

        $limited = $transporter->send(ApiRequest::get('images'))->rateLimit();
        $plain = $transporter->send(ApiRequest::get('images'))->rateLimit();

        expect($limited->limit)->toBe(300)
            ->and($limited->remaining)->toBe(299)
            ->and($limited->retryAfter)->toBeNull()
            ->and($plain->limit)->toBeNull();
    });

    it('refuses a body that is not the JSON object the API answers with', function (string $body, string $message) {
        $http = new RecordingHttpClient([new Response(200, [], $body)]);
        $response = clientAnswering($http)->transporter()->send(ApiRequest::get('images'));

        expect(fn () => $response->data())->toThrow(UnexpectedResponseException::class, $message);
    })->with([
        ['<html>', "isn't a JSON object"],
        ['"text"', "isn't a JSON object"],
        ['{"message":"no data"}', 'without the `data` key'],
    ]);
});

describe('the factory', function () {
    it('makes a client with whatever HTTP client it discovers', function () {
        expect(Iminify::client('a-key'))->toBeInstanceOf(Client::class);
    });

    it('turns redirects off on a client it discovered, which may follow them', function () {
        $discovered = new FollowingHttpClient;
        $factory = new class($discovered) extends Factory
        {
            public function __construct(protected ClientInterface $discovered) {}

            protected function findHttpClient(): ClientInterface
            {
                return $this->discovered;
            }
        };

        $factory->withApiKey('a-key')->withRequestFactory(new HttpFactory)->withStreamFactory(new HttpFactory)->make();

        expect($discovered->options)->toBe(['max_redirects' => 0]);
    });

    it('leaves a client it was given as it was set up', function () {
        $given = new FollowingHttpClient;

        (new Factory)->withApiKey('a-key')->withHttpClient($given)->withRequestFactory(new HttpFactory)->withStreamFactory(new HttpFactory)->make();

        expect($given->options)->toBe([]);
    });

    describe('a timeout', function () {
        $finding = fn (ClientInterface $client): Factory => (new class($client) extends Factory
        {
            public function __construct(protected ClientInterface $found) {}

            protected function findHttpClient(): ClientInterface
            {
                return $this->found;
            }
        })->withApiKey('a-key')->withRequestFactory(new HttpFactory)->withStreamFactory(new HttpFactory);

        $httpClientOf = fn (Client $client): ClientInterface => (fn (): ClientInterface => $this->client)->call($client->transporter());

        it('reaches a client found that takes options, beside redirects off', function () use ($finding) {
            $found = new FollowingHttpClient;

            $finding($found)->withTimeout(30)->make();

            expect($found->options)->toBe(['max_redirects' => 0, 'timeout' => 30, 'max_duration' => 30]);
        });

        it('makes Guzzle again with it, since one found cannot be changed', function () use ($finding, $httpClientOf) {
            $client = $httpClientOf($finding(new GuzzleClient)->withTimeout(30)->make());

            expect($client)->toBeInstanceOf(GuzzleClient::class)
                ->and((fn (): array => $this->config)->call($client)['timeout'])->toBe(30);
        });

        it('refuses a client found that cannot take one, naming the way out', function () use ($finding) {
            $finding(new RecordingHttpClient)->withTimeout(30)->make();
        })->throws(InvalidArgumentException::class, 'withHttpClient()');

        it('leaves any client found as it stands without one', function () use ($finding, $httpClientOf) {
            $found = new RecordingHttpClient;

            expect($httpClientOf($finding($found)->make()))->toBe($found);
        });

        it('leaves a client it was given with the timeout it was given', function () {
            $given = new FollowingHttpClient;

            (new Factory)->withApiKey('a-key')->withHttpClient($given)->withTimeout(30)->withRequestFactory(new HttpFactory)->withStreamFactory(new HttpFactory)->make();

            expect($given->options)->toBe([]);
        });

        it('is at least a second', function () {
            (new Factory)->withTimeout(0);
        })->throws(InvalidArgumentException::class, 'at least one second');
    });

    it('refuses a key holding what a header cannot carry, without quoting it', function (string $key) {
        try {
            (new Factory)->withApiKey($key);
            $this->fail('Nothing was thrown.');
        } catch (InvalidArgumentException $exception) {
            expect($exception->getMessage())->toContain('Settings > API Tokens')
                ->and(str_contains($exception->getMessage(), 'SECRET'))->toBeFalse();
        }
    })->with([
        'a line break' => ["SECRET\n123"],
        'a space' => ['SECRET 123'],
        'a tab' => ["SECRET\t123"],
    ]);

    it('takes a key shaped as the API issues them, trimmed', function () {
        $http = new RecordingHttpClient([jsonResponse(['data' => []])]);

        factoryAnswering($http)->withApiKey(" 12|AbCdEf0123\n")->make()->transporter()->send(ApiRequest::get('account'));

        expect($http->requests[0]->getHeaderLine('Authorization'))->toBe('Bearer 12|AbCdEf0123');
    });

    it('refuses to make a client without a key', function (?string $key) {
        $factory = (new Factory)->withHttpClient(new RecordingHttpClient);

        if ($key !== null) {
            $factory->withApiKey($key);
        }

        expect(fn () => $factory->make())->toThrow(InvalidArgumentException::class, 'API key is required');
    })->with([[null], [''], ['  ']]);

    it('takes a base address without its trailing slash and refuses anything but http', function () {
        $http = new RecordingHttpClient([jsonResponse(['data' => []])]);

        factoryAnswering($http)->withBaseUri('https://staging.example.com/api/v1/')->make()->transporter()->send(ApiRequest::get('account'));

        expect((string) $http->requests[0]->getUri())->toBe('https://staging.example.com/api/v1/account')
            ->and(fn () => factoryAnswering($http)->withBaseUri('ftp://example.com'))->toThrow(InvalidArgumentException::class, 'http or https');
    });

    it('declares only a version the API keeps', function (?string $installed, string $declared) {
        expect(ClientIdentifier::version($installed))->toBe($declared);
    })->with([
        ['1.2.3', '1.2.3'],
        ['v1.2.3', '1.2.3'],
        ['1.2.3-beta.1', '1.2.3-beta.1'],
        ['1.2.3-toolongatag', '0.0.0'],
        ['dev-main', '0.0.0'],
        ['1.x-dev', '0.0.0'],
        [null, '0.0.0'],
    ]);

    it('declares this package by default', function () {
        expect(ClientIdentifier::for('php', 'mozex/no-such-package'))->toBe('php/0.0.0')
            ->and(ClientIdentifier::for('php', 'mozex/iminify-php'))->toMatch('/\Aphp\/\d+\.\d+\.\d+(?:-[0-9A-Za-z.]{1,8})?\z/');
    });

    it('declares another client for a package built on this one, and sends extra headers', function () {
        $http = new RecordingHttpClient([jsonResponse(['data' => []])]);

        factoryAnswering($http)->withClientIdentifier('laravel/1.2.3')->withHttpHeader('X-Extra', 'yes')->make()->transporter()->send(ApiRequest::get('account'));

        expect($http->requests[0]->getHeaderLine('Iminify-Client'))->toBe('laravel/1.2.3')
            ->and($http->requests[0]->getHeaderLine('X-Extra'))->toBe('yes');
    });
});
