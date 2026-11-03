<?php

declare(strict_types=1);

namespace Mozex\Iminify\Tests\Fakes;

/**
 * Stands for a client like Symfony's, which follows redirects until told not to, and hands back
 * a new client when told.
 */
class FollowingHttpClient extends RecordingHttpClient
{
    /** @var array<string, mixed> */
    public array $options = [];

    /**
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): static
    {
        $client = clone $this;
        $client->options = $options;

        return $client;
    }
}
