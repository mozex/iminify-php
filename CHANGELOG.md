# Changelog

All notable changes to `iminify-php` will be documented in this file.

## 1.0.0 - 2026-11-04

### What's Changed

The first release of the PHP client for the [Iminify](https://www.iminify.com) image optimization API.

* Upload an image from a path, a stream or a web address, wait for it with a back-off that respects `Retry-After`, and save the result to a path or a stream
* Upload groups: send an image once and get back every size and format a site keeps of it, as one zip with a manifest
* Page scans with their reports, site crawls, weekly site checks, and your account's limits and usage
* An exception for each kind of refusal, carrying the API's error code: `DailyLimitReachedException`, `RateLimitedException` with its `$retryAfter`, `ValidationException` with its `$errors`
* `WebhookVerifier` checks the signature on Iminify's webhook deliveries and reads the event
* `FakeClient` and `Fixtures` for your own tests, answering through the real resources
* `IminifyOptimizer` for spatie/image-optimizer, so a chain, `spatie/image` or Browsershot compresses through Iminify with no binaries on the server
* Runs on PHP 8.2 or newer with any PSR-18 HTTP client, Guzzle and Symfony's included

The docs are at https://www.iminify.com/docs/php.

**Full Changelog**: https://github.com/mozex/iminify-php/commits/1.0.0
