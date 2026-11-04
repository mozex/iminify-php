# iminify-php

The PHP client for the Iminify image optimization API at `https://www.iminify.com/api/v1`, for anyone calling it from PHP: every operation of its public document, a wait helper, typed exceptions, a webhook verifier, a fake for tests, and an optimizer for Spatie's image packages. The Laravel wrapper, `mozex/iminify-laravel`, builds on it.

## Commands

```bash
composer test                  # lint + static analysis + type coverage + tests, the gate before every commit
composer lint                  # fix code style (Pint)
composer test:lint             # check code style without fixing
composer test:types            # PHPStan
composer test:type-coverage    # Pest type coverage (minimum 100%)
composer test:unit             # Pest

vendor/bin/pest tests/SomeTest.php          # one file
vendor/bin/pest --filter="test name here"   # one test
```

## Architecture

- `Iminify` makes clients, and `Factory` is the fluent way to set one up: key, base address, timeout, HTTP client, PSR-17 factories, headers and the sleep a wait calls.
- `Client` hands out one resource per area: `images()`, `groups()`, `scans()`, `crawls()`, `monitors()`, `account()`. `transporter()` is the way to the API for a call with no method yet.
- `Contracts\TransporterContract` is the one seam: `send(ApiRequest): ApiResponse`. `Http\HttpTransporter` is the real one on PSR-18, and `Testing\FakeClient` is both a `Client` and its own transporter.
- `Http\`: `ApiRequest` (one call, enums turned into their values, nulls dropped), `ApiResponse` (status, headers and a lazy body, `successful()` is the one place status handling lives), `Upload` (a path, bytes or a stream under a name).
- `Resources\`: each extends `ApiResource`, whose `poll()` is the back-off behind every `wait()` and whose `record()`, `page()`, `records()` and `file()` read answers.
- `Responses\`: read-only objects with `from()`. A required field is read with `?? null`, so one missing reaches a typed property as a `TypeError`, which `ApiResource::parse()` turns into `UnexpectedResponseException`. `Support\Read` reads dates and format rows strictly.
- `Enums\`, `Exceptions\`, `Webhooks\` (`WebhookVerifier`, `WebhookEvent`), `Support\ClientIdentifier` (the `Iminify-Client` header).
- `Spatie\IminifyOptimizer` is loaded only when someone names it, since `spatie/image-optimizer` is optional.

## Key design decisions

- **One exception class per kind of refusal**, under `ApiException`, not one per package: a caller's `catch` is the point of them, and `$errorCode` holds the API's code for anything finer.
- **Status enums have an `Unknown` case**, and `ImageStatus::isFinished()` is the API's own rule, so a status added later ends a wait rather than failing a read or running out a timeout. `toArray()` hands back the answer as sent.
- **What a response reads as an enum is only a status, the plan and a webhook's type.** What a report describes (a skip reason, a finding, an outcome, a group's output format) stays a documented string, because nothing branches on it for control and a new value then needs no release. The other enums are for what you send.
- **Options pass through as sent**, so a field the API adds works without a release. Don't validate them here.
- **The fake sits at the transporter**, recording requests and answering through the real resources, so there are no per-resource contracts or doubles. It's named `FakeClient` because test doubles here start with `Fake`.
- **`Testing\Fixtures` holds the API document's examples as literal arrays in `src/`**, since `tests/` doesn't ship. `FixturesTest` pins them to the document.
- **`Groups::create()` never works out a source's `width` and `height`.** The API wants them as displayed, `getimagesize()` gives them as stored, and a sideways phone photo would be refused. `Upload::size()` gives the one part the client can know.
- **A group's `wait()` reads only while it's `in_progress`**, the document's own words, so on `awaiting_source` it returns at once: only `sendSource()` moves it on. `Crawls::wait()` defaults to 900 seconds because a crawl takes minutes.
- **Multipart bodies are built on `php://temp`** through the PSR-17 stream factory, with no multipart library, so a large file costs a second write to disk and no memory.
- **Redirects are refused, and a client found has them turned off.** Following one can turn a POST into a GET that reads as a success. Discovery prefers Symfony's client, which follows them unless told not to.
- **`withTimeout()` knows Symfony's client and Guzzle, and refuses any other client found.** PSR-18 has no timeout, and a wait's deadline stops only between reads, so a client with none would block for good.
- **The optimizer keeps no client in a property.** A queue serializes the chain and a client can't be, so it makes one per file through `client()`, which subclasses override. An `api_key` in its options travels in a queued payload, which is why overriding `client()` is the documented way on a queue.
- **The optimizer names an upload `image.<ext>` from the file's type**, never its path: a temporary upload has no extension, and a long name would be refused.
- **The optimizer writes back only a smaller copy, of the same type by its bytes, of a file the API received whole and that hasn't changed since.** This runs over a user's own file, so `Download::saveTo()`'s `$check` makes the last two checks on the finished temporary file: a client that streams reads the body only then. Its time comes from `Deadline`, which counts what was slept as `poll()` does, so a `pause()` that returns at once can't spin. Clean-up never replaces the exception that caused it.

## Extension points

- `Factory::withHttpClient()` and `withBaseUri()` for another client or address.
- `IminifyOptimizer::client()` for a client of your own, `pause()` for what a rate-limit wait calls, and `uploadOptions()` for what it sends.
- `Client::transporter()` for an endpoint this client has no method for.
- `Support\ClientIdentifier` and `Factory::withClientIdentifier()` for a package built on this one.

## Testing

- `tests/Fakes/RecordingHttpClient` answers from a queue and keeps PSR-7 requests, for what reaches the wire. `Testing\FakeClient` answers through the real resources. `tests/Fakes/FakeOptimizer` is the optimizer on a given client, with its waits recorded rather than slept.
- `tests/Fixtures/openapi.json` is a copy of the API's published document. `OperationsTest` holds every operation to the client in both directions, and each field's property and nullability to the schema; `FixturesTest` holds `Testing\Fixtures` to its examples. Both fail when the document moves, so the copy and the code are refreshed together. A later API change reaches the copy only by that refresh.
- The spatie/image test needs GD, which CI's PHP has. On PHP below 8.4 Composer resolves spatie/image 3.9.7, the last release for them.
- Permission and symlink tests skip themselves on Windows and run on CI.
- Tests write only to the system's temporary folder, and remove what they made. `downloadFolder()` gives a folder to remove in `afterEach`. They never reach the network.

## Development notes

- `CHANGELOG.md` is generated from GitHub releases by the changelog workflow. Never edit it by hand.
- The PHP floor is 8.2: no typed class constants (`public const string X`) and no `new Foo()->bar()` without wrapping parentheses. PHPStan's `type_coverage.constant` is 0 for that reason.
- `spatie/image-optimizer` is only in `suggest`, `require-dev` and `conflict` (below 1.10.0, which has no self-handling optimizers), and `guzzlehttp/guzzle` is dev-only: discovery finds what the app has. `Factory` names Guzzle's client only behind an `instanceof` of something discovery already found. `ArchTest` scans `src/` so nothing else imports what a user may not have; Pest's own `toUse` can't see these names.
- A change to the docs is checked against the README and this file, and the reverse.
- The docs site, `https://www.iminify.com/docs/php`, reads `docs/` at each release, every page together, and refuses the whole release, keeping the pages it had, when one page breaks its check: front matter with `title` and `weight`, no `#` heading, no image, and every link to a docs page or heading on the site resolving (a relative `.md` link and an absolute `https://www.iminify.com/docs/...` link alike). So a docs change is tried with the site's dry run against `main` before the release is tagged.
