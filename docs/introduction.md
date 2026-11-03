---
title: Introduction
weight: 0
---

Iminify PHP is the PHP client for the [Iminify](https://www.iminify.com) image optimization API. It sends an image, waits for the result and saves the smaller file in a few lines of code, and it covers the rest of the API too: upload groups, page scans, site crawls, weekly site checks, your account and webhook signatures. It also ships an optimizer for Spatie's image packages.

## What you get

The API works in the background, so every long-running record has a `wait()` that backs off between reads, waits out a short rate limit and stops at the timeout you give it.

A refusal throws its own exception. A spent daily allowance throws `DailyLimitReachedException`, a short rate limit `RateLimitedException` with its `$retryAfter`, a bad field `ValidationException` with its `$errors`, and every one carries the API's error code.

A file goes up through a temporary stream and comes down the same way, so a large one doesn't have to fit in memory. A saved file lands in one step, so a broken transfer leaves whatever was at the path untouched.

For your own tests, `FakeClient` answers from a queue through the real resources, so your code reads the same objects and catches the same exceptions as it does in production. The client is built on PSR-18, so it uses Guzzle, Symfony's client or whatever your project already has.

## Installation

> **Requires [PHP 8.2+](https://php.net/releases/)** - see [all version requirements](https://mozex.dev/docs/iminify-php/v1/requirements)

```bash
composer require mozex/iminify-php
```

The client needs a PSR-18 HTTP client and PSR-17 factories, and finds what your project has. A project with neither needs one:

```bash
composer require guzzlehttp/guzzle
```

You also need an API key: make one under Settings, API Tokens on [www.iminify.com](https://www.iminify.com). The account needs a verified email address.

## Quick start

```php
use Mozex\Iminify\Iminify;

$client = Iminify::factory()
    ->withApiKey(getenv('IMINIFY_API_KEY'))
    ->withTimeout(300)
    ->make();

$image = $client->images()->upload('photos/team.jpg', ['format' => 'webp']);
$image = $client->images()->wait($image);

if ($image->hasResult()) {
    $client->images()->download($image)->saveTo('photos/team.webp');
}

echo "{$image->status->value}, saved {$image->savedPercent}%";
```

`Iminify::client($key)` is the short way to make a client, with no timeout on its requests. [Configuration](./reference/configuration.md) explains why you want one and what else you can set.

Compressions and scans count against the same daily and per-minute limits as the website. Your plan's are on the [pricing page](https://www.iminify.com/pricing), and [your account](./usage/account.md) tells you what's left today.

## Where to go next

- [Images](./usage/images.md) covers everything you can do with one image.
- [Upload groups](./usage/upload-groups.md) make every size and format a site keeps of an image, counted as one image.
- [Page scans](./usage/page-scans.md), [site crawls](./usage/site-crawls.md) and [weekly checks](./usage/weekly-checks.md) find and compress the images a site already serves.
- [Spatie's image packages](./integrations/spatie.md) get an optimizer that works in place of their command-line tools.
- [Error handling](./reference/error-handling.md) lists every exception, and [testing](./reference/testing.md) shows the fake.

The API's own reference, with every field and error code, is at [www.iminify.com/docs](https://www.iminify.com/docs).
