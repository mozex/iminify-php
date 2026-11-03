<?php

declare(strict_types=1);

namespace Mozex\Iminify;

use Closure;
use Mozex\Iminify\Contracts\TransporterContract;
use Mozex\Iminify\Resources\Images;

class Client
{
    /**
     * @param  (Closure(float): void)|null  $sleep  what waiting calls instead of `usleep()`
     */
    public function __construct(
        protected TransporterContract $transporter,
        protected ?Closure $sleep = null,
    ) {}

    public function images(): Images
    {
        return new Images($this->transporter, $this->sleep);
    }

    /**
     * The way to the API itself, for a call this client doesn't have a method for yet.
     */
    public function transporter(): TransporterContract
    {
        return $this->transporter;
    }
}
