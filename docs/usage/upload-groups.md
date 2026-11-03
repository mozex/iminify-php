---
title: Upload groups
weight: 2
---

An upload group is one image and every size and format you keep of it, the way a site keeps a thumbnail, a medium and a large copy of each upload. You describe the image and the outputs you want, send the image once, and Iminify makes every output from it. The whole group counts as one image, and each output in each format as one file against a daily allowance of files. The rules on outputs are in the API's [upload groups guide](https://www.iminify.com/docs/upload-groups).

To compress one file as it is, send it as an [image](./images.md) instead.

## Opening a group

`create()` takes the API's parameters as they are. Every limit is checked here, before the file travels, and a group past any of them is refused whole with nothing counted.

```php
use Mozex\Iminify\Enums\CropAnchor;
use Mozex\Iminify\Enums\GroupFormat;
use Mozex\Iminify\Http\Upload;

$upload = Upload::fromPath('photos/team.jpg');

$group = $client->groups()->create([
    'name' => 'team',
    'source' => ['format' => 'jpg', 'size' => $upload->size(), 'width' => 2560, 'height' => 1707],
    'outputs' => [
        ['name' => 'full', 'width' => 2560, 'height' => 1707],
        ['name' => 'medium', 'width' => 300, 'height' => 200],
        ['name' => 'thumbnail', 'width' => 150, 'height' => 150, 'crop' => [CropAnchor::Center, CropAnchor::Center]],
    ],
    'formats' => [GroupFormat::Original, GroupFormat::Webp],
    'ephemeral' => true,
]);
```

`source` has to match the file you send exactly. `Upload::size()` gives you the bytes. The `width` and `height` are yours to work out, because the API wants them as displayed and `getimagesize()` gives them as stored: a phone photo stored on its side has them swapped, and the group would refuse it.

`ephemeral`, `priority`, `level`, `keep_metadata`, `alt_text` and `alt_text_language` work as they do on an [image](./images.md).

## Sending the image

```php
$group = $client->groups()->sendSource($group, $upload);
```

It also takes a path, a stream or a PSR-7 stream, like `images()->upload()`. A different file from the one declared is refused with a `ValidationException`, and the group still takes the right one until `$group->uploadBy`. Past that it reads `expired`, and the image and files it counted are given back.

## Waiting and downloading

```php
$group = $client->groups()->wait($group);

foreach ($group->outputs as $output) {
    echo "{$output->name} {$output->format}: {$output->status->value}, {$output->size} bytes\n";
}

$client->groups()->download($group)->saveTo('photos/team.zip');
$client->groups()->delete($group);
```

`wait()` reads only while the group is `in_progress`, so on a group still waiting for its image it returns at once: nothing but `sendSource()` moves it on.

The zip holds every output marked `replace`, meaning smaller than the size you told it you already have, with a `manifest.csv` of every output. `$group->isInProgress()` and `$group->isFinished()` say where it stands, and `$client->groups()->get($id)` reads one by its id.
