<?php

declare(strict_types=1);

namespace Mozex\Iminify\Tests\Fakes;

/**
 * Stands for a client like Symfony's, which follows redirects until told not to.
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
        $this->options = $options;

        return $this;
    }
}
