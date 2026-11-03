<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'die', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('it uses strict types')
    ->expect('Mozex\Iminify')
    ->toUseStrictTypes();

it('names what a user may not have only where it says so', function () {
    $optional = ['GuzzleHttp', 'Spatie', 'Psr\Log', 'PHPUnit', 'Symfony'];
    $allowed = [
        'Factory.php' => ['GuzzleHttp\Client'],
        'Spatie/IminifyOptimizer.php' => ['Spatie', 'Psr\Log'],
        'Testing/FakeClient.php' => ['PHPUnit'],
    ];
    $source = str_replace('\\', '/', dirname(__DIR__).'/src/');
    $found = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = str_replace($source, '', str_replace('\\', '/', $file->getPathname()));

        preg_match_all('/^use ([\w\\\\]+)/m', (string) file_get_contents($file->getPathname()), $imports);

        foreach ($imports[1] as $import) {
            $isOptional = array_filter($optional, fn (string $name): bool => str_starts_with($import, $name.'\\'));
            $isAllowed = array_filter($allowed[$relative] ?? [], fn (string $name): bool => $import === $name || str_starts_with($import, $name.'\\'));

            if ($isOptional !== [] && $isAllowed === []) {
                $found[] = "{$relative}: {$import}";
            }
        }
    }

    expect($found)->toBe([]);
});
