---
title: Spatie's image packages
weight: 1
---

`Mozex\Iminify\Spatie\IminifyOptimizer` is an optimizer for [spatie/image-optimizer](https://github.com/spatie/image-optimizer)'s chain, which normally runs command-line tools such as jpegoptim. It compresses each file through Iminify instead, so the server needs no binaries, and writes the smaller copy back over it. It needs `spatie/image-optimizer` 1.10 or newer, which this package doesn't install for you.

```bash
composer require spatie/image-optimizer
```

## In a chain

```php
use Mozex\Iminify\Spatie\IminifyOptimizer;
use Spatie\ImageOptimizer\OptimizerChain;

$chain = (new OptimizerChain)->addOptimizer(new IminifyOptimizer([
    'api_key' => getenv('IMINIFY_API_KEY'),
]));

$chain->optimize('photos/team.jpg');
```

`$chain->optimize($from, $to)` leaves the first file alone and writes the result to the second, as it does with any optimizer.

Don't build it with `OptimizerChainFactory::create([IminifyOptimizer::class => [...]])`. The factory looks for one of its own optimizers in the list and, finding none, silently hands back its default command-line chain. List a built-in beside it, such as `Svgo::class => []`, or build the chain as above.

## With spatie/image and Browsershot

Both take a chain, and Browsershot needs `spatie/image` installed to use it:

```php
use Spatie\Browsershot\Browsershot;
use Spatie\Image\Image;

Image::load('photos/team.jpg')->width(1200)->optimize($chain)->save('photos/team-1200.jpg');

Browsershot::url('https://example.com')->optimize($chain)->save('shot.png');
```

Calling `optimize()` with no chain runs the default command-line tools.

## Options

| Option | What it does |
|---|---|
| `api_key` | Your API key |
| `timeout` | How long it waits for a file to be compressed, and the limit on each request to Iminify, in seconds up to a day. 120 by default. Each request has its own limit, so if every one stalled a file could take about four times this. The chain's own `setTimeout()` doesn't reach an optimizer like this one |
| `level` | The [compression level](../usage/images.md#options). `smart` by default |
| `keep_metadata` | Keep the file's metadata, which is stripped by default |

## What it does to a file

It takes JPEG, PNG, WebP and GIF files, read by what the file is rather than by its name, so a temporary upload with no extension works. Each comes back in its own format, an animated GIF still animated. It leaves AVIF, SVG, HEIC and TIFF to whatever else is in the chain, since Iminify doesn't read the first two and always turns the others into another format.

A chain can hold `Avifenc` and `Svgo` beside it. Leave out `Jpegoptim`, `Pngquant`, `Optipng`, `Gifsicle` and `Cwebp`, or the same file goes through both.

It sends the file with alt text off, then writes the result back only if it completed, kept its format and came out smaller, only if what arrived is an image of that type, and only if the API received the whole file and yours hasn't changed since. Each file counts as one image against your daily and per-minute limits. A file Iminify can't shrink stays as it is.

The new copy replaces the file in one step, so it keeps the file's permissions but belongs from then on to the user PHP runs as.

The image doesn't stay on your account. It's sent as `ephemeral` and deleted once the file is written, and Iminify [deletes one the optimizer couldn't](https://www.iminify.com/docs/images#deleting-images-once-saved), such as one still being compressed at the deadline, on its own schedule.

## When something goes wrong

The optimizer throws, and the file stays as it was. By default the chain catches that and logs it to a logger you haven't set, so a missing key or a spent daily allowance leaves every file unoptimized without a word. Give it a logger, and have it rethrow if you'd rather stop:

```php
use Mozex\Iminify\Spatie\IminifyOptimizer;
use Spatie\ImageOptimizer\OptimizerChain;

$chain = (new OptimizerChain)
    ->useLogger($logger)
    ->throws()
    ->addOptimizer(new IminifyOptimizer(['api_key' => $key]));
```

A short rate limit, on the upload or the download, is waited out if it fits inside `timeout`. A spent daily allowance fails at once, because it can last hours.

A chain runs synchronously, so whatever calls it waits for the upload, the compression and the download: seconds for most files, and up to `timeout` when Iminify's queue is busy. Run it from a queued job rather than a web request where you can.

## Using your own client

The optimizer makes a client for each file, with `timeout` as its request timeout. That client comes from discovery, which [refuses a client it can't set a timeout on](../reference/configuration.md#timeouts). To hand it your own, extend it:

```php
use Mozex\Iminify\Client;
use Mozex\Iminify\Spatie\IminifyOptimizer;

class MyOptimizer extends IminifyOptimizer
{
    protected function client(): Client
    {
        return MyContainer::get(Client::class);
    }
}
```

This is also how to fake it in your tests, along with `pause()`, which is what a rate-limit wait calls. And it's what to do when a chain goes on a queue. A queue serializes the chain, so the optimizer keeps no client in a property, and an `api_key` in its options travels in the job's payload. Overriding `client()` keeps the key out of it.
