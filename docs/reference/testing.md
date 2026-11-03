---
title: Testing
weight: 3
---

`FakeClient` stands in for the client in your tests. The real resources read answers you queue instead of calling the API, so a response reads, and a refusal throws, as it would for real. Every call is kept to assert on, and waiting never sleeps. The assertions need PHPUnit, which a Pest or PHPUnit suite already has.

```php
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Testing\FakeClient;
use Mozex\Iminify\Testing\Fixtures;

$client = new FakeClient([
    Fixtures::queuedImage(),
    Fixtures::image(['id' => 'abc', 'saved_percent' => 42.5]),
    Fixtures::file('the bytes'),
]);

// the code under test, given $client
$image = $client->images()->upload('photos/team.jpg', ['format' => 'webp']);
$image = $client->images()->wait($image);
$client->images()->download($image)->saveTo('photos/team.webp');

$client->assertSent('POST', 'images', fn (ApiRequest $request): bool => $request->fields['format'] === 'webp');
$client->assertSentTimes('GET', 'images/abc/download', 1);
$client->assertNotSent('DELETE', 'images/*');
```

`FakeClient` is a `Client`, so wherever your code takes one you can pass it. A `*` in a path stands for one part of it, so `images/*` matches `images/abc` and not `images/abc/cancel`. `requests()` gives every call sent, oldest first, `sent()` the ones to an address, and `assertNothingSent()` checks there were none.

## What you can queue

Responses go in the order the calls will ask for them, in the constructor or through `addAnswers()`. Each is one of:

- an array: the record, sent under `data`, such as `Fixtures::image()`. An array that already has a `data` key is the whole response, such as `Fixtures::page([...])`.
- an `ApiResponse`: sent as it stands, such as `Fixtures::file()`, `Fixtures::noContent()` or `Fixtures::refusal('daily_limit_reached', 429)`.
- an exception: thrown, such as a `TransportException` for a lost connection.

A call with nothing queued throws a `LogicException` naming the call.

## Fixtures

Each fixture but `maxAccount()`, which the document has no example of, is the example from the API's own document, so pass only the fields your test cares about. What you pass replaces that field whole.

| Fixture | What it is |
|---|---|
| `image()`, `queuedImage()` | A finished image, and one just sent |
| `group()`, `openGroup()`, `groupInProgress()` | A finished upload group, one waiting for its image, and one being made |
| `scan()`, `queuedScan()` | A finished page scan, and one just sent |
| `crawl()`, `queuedCrawl()` | A finished site crawl, and one just sent |
| `monitor()`, `newMonitor()` | A weekly check that has run, and a new one |
| `account()`, `maxAccount()` | A free account, and a Max account whose daily limits read null |
| `page($records)` | One page of a list |
| `file()`, `noContent()` | A download, and a delete's response |
| `refusal($code, $status)` | A refusal, ending in the exception the real one would |
| `answer($body, $status, $headers)` | Any JSON response, for the limit headers or a status the others don't give |

To read one without a client, pass it to its response class: `Image::from(Fixtures::image())`.

## Webhooks

`WebhookVerifier::sign()` makes the header for a request to your own endpoint. See [webhooks](../usage/webhooks.md#testing-your-endpoint).

## The optimizer

To test code that runs [the Spatie optimizer](../integrations/spatie.md#using-your-own-client), extend it, have `client()` return a `FakeClient`, and have `pause()` do nothing, since it sleeps for real. Cleaning up swallows whatever goes wrong, a missing answer included, so assert on `requests()` rather than expecting a `LogicException`.
