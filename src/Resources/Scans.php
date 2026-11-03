<?php

declare(strict_types=1);

namespace Mozex\Iminify\Resources;

use Closure;
use Mozex\Iminify\Exceptions\WaitTimeoutException;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Responses\Download;
use Mozex\Iminify\Responses\Page;
use Mozex\Iminify\Responses\Scan;

/**
 * Every call on page scans. A call that takes a scan takes its id or the `Scan` itself.
 */
class Scans extends ApiResource
{
    /**
     * @param  array<string, mixed>  $query  `status`, `per_page`, `page`
     * @return Page<Scan>
     */
    public function list(array $query = []): Page
    {
        return $this->page(ApiRequest::get('scans', $query), Scan::from(...));
    }

    /**
     * Scans a page and compresses every image it loads.
     *
     * The options pass through as sent, so one the API adds works without a new release:
     * `level`, `format`, `keep_metadata`, `width`, `height`, `scale`, `alt_text` and `alt_text_language`.
     *
     * @param  array<string, mixed>  $options
     */
    public function create(string $url, array $options = []): Scan
    {
        return $this->scan(ApiRequest::post('scans', ['url' => $url] + $options));
    }

    public function get(string|Scan $scan): Scan
    {
        return $this->scan(ApiRequest::get($this->path($scan)));
    }

    /**
     * Reads the scan until the scanner is through and none of its images is still compressing,
     * and returns it. Pass `$until` to wait for more, such as `altTextsInProgress` reaching 0.
     *
     * `$timeout` is checked between reads, so give the HTTP client a timeout of its own.
     *
     * @param  int  $timeout  in seconds
     * @param  (Closure(Scan): bool)|null  $until
     *
     * @throws WaitTimeoutException
     */
    public function wait(string|Scan $scan, int $timeout = 300, ?Closure $until = null): Scan
    {
        return $this->poll(
            fn (): Scan => $this->get($scan),
            fn (Scan $current): bool => $current->isFinished() && ($until === null || $until($current)),
            $timeout,
            'The scan',
            $scan instanceof Scan ? $scan : null,
        );
    }

    public function delete(string|Scan $scan): void
    {
        $this->transporter->send(ApiRequest::delete($this->path($scan)));
    }

    public function retry(string|Scan $scan): Scan
    {
        return $this->scan(ApiRequest::post($this->path($scan).'/retry'));
    }

    public function cancel(string|Scan $scan): Scan
    {
        return $this->scan(ApiRequest::post($this->path($scan).'/cancel'));
    }

    /**
     * Downloads the optimized copy of every image that has finished as one zip.
     */
    public function download(string|Scan $scan): Download
    {
        return $this->file(ApiRequest::get($this->path($scan).'/download'));
    }

    /**
     * Gives the page report a public page, whose address comes back as `shareUrl`.
     */
    public function share(string|Scan $scan): Scan
    {
        return $this->scan(ApiRequest::post($this->path($scan).'/share'));
    }

    public function unshare(string|Scan $scan): void
    {
        $this->transporter->send(ApiRequest::delete($this->path($scan).'/share'));
    }

    public function downloadReportCsv(string|Scan $scan): Download
    {
        return $this->file(ApiRequest::get($this->path($scan).'/report.csv'));
    }

    public function downloadReportPdf(string|Scan $scan): Download
    {
        return $this->file(ApiRequest::get($this->path($scan).'/report.pdf'));
    }

    protected function scan(ApiRequest $request): Scan
    {
        return $this->record($request, Scan::from(...));
    }

    protected function path(string|Scan $scan): string
    {
        return 'scans/'.ApiRequest::segment($scan instanceof Scan ? $scan->id : $scan);
    }
}
