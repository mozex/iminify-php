---
title: Error handling
weight: 2
---

Everything this package throws while talking to Iminify extends `IminifyException`, so catching that handles them all. A refusal throws an `ApiException` or one of its subclasses, chosen by the API's error code and status.

| Exception | When |
|---|---|
| `AuthenticationException` | 401: a missing or wrong key |
| `AccessDeniedException` | 403: an unverified address or a banned account |
| `ProRequiredException` | 403: this takes a paid plan |
| `NotFoundException` | 404 |
| `ConflictException` | 409: the record isn't in a state that allows this, such as a download before it has finished |
| `PayloadTooLargeException` | 413 |
| `ValidationException` | 422: a field was refused |
| `RateLimitedException` | 429: too many requests or compressions this minute |
| `DailyLimitReachedException` | 429: the day's allowance is spent |
| `ServerException` | 500 and up |
| `ApiException` | Any other refusal |

Each one has these properties:

```php
use Mozex\Iminify\Exceptions\ApiException;

try {
    $image = $client->images()->upload('photos/team.jpg', ['size_limit' => 1]);
} catch (ApiException $exception) {
    $exception->status;        // 422
    $exception->errorCode;     // validation_failed
    $exception->getMessage();  // words meant for a person
    $exception->errors;        // on a validation failure, each field's messages
    $exception->retryAfter;    // the answer's Retry-After, in seconds
}
```

Branch on `$errorCode`, which stays stable, rather than on the message, which gets reworded. Every code is listed on the API's [errors page](https://www.iminify.com/docs/errors).

Three exceptions don't come from a refusal:

| Exception | When |
|---|---|
| `TransportException` | Iminify couldn't be reached, a request timed out, or a download broke off |
| `UnexpectedResponseException` | The answer isn't one the API gives: a redirect, a proxy's error page, or a body that doesn't read as expected |
| `WaitTimeoutException` | `wait()` ran out of time. `$last` holds the record as last read |

`InvalidWebhookSignatureException` comes from [verifying a webhook](../usage/webhooks.md).

## Rate limits

`wait()` honours a short rate limit by itself. Other calls throw `RateLimitedException`, and how long to wait is in `$retryAfter`:

```php
use Mozex\Iminify\Exceptions\DailyLimitReachedException;
use Mozex\Iminify\Exceptions\RateLimitedException;

try {
    $image = $client->images()->upload('photos/team.jpg');
} catch (RateLimitedException $exception) {
    sleep($exception->retryAfter ?? 5);
    $image = $client->images()->upload('photos/team.jpg');
} catch (DailyLimitReachedException $exception) {
    // Retry-After can be hours away, so tell someone rather than sleeping
}
```

Every record and list you get back says how you stand from the limit headers of its answer:

```php
$image->rateLimit->limit;
$image->rateLimit->remaining;
```

## A status this client doesn't know

Statuses are enums with an `Unknown` case for a value that arrived after this version was released. It doesn't throw: `ImageStatus::Unknown` counts as finished, so a `wait()` ends rather than running out its time, and `toArray()` hands you the answer as it was sent.
