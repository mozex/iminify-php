---
title: Webhooks
weight: 7
---

Webhooks come with a paid plan and are set up on the website, under Settings, Webhooks: Iminify sends a signed request to your address when an image or a page scan finishes, so you don't have to poll. `WebhookVerifier` checks that a request came from Iminify and reads its event.

Hand it the raw request body, before any JSON parsing changes it, and the `Iminify-Signature` header:

```php
use Mozex\Iminify\Enums\WebhookEventType;
use Mozex\Iminify\Exceptions\InvalidWebhookSignatureException;
use Mozex\Iminify\Webhooks\WebhookVerifier;

$verifier = new WebhookVerifier(getenv('IMINIFY_WEBHOOK_SECRET'));

try {
    $event = $verifier->verify(file_get_contents('php://input'), $_SERVER['HTTP_IMINIFY_SIGNATURE'] ?? null);
} catch (InvalidWebhookSignatureException) {
    http_response_code(400);

    return;
}

if ($event->type === WebhookEventType::ImageCompleted) {
    echo $event->image()?->optimized?->downloadUrl;
}
```

The secret is the whole value Settings shows when you save the address, with its `whsec_`. A request whose time is more than five minutes from your server's clock is refused, which stops an old request being replayed. The second constructor argument changes that tolerance, in seconds.

`$event->type` is a `WebhookEventType`: `ImageCompleted`, `ImageFailed`, `ImageResponsiveFinished`, `ScanCompleted`, `ScanFailed` or `Ping`, or `Unknown` for an event this version doesn't know yet. `$event->image()` and `$event->scan()` read the record it carries, and `$event->data` has it as sent. `$event->id` stays the same when a failed send is sent again, so you can skip an event you've already handled.

To answer the request yourself rather than catching, use `isValid()`:

```php
if (! $verifier->isValid($payload, $signature)) {
    http_response_code(400);
}
```

## Testing your endpoint

`sign()` makes the header a real request would carry, for a test that posts to your own endpoint:

```php
use Mozex\Iminify\Webhooks\WebhookVerifier;

$verifier = new WebhookVerifier('whsec_test');
$signature = $verifier->sign($payload);
```
