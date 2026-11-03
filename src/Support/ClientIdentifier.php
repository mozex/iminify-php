<?php

declare(strict_types=1);

namespace Mozex\Iminify\Support;

use Composer\InstalledVersions;

/**
 * What the `Iminify-Client` header says, `<name>/<version>`. The API keeps only a plain
 * `x.y.z` with at most a short tag, so a development install declares 0.0.0.
 */
class ClientIdentifier
{
    public static function for(string $name, string $package): string
    {
        return $name.'/'.self::version(InstalledVersions::isInstalled($package) ? InstalledVersions::getPrettyVersion($package) : null);
    }

    public static function version(?string $installed): string
    {
        $version = ltrim((string) $installed, 'v');

        return preg_match('/\A\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[0-9A-Za-z.]{1,8})?\z/', $version) === 1 ? $version : '0.0.0';
    }
}
