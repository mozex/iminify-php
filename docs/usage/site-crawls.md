---
title: Site crawls
weight: 4
---

A site crawl scans several pages of one site at once, with one report and one zip for the whole site. It comes with a paid plan. Everything here goes through `$client->crawls()`.

Start from a sitemap (an XML sitemap, a sitemap index or a text file with one address a line), or from a list of pages on one site:

```php
$crawl = $client->crawls()->fromSitemap('https://example.com/sitemap.xml', ['format' => 'webp']);

$crawl = $client->crawls()->fromPages([
    'https://example.com/',
    'https://example.com/pricing',
]);
```

A crawl takes minutes rather than seconds, so its `wait()` defaults to 900 seconds where the others take 300.

```php
$crawl = $client->crawls()->wait($crawl);

echo "{$crawl->pagesCount} pages, {$crawl->pagesFailed} failed, saved {$crawl->savedPercent}%";
echo $crawl->audit?->imagesToFix;

$client->crawls()->download($crawl)->saveTo('site-images.zip');
```

Each page of a crawl is a page scan, so `$client->scans()->get($page->id)` reads one in full, report included:

```php
foreach ($crawl->pages ?? [] as $page) {
    echo "{$page->url}: {$page->status->value}, {$page->imagesToFix} to fix\n";
}
```

## Everything else

```php
$client->crawls()->list(['per_page' => 50]);
$client->crawls()->get($id);
$client->crawls()->delete($crawl);
```
