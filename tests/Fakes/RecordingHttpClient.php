<?php

declare(strict_types=1);

namespace Mozex\Iminify\Tests\Fakes;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * A PSR-18 client that answers from a queue and keeps every request it was sent.
 */
class RecordingHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /**
     * @param  list<ResponseInterface|Throwable>  $responses
     */
    public function __construct(public array $responses = []) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses) ?? throw new RuntimeException('No answer queued for '.$request->getMethod().' '.$request->getUri());

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }
}
