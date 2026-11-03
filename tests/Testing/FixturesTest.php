<?php

declare(strict_types=1);

use Mozex\Iminify\Testing\Fixtures;

describe('the fixtures', function () {
    /*
     * They ship, so they can't read the document at run time: this is what keeps the
     * copies honest when the document changes.
     */
    it('are the API document\'s own examples', function (string $fixture, string $operationId, int $status) {
        expect(Fixtures::{$fixture}())->toBe(apiExample($operationId, $status)['data']);
    })->with([
        ['image', 'getImage', 200],
        ['queuedImage', 'createImage', 202],
        ['scan', 'getScan', 200],
        ['queuedScan', 'createScan', 202],
        ['group', 'getGroup', 200],
        ['openGroup', 'createGroup', 201],
        ['groupInProgress', 'sendGroupSource', 202],
        ['crawl', 'getCrawl', 200],
        ['queuedCrawl', 'createCrawl', 202],
        ['monitor', 'getMonitor', 200],
        ['newMonitor', 'createMonitor', 201],
        ['account', 'getAccount', 200],
    ]);

    it('replace whole fields by what a test passes', function () {
        expect(Fixtures::image(['name' => 'b.webp', 'added_later' => 1]))->toMatchArray(['name' => 'b.webp', 'added_later' => 1, 'status' => 'completed'])
            ->and(Fixtures::image(['optimized' => null])['optimized'])->toBeNull();
    });
});
