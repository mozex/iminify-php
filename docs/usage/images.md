---
title: Images
weight: 1
---

An image is one compression: the file you sent, the settings you sent it with and, once it has finished, the optimized copy. Everything here goes through `$client->images()`. A call that takes an image takes its id or the `Image` itself.

## Sending an image

Pass a path, or have Iminify fetch a public address:

```php
$image = $client->images()->upload('photos/team.jpg');
$image = $client->images()->fromUrl('https://example.com/team.jpg');
```

An open stream or a PSR-7 stream works too, and needs a name, since the API reads the file's extension from it. Use `Upload::fromContents()` for bytes you already hold in memory, and `Upload::fromPath()` or `Upload::fromStream()` when you want the `Upload` itself:

```php
use Mozex\Iminify\Http\Upload;

$image = $client->images()->upload(fopen('photos/team.jpg', 'rb'), name: 'team.jpg');
$image = $client->images()->upload(Upload::fromContents($bytes, 'team.jpg'));
```

### Options

Options are the fields of the API's [`POST /images`](https://www.iminify.com/docs/images#create-image), and pass straight through, so one the API adds later works without a new release of this package. An enum is sent as its value, and a null as nothing.

```php
use Mozex\Iminify\Enums\CompressionLevel;
use Mozex\Iminify\Enums\OutputFormat;

$image = $client->images()->upload('photos/team.jpg', [
    'level' => CompressionLevel::Ultra,
    'format' => OutputFormat::Webp,
    'width' => 1600,
    'alt_text' => false,
]);
```

| Option | What it does |
|---|---|
| `level` | `none`, `lossless`, `ultra` or `smart`, the default |
| `format` | `auto`, `webp`, `avif`, `png` or `jpg`. Leave it out to keep the upload's format |
| `keep_metadata` | Keep the file's metadata, which is stripped by default |
| `width`, `height`, `scale` | Resize to a width, a height or both, or by a percentage. Nothing is cropped |
| `size_limit` | Fit the file under this many KB |
| `responsive_widths` | Make the image at these widths too, with an `<img srcset>` to copy (paid plans) |
| `folder` | The folder it goes in when you download several as a zip (paid plans) |
| `alt_text` | Write an AI alt text and suggest a file name. On by default, and it spends an alt text from your daily allowance |
| `alt_text_language` | The language to write it in |
| `use_suggested_name` | Name the download after the suggested file name |
| `ephemeral` | See [deleting images once saved](#deleting-images-once-saved) |
| `priority` | See [bulk work](#bulk-work) |

The API's [compression settings guide](https://www.iminify.com/docs/compression-settings) has what each one means, and which formats come back in another one.

## Waiting for the result

An upload returns at once, with the image `queued`. `wait()` reads it until it has finished, whether it completed, failed or was cancelled, and returns it. It backs off from one second to five between reads and waits out `Retry-After` when you're rate limited.

```php
$image = $client->images()->wait($image, timeout: 120);
```

If it runs out of time it throws `WaitTimeoutException`, whose `$last` holds the image as last read. The work carries on at Iminify, so asking again later finds it further along.

The timeout is checked between reads, so a read that stalls runs for as long as the HTTP client lets it. Give the client [a timeout of its own](../reference/configuration.md#timeouts).

To wait for more than the compression, such as the alt text, pass `until`. A failed or cancelled image has neither, and ends the wait all the same.

```php
use Mozex\Iminify\Enums\AltTextStatus;
use Mozex\Iminify\Responses\Image;

$image = $client->images()->wait($image, until: fn (Image $image): bool => $image->altText?->status !== AltTextStatus::Pending);

echo $image->altText?->text;
```

A webhook does the same job without polling. See [webhooks](./webhooks.md).

## Reading a result

`status` is an `ImageStatus`: `Queued`, `Optimizing`, `Completed`, `AlreadyOptimized`, `Failed` or `Cancelled`, or `Unknown` for a value this version of the client doesn't know yet.

```php
$image->isFinished();          // no longer queued or optimizing
$image->hasResult();           // a copy can be downloaded
$image->original->size;        // bytes
$image->optimized?->size;
$image->optimized?->format;    // jpg, webp, ...
$image->savedBytes;
$image->savedPercent;
$image->sizeLimitStatus;        // under, resized or over, when you sent a size_limit
$image->contentCredentials?->status;
$image->altText?->text;
$image->altText?->suggestedName;
$image->responsive?->html;     // the <img srcset> to copy
$image->toArray();             // the response as the API sent it
```

`AlreadyOptimized` means Iminify hands the upload back, because it couldn't make the file smaller or because the file is one Iminify made itself, so `hasResult()` is true and the download is your own file.

## Downloading

A download reads nothing until you ask for it, so a large file never has to fit in memory unless you call `contents()`.

```php
$download = $client->images()->download($image);

$download->saveTo('photos/team.webp');   // lands in one step, and returns the bytes written
$download->writeTo($openStream);
$download->contents();

$download->name;       // the name the API offers it under, which comes from outside your app
$download->mimeType;
$download->size;
```

Pass `DownloadType::Original` as the second argument to `download()` to get the file you sent back. `saveTo()` fails rather than saving a short file, and takes the size to hold it to as its second argument, such as `$image->optimized->size`, for a response that doesn't state its length. Its third argument is a closure given the finished temporary file's path before anything is replaced, which throws to keep what's at the path. It follows a link, and a file it replaces keeps its permissions but belongs from then on to the user PHP runs as.

For a responsive set, `downloadResponsive($image, 640)` gets one width and `downloadResponsiveZip($image)` gets them all. `downloadMany([$first, $second])` gets several images' optimized copies as one zip.

## Listing

A list comes one page at a time. Loop over the page for its records, and ask for `$nextPage` while it isn't null:

```php
$page = 1;

do {
    $images = $client->images()->list(['status' => 'failed', 'per_page' => 50, 'page' => $page]);

    foreach ($images as $image) {
        echo "{$image->name}\n";
    }

    $page = $images->nextPage;
} while ($page !== null);
```

`list()` also takes `source` and `scan`. The page has `$items`, `$total`, `$perPage`, `$currentPage` and `$lastPage`, and `toArray()` gives the response as sent.

## Everything else

```php
$client->images()->get($id);
$client->images()->rename($image, 'team-offsite');
$client->images()->renameMany([$first, $second], 'team-offsite');
$client->images()->recompress($image, ['level' => 'ultra']);   // a new image from the same original
$client->images()->retry($image);
$client->images()->cancel($image);                             // only while it's queued
$client->images()->writeAltText($image, ['alt_text_language' => 'de']);
$client->images()->share($image);                              // its shareUrl
$client->images()->unshare($image);
$client->images()->delete($image);
```

## Deleting images once saved

An image stays on your account until you delete it. A program that keeps its own copy of every result can send `ephemeral` with the upload, then delete the image once it has written the file. Anything you leave is [deleted for you](https://www.iminify.com/docs/images#deleting-images-once-saved) some hours after it finishes.

```php
$image = $client->images()->upload('photos/team.jpg', ['ephemeral' => true]);
$image = $client->images()->wait($image);

if ($image->hasResult()) {
    $client->images()->download($image)->saveTo('photos/team.jpg');
}

$client->images()->delete($image);
```

## Bulk work

Send `priority` as `Priority::Background` for work nobody is waiting on, such as a whole library. It runs after every image sent without it and counts against your limits the same.

```php
use Mozex\Iminify\Enums\Priority;

$image = $client->images()->upload('photos/team.jpg', ['priority' => Priority::Background]);
```
