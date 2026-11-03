<?php

declare(strict_types=1);

namespace Mozex\Iminify\Resources;

use Closure;
use Mozex\Iminify\Exceptions\WaitTimeoutException;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Http\Upload;
use Mozex\Iminify\Responses\Download;
use Mozex\Iminify\Responses\Group;
use Psr\Http\Message\StreamInterface;

/**
 * Upload groups: one image and every size and format made from it, counted as one image, for
 * a site or an app that keeps several sizes of each upload. A call that takes a group takes
 * its id or the `Group` itself.
 */
class Groups extends ApiResource
{
    /**
     * Opens a group. Every limit is checked here, before the file travels.
     *
     * The parameters pass through as sent, so one the API adds works without a new release:
     * `name`, `source` (the image exactly as you will send it: `format`, `size` in bytes, which
     * `Upload::size()` gives, and `width` and `height` as displayed, so after any rotation
     * its metadata asks for), `outputs` (each a `name`, `width`, `height` and optional
     * `crop` of two `CropAnchor` values and `size`), `formats` (`GroupFormat` values), `level`,
     * `keep_metadata`, `alt_text`, `alt_text_language`, `ephemeral` and `priority`.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters): Group
    {
        return $this->group(ApiRequest::post('groups', $parameters));
    }

    /**
     * Sends the image the group was opened for. Pass a path, an open stream or a PSR-7 stream
     * (which also needs `$name`), or an `Upload` for bytes in memory. A file that differs from
     * what the group declared is refused, and the group still takes the right one until `uploadBy`.
     *
     * @param  string|resource|StreamInterface|Upload  $file
     */
    public function sendSource(string|Group $group, mixed $file, ?string $name = null): Group
    {
        return $this->group(ApiRequest::upload($this->path($group).'/source', $this->toUpload($file, $name)));
    }

    public function get(string|Group $group): Group
    {
        return $this->group(ApiRequest::get($this->path($group)));
    }

    /**
     * Reads the group while its files are being made, and returns it. Pass `$until` to wait for
     * more once it has finished, such as its alt text; a group that didn't finish ends the wait.
     *
     * `$timeout` is checked between reads, so give the HTTP client a timeout of its own.
     *
     * @param  int  $timeout  in seconds
     * @param  (Closure(Group): bool)|null  $until
     *
     * @throws WaitTimeoutException
     */
    public function wait(string|Group $group, int $timeout = 300, ?Closure $until = null): Group
    {
        return $this->poll(
            fn (): Group => $this->get($group),
            fn (Group $current): bool => ! $current->isInProgress() && ($until === null || ! $current->isFinished() || $until($current)),
            $timeout,
            'The group',
            $group instanceof Group ? $group : null,
        );
    }

    /**
     * Downloads every file marked `replace` as one zip, with a `manifest.csv` of every output.
     */
    public function download(string|Group $group): Download
    {
        return $this->file(ApiRequest::get($this->path($group).'/download'));
    }

    public function delete(string|Group $group): void
    {
        $this->transporter->send(ApiRequest::delete($this->path($group)));
    }

    protected function group(ApiRequest $request): Group
    {
        return $this->record($request, Group::from(...));
    }

    protected function path(string|Group $group): string
    {
        return 'groups/'.ApiRequest::segment($group instanceof Group ? $group->id : $group);
    }
}
