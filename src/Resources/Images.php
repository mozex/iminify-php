<?php

declare(strict_types=1);

namespace Mozex\Iminify\Resources;

use Closure;
use InvalidArgumentException;
use Mozex\Iminify\Enums\DownloadType;
use Mozex\Iminify\Exceptions\WaitTimeoutException;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Http\Upload;
use Mozex\Iminify\Responses\Download;
use Mozex\Iminify\Responses\Image;
use Mozex\Iminify\Responses\Page;
use Psr\Http\Message\StreamInterface;

/**
 * Every call on images. A call that takes an image takes its id or the `Image` itself.
 *
 * The options an upload takes pass through as sent, so one the API adds works without a new
 * release: `level`, `format`, `keep_metadata`, `width`, `height`, `scale`, `size_limit`,
 * `responsive_widths`, `folder`, `alt_text`, `alt_text_language`, `use_suggested_name`,
 * `ephemeral` and `priority`. An enum value is sent as its value, and a null as nothing.
 */
class Images extends ApiResource
{
    /**
     * @param  array<string, mixed>  $query  `status`, `source`, `scan`, `per_page`, `page`
     * @return Page<Image>
     */
    public function list(array $query = []): Page
    {
        $response = $this->transporter->send(ApiRequest::get('images', $query));

        return $this->parse(fn (): Page => Page::from($response->json(), fn (array $image): Image => Image::from($image), $response->rateLimit()));
    }

    /**
     * Sends a file to be compressed. Pass a path, an open stream or a PSR-7 stream (which
     * also needs `$name`, since the API reads the file's extension), or an `Upload` for bytes in memory.
     *
     * @param  string|resource|StreamInterface|Upload  $file
     * @param  array<string, mixed>  $options
     */
    public function upload(mixed $file, array $options = [], ?string $name = null): Image
    {
        return $this->image(ApiRequest::upload('images', $this->toUpload($file, $name), $options));
    }

    /**
     * Has Iminify fetch the image from a public address and compress it.
     *
     * @param  array<string, mixed>  $options
     */
    public function fromUrl(string $url, array $options = []): Image
    {
        return $this->image(ApiRequest::post('images', ['url' => $url] + $options));
    }

    public function get(string|Image $image): Image
    {
        return $this->image(ApiRequest::get($this->path($image)));
    }

    /**
     * Reads the image until it has finished, whether it completed, failed or was cancelled, and
     * returns it. Pass `$until` to wait for more once it has a result, such as its alt text or
     * responsive sizes; a failed or cancelled image has none, and ends the wait all the same.
     *
     * `$timeout` is checked between reads, so a read that stalls runs for as long as the HTTP
     * client lets it: give the client a timeout of its own.
     *
     * @param  int  $timeout  in seconds
     * @param  (Closure(Image): bool)|null  $until
     *
     * @throws WaitTimeoutException
     */
    public function wait(string|Image $image, int $timeout = 300, ?Closure $until = null): Image
    {
        return $this->poll(
            fn (): Image => $this->get($image),
            fn (Image $current): bool => $current->isFinished() && ($until === null || ! $current->hasResult() || $until($current)),
            $timeout,
            'The image',
            $image instanceof Image ? $image : null,
        );
    }

    public function rename(string|Image $image, string $name): Image
    {
        return $this->image(ApiRequest::patch($this->path($image), ['name' => $name]));
    }

    public function delete(string|Image $image): void
    {
        $this->transporter->send(ApiRequest::delete($this->path($image)));
    }

    public function download(string|Image $image, DownloadType|string $type = DownloadType::Optimized): Download
    {
        return $this->file(ApiRequest::get($this->path($image).'/download', ['type' => $type]));
    }

    public function downloadResponsive(string|Image $image, int $width): Download
    {
        return $this->file(ApiRequest::get($this->path($image)."/responsive/{$width}"));
    }

    public function downloadResponsiveZip(string|Image $image): Download
    {
        return $this->file(ApiRequest::get($this->path($image).'/responsive.zip'));
    }

    /**
     * Compresses a finished image again as a new image, with other settings.
     *
     * @param  array<string, mixed>  $options
     */
    public function recompress(string|Image $image, array $options = []): Image
    {
        return $this->image(ApiRequest::post($this->path($image).'/recompress', $options));
    }

    public function retry(string|Image $image): Image
    {
        return $this->image(ApiRequest::post($this->path($image).'/retry'));
    }

    public function cancel(string|Image $image): Image
    {
        return $this->image(ApiRequest::post($this->path($image).'/cancel'));
    }

    /**
     * @param  array<string, mixed>  $options  `alt_text_language`, `use_suggested_name`
     */
    public function writeAltText(string|Image $image, array $options = []): Image
    {
        return $this->image(ApiRequest::post($this->path($image).'/alt-text', $options));
    }

    public function share(string|Image $image): Image
    {
        return $this->image(ApiRequest::post($this->path($image).'/share'));
    }

    public function unshare(string|Image $image): void
    {
        $this->transporter->send(ApiRequest::delete($this->path($image).'/share'));
    }

    /**
     * Renames several images at once under one name, numbered by the API.
     *
     * @param  array<array-key, string|Image>  $images
     * @return list<Image>
     */
    public function renameMany(array $images, string $name): array
    {
        $response = $this->transporter->send(ApiRequest::post('images/rename', ['ids' => $this->ids($images), 'name' => $name]));

        return $this->parse(fn (): array => array_values(array_map(fn (array $image): Image => Image::from($image, $response->rateLimit()), $response->data())));
    }

    /**
     * Downloads several images' optimized copies as one zip.
     *
     * @param  array<array-key, string|Image>  $images
     */
    public function downloadMany(array $images): Download
    {
        return $this->file(ApiRequest::post('images/download', ['ids' => $this->ids($images)]));
    }

    protected function image(ApiRequest $request): Image
    {
        $response = $this->transporter->send($request);

        return $this->parse(fn (): Image => Image::from($response->data(), $response->rateLimit()));
    }

    protected function file(ApiRequest $request): Download
    {
        return Download::fromResponse($this->transporter->send($request));
    }

    protected function path(string|Image $image): string
    {
        return 'images/'.ApiRequest::segment($this->id($image));
    }

    protected function id(string|Image $image): string
    {
        return $image instanceof Image ? $image->id : $image;
    }

    /**
     * @param  array<array-key, string|Image>  $images
     * @return list<string>
     */
    protected function ids(array $images): array
    {
        return array_values(array_map(fn (string|Image $image): string => $this->id($image), $images));
    }

    protected function toUpload(mixed $file, ?string $name): Upload
    {
        return match (true) {
            $file instanceof Upload => $file,
            is_string($file) => Upload::fromPath($file, $name),
            is_resource($file), $file instanceof StreamInterface => Upload::fromStream($file, $name ?? throw new InvalidArgumentException('A stream upload needs a name, whose extension the API reads.')),
            default => throw new InvalidArgumentException('An upload takes a path, an open stream, a PSR-7 stream or an Upload.'),
        };
    }
}
