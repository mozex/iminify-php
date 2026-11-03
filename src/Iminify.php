<?php

declare(strict_types=1);

namespace Mozex\Iminify;

use SensitiveParameter;

class Iminify
{
    /**
     * The address the API answers at. The bare domain answers with a redirect here, which ends in an exception.
     */
    public const BASE_URI = 'https://www.iminify.com/api/v1';

    public static function client(#[SensitiveParameter] string $apiKey): Client
    {
        return self::factory()->withApiKey($apiKey)->make();
    }

    public static function factory(): Factory
    {
        return new Factory;
    }
}
