<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'die', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('it uses strict types')
    ->expect('Mozex\Iminify')
    ->toUseStrictTypes();

arch('it names what a user may not have only where it says so')
    ->expect('Mozex\Iminify')
    ->not->toUse(['GuzzleHttp', 'Spatie', 'Psr\Log', 'PHPUnit', 'Symfony'])
    ->ignoring([
        'Mozex\Iminify\Factory',
        'Mozex\Iminify\Spatie',
        'Mozex\Iminify\Testing\FakeClient',
        'Mozex\Iminify\Tests',
    ]);
