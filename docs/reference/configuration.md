---
title: Configuration
weight: 1
---

`Iminify::client($key)` makes a client with everything at its default. `Iminify::factory()` gives you the rest:

```php
use Mozex\Iminify\Iminify;

$client = Iminify::factory()
    ->withApiKey(getenv('IMINIFY_API_KEY'))
    ->withTimeout(300)
    ->make();
```

| Method | What it does |
|---|---|
| `withApiKey($key)` | Required. Make a key under Settings, API Tokens |
| `withTimeout($seconds)` | The seconds one request may take, on a client the factory finds |
| `withHttpClient($client)` | Any PSR-18 client, set up as you need it |
| `withRequestFactory($factory)`, `withStreamFactory($factory)` | PSR-17 factories, found for you when left out |
| `withBaseUri($uri)` | Leave it out for the live API. It takes no query string or fragment |
| `withHttpHeader($name, $value)` | A header sent with every request. `Authorization`, `Accept` and `Iminify-Client` are the client's own, and refused here |
| `withClientIdentifier($identifier)` | What the `Iminify-Client` header says, for a package built on this one |
| `withSleep($closure)` | What waiting calls instead of `usleep()`, given the seconds to wait |

## Timeouts

PSR-18 has no timeout of its own, and a client found through discovery has none set. Without one, a stalled read blocks forever, and `wait()` can't help, because it only checks its clock between reads.

`withTimeout()` sets one on a client the factory finds. Symfony's client and Guzzle take it. Any other client found is refused rather than left without one, so set it up yourself and pass it through `withHttpClient()`. A client you pass keeps whatever you set on it, and `withTimeout()` doesn't touch it:

```php
use GuzzleHttp\Client as GuzzleClient;
use Mozex\Iminify\Iminify;

$client = Iminify::factory()
    ->withApiKey($key)
    ->withHttpClient(new GuzzleClient(['timeout' => 60]))
    ->make();
```

A large upload or download takes as long as your connection needs, so set the timeout with your largest file in mind.

## Redirects

The API is at `https://www.iminify.com/api/v1`, and the bare domain responds with a redirect. Following one can turn a POST into a GET that reads as a success, so the client treats any redirect as a failure and throws `UnexpectedResponseException` naming the address.

A client found through discovery has its redirects turned off. If you pass your own, don't let it follow them. Guzzle never does through PSR-18. Symfony's does unless you give it `['max_redirects' => 0]`.

## The Iminify-Client header

Every request says which client sent it, in an `Iminify-Client` header such as `php/1.2.0`. Iminify uses it only for its own figures on which clients are in use. It keeps the name `php` or `laravel` with a plain `x.y.z` version and at most a short tag, and quietly ignores anything else, so a header can't fail a request.

## A call this client doesn't have yet

Options and parameters pass through as sent, so a new field works without a release. For a whole new endpoint, `transporter()` is the way to the API itself:

```php
use Mozex\Iminify\Http\ApiRequest;

$response = $client->transporter()->send(ApiRequest::get('something-new', ['page' => 2]));

$response->json();
```

## Dumping a client

The key stays out of a `var_dump()` or `print_r()` of a client. Symfony's `dump()` and `var_export()` read the properties themselves, so don't dump one anywhere a log could keep it.
