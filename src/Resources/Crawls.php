<?php

declare(strict_types=1);

namespace Mozex\Iminify\Resources;

use Closure;
use Mozex\Iminify\Exceptions\WaitTimeoutException;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Responses\Crawl;
use Mozex\Iminify\Responses\Download;
use Mozex\Iminify\Responses\Page;

/**
 * Every call on site crawls, which come with a paid plan. A call that takes a crawl takes its
 * id or the `Crawl` itself.
 *
 * The options a crawl takes pass through as sent, so one the API adds works without a new
 * release: `level`, `format`, `keep_metadata`, `width`, `height`, `scale`, `alt_text` and `alt_text_language`.
 */
class Crawls extends ApiResource
{
    /**
     * @param  array<string, mixed>  $query  `per_page`, `page`
     * @return Page<Crawl>
     */
    public function list(array $query = []): Page
    {
        return $this->page(ApiRequest::get('crawls', $query), Crawl::from(...));
    }

    /**
     * Crawls the pages a sitemap lists: an XML sitemap, a sitemap index or a text file with one
     * address a line.
     *
     * @param  array<string, mixed>  $options
     */
    public function fromSitemap(string $sitemap, array $options = []): Crawl
    {
        return $this->crawl(ApiRequest::post('crawls', ['sitemap' => $sitemap] + $options));
    }

    /**
     * Crawls the pages you list, all on one site.
     *
     * @param  list<string>  $pages
     * @param  array<string, mixed>  $options
     */
    public function fromPages(array $pages, array $options = []): Crawl
    {
        return $this->crawl(ApiRequest::post('crawls', ['pages' => $pages] + $options));
    }

    public function get(string|Crawl $crawl): Crawl
    {
        return $this->crawl(ApiRequest::get($this->path($crawl)));
    }

    /**
     * Reads the crawl until it has stopped and no page or image is still at work, and returns it.
     * A crawl takes a few minutes, so the default wait is longer than an image's. Pass `$until`
     * to wait for more.
     *
     * `$timeout` is checked between reads, so give the HTTP client a timeout of its own.
     *
     * @param  int  $timeout  in seconds
     * @param  (Closure(Crawl): bool)|null  $until
     *
     * @throws WaitTimeoutException
     */
    public function wait(string|Crawl $crawl, int $timeout = 900, ?Closure $until = null): Crawl
    {
        return $this->poll(
            fn (): Crawl => $this->get($crawl),
            fn (Crawl $current): bool => $current->isFinished() && ($until === null || $until($current)),
            $timeout,
            'The crawl',
            $crawl instanceof Crawl ? $crawl : null,
        );
    }

    public function delete(string|Crawl $crawl): void
    {
        $this->transporter->send(ApiRequest::delete($this->path($crawl)));
    }

    /**
     * Downloads every page's images as one zip, with a folder for each page.
     */
    public function download(string|Crawl $crawl): Download
    {
        return $this->file(ApiRequest::get($this->path($crawl).'/download'));
    }

    protected function crawl(ApiRequest $request): Crawl
    {
        return $this->record($request, Crawl::from(...));
    }

    protected function path(string|Crawl $crawl): string
    {
        return 'crawls/'.ApiRequest::segment($crawl instanceof Crawl ? $crawl->id : $crawl);
    }
}
