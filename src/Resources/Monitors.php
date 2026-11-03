<?php

declare(strict_types=1);

namespace Mozex\Iminify\Resources;

use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Responses\Crawl;
use Mozex\Iminify\Responses\Monitor;

/**
 * Every call on weekly site checks, which come with a paid plan. A call that takes a check
 * takes its id or the `Monitor` itself. A run is a site crawl: read it through `crawls()`.
 */
class Monitors extends ApiResource
{
    /**
     * Every check on the account, oldest first. A plan sets how many there can be, so the
     * list is never paged.
     *
     * @return list<Monitor>
     */
    public function list(): array
    {
        return $this->records(ApiRequest::get('monitors'), Monitor::from(...));
    }

    /**
     * Turns a crawl into a weekly check of its site, or hands back the check already made from
     * that crawl. Nothing runs now and nothing is counted: the first run is a week away.
     */
    public function create(string|Crawl $crawl): Monitor
    {
        return $this->monitor(ApiRequest::post('monitors', ['crawl_id' => $crawl instanceof Crawl ? $crawl->id : $crawl]));
    }

    public function get(string|Monitor $monitor): Monitor
    {
        return $this->monitor(ApiRequest::get($this->path($monitor)));
    }

    /**
     * The parameters pass through as sent, so one the API adds works without a new release:
     * `emails`, false to stop a check's emails and keep it running.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function update(string|Monitor $monitor, array $parameters): Monitor
    {
        return $this->monitor(ApiRequest::patch($this->path($monitor), $parameters));
    }

    public function delete(string|Monitor $monitor): void
    {
        $this->transporter->send(ApiRequest::delete($this->path($monitor)));
    }

    protected function monitor(ApiRequest $request): Monitor
    {
        return $this->record($request, Monitor::from(...));
    }

    protected function path(string|Monitor $monitor): string
    {
        return 'monitors/'.ApiRequest::segment($monitor instanceof Monitor ? $monitor->id : $monitor);
    }
}
