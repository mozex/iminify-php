<?php

declare(strict_types=1);

namespace Mozex\Iminify\Tests\Fakes;

use Closure;
use Mozex\Iminify\Client;
use Mozex\Iminify\Spatie\IminifyOptimizer;

/**
 * The optimizer on a client the test gives it, keeping the waits it would have slept through.
 * A closure is called with the file's path, for answers that depend on what's on disk by then.
 */
class FakeOptimizer extends IminifyOptimizer
{
    /** @var list<int> */
    public array $paused = [];

    /**
     * @param  Client|Closure(string): Client  $given
     * @param  array<string, mixed>  $options
     */
    public function __construct(public Client|Closure $given, array $options = [])
    {
        parent::__construct($options);
    }

    protected function client(): Client
    {
        return $this->given instanceof Closure ? ($this->given)($this->imagePath) : $this->given;
    }

    protected function pause(int $seconds): void
    {
        $this->paused[] = $seconds;
    }
}
