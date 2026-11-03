<?php

declare(strict_types=1);

namespace Mozex\Iminify;

use Closure;
use Mozex\Iminify\Contracts\TransporterContract;

class Client
{
    /**
     * @param  (Closure(float): void)|null  $sleep  what waiting calls instead of `usleep()`
     */
    public function __construct(
        protected TransporterContract $transporter,
        protected ?Closure $sleep = null,
    ) {}

    public function transporter(): TransporterContract
    {
        return $this->transporter;
    }
}
