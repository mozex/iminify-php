---
title: Account
weight: 6
---

`$client->account()->get()` reads the key's account: its plan, what the plan allows, and how much of today's allowance is gone.

```php
use Mozex\Iminify\Enums\Plan;

$account = $client->account()->get();

$account->plan === Plan::Pro;
$account->planEndsAt;                  // when a paid plan or a trial ends, null while it renews
$account->limits->imagesPerDay;
$account->limits->maxFileSize;
$account->usage->images->used;
$account->usage->images->remaining;
$account->usage->images->resetsAt;     // a rolling 24 hours from the first use
```

The usage has `images`, `scans`, `crawls`, `altTexts` and `files`, each a `Usage`.

**A limit that reads null is unlimited, never zero.** Some plans have no daily limit on images or page scans, so ask the `Usage` rather than comparing the number:

```php
if ($account->usage->images->isSpent()) {
    // nothing left until resetsAt
}

$account->usage->images->isUnlimited();
```

`isSpent()` is false on an unlimited plan, where `remaining` is null.
