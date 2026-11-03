<?php

declare(strict_types=1);

namespace Mozex\Iminify\Contracts;

use Mozex\Iminify\Exceptions\IminifyException;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Http\ApiResponse;

interface TransporterContract
{
    /**
     * Sends the request and hands back a successful answer.
     *
     * @throws IminifyException when the API refuses the request or can't be reached
     */
    public function send(ApiRequest $request): ApiResponse;
}
