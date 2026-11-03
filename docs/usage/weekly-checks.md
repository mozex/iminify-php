---
title: Weekly checks
weight: 5
---

A weekly check crawls a site every week and emails you what it found. It comes with a paid plan, and you make one from a crawl you already ran. Nothing runs and nothing is counted until its first run, a week later. Everything here goes through `$client->monitors()`.

```php
$monitor = $client->monitors()->create($crawl);

echo $monitor->nextRunAt->format('c');
```

Making one from a crawl that already has a check hands back that check rather than making another.

```php
$client->monitors()->list();                                 // every check, oldest first, never paged
$client->monitors()->get($id);
$client->monitors()->update($monitor, ['emails' => false]);  // keeps it running without the emails
$client->monitors()->delete($monitor);

$monitor->lastRun?->savedPercent;                            // the crawl it last ran
```
