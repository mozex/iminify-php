---
title: Page scans
weight: 3
---

A page scan loads a web page in a real browser, finds every image it loads and compresses them all. It also writes a report of what the page received and how it used it: the largest contentful paint, oversized images, and images missing a width, height or alt text. Everything here goes through `$client->scans()`.

```php
$scan = $client->scans()->create('https://example.com/pricing', ['format' => 'webp', 'alt_text' => false]);
$scan = $client->scans()->wait($scan);

echo "{$scan->imagesFinished} of {$scan->imagesCount} images, saved {$scan->savedPercent}%";

$client->scans()->download($scan)->saveTo('pricing-images.zip');
```

`create()` takes `level`, `format`, `keep_metadata`, `width`, `height`, `scale`, `alt_text` and `alt_text_language`, which work as they do on an [image](./images.md).

A scan is done once it's no longer `queued` or `scanning` and none of its images are in progress, which is what `wait()` waits for and `$scan->isFinished()` asks. Its `until` takes a closure for anything more, such as `altTextsInProgress` reaching zero.

## The report

```php
$audit = $scan->audit;

$audit?->largestPaint?->url;
$audit?->findings;           // counts of each kind of problem
$audit?->oversizedBytes;

foreach ($audit?->images ?? [] as $image) {
    echo "{$image->url}: ".implode(', ', $image->findings)."\n";
}

foreach ($scan->skipped as $skipped) {
    echo "{$skipped->url}: {$skipped->reason}\n";
}
```

Downloads of the report and share links come with a paid plan:

```php
$client->scans()->downloadReportCsv($scan)->saveTo('report.csv');
$client->scans()->downloadReportPdf($scan)->saveTo('report.pdf');
$client->scans()->share($scan);    // its shareUrl
$client->scans()->unshare($scan);
```

## Everything else

```php
$client->scans()->list(['status' => 'completed', 'per_page' => 50]);
$client->scans()->get($id);
$client->scans()->retry($scan);
$client->scans()->cancel($scan);
$client->scans()->delete($scan);   // its images with it
```
