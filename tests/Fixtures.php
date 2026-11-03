<?php

declare(strict_types=1);

namespace Mozex\Iminify\Tests;

/**
 * Answers shaped as the API's document describes them.
 */
class Fixtures
{
    /**
     * A finished image, every optional part filled in.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function image(array $overrides = []): array
    {
        $id = '9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35';
        $api = 'https://www.iminify.com/api/v1/images/'.$id;

        return $overrides + [
            'id' => $id,
            'status' => 'completed',
            'name' => 'team-offsite.webp',
            'original' => ['name' => 'team-offsite.jpg', 'format' => 'jpg', 'size' => 482133, 'width' => 2400, 'height' => 1600, 'download_url' => "{$api}/download?type=original"],
            'optimized' => ['name' => 'team-offsite.webp', 'format' => 'webp', 'size' => 98211, 'width' => 1600, 'height' => 1067, 'download_url' => "{$api}/download"],
            'saved_bytes' => 383922,
            'saved_percent' => 79.6,
            'settings' => ['level' => 'smart', 'format' => 'webp', 'keep_metadata' => false, 'width' => 1600, 'height' => null, 'scale' => null, 'size_limit' => 150, 'responsive_widths' => [480, 960]],
            'size_limit_status' => 'under',
            'content_credentials' => ['status' => 'removed', 'generative_ai' => true],
            'alt_text' => ['status' => 'written', 'text' => 'Six colleagues around a picnic table', 'language' => 'en', 'suggested_name' => 'colleagues-picnic-table'],
            'responsive' => [
                'status' => 'ready',
                'files' => [['width' => 480, 'height' => 320, 'size' => 24118, 'name' => 'team-offsite-480w.webp', 'download_url' => "{$api}/responsive/480"]],
                'download_url' => "{$api}/responsive.zip",
                'html' => '<img src="team-offsite-480w.webp">',
            ],
            'scan_id' => null,
            'source_url' => 'https://example.com/team-offsite.jpg',
            'folder' => 'holiday/beach',
            'share_url' => 'https://www.iminify.com/shared/abc',
            'created_at' => '2026-11-03T10:15:30+00:00',
            'updated_at' => '2026-11-03T10:15:42+00:00',
        ];
    }

    /**
     * An image still in the queue: nothing optional is filled in yet.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function queuedImage(array $overrides = []): array
    {
        return $overrides + self::image([
            'status' => 'queued',
            'optimized' => null,
            'saved_bytes' => null,
            'saved_percent' => null,
            'settings' => ['level' => 'smart', 'format' => null, 'keep_metadata' => false, 'width' => null, 'height' => null, 'scale' => null, 'size_limit' => null, 'responsive_widths' => null],
            'size_limit_status' => null,
            'content_credentials' => null,
            'alt_text' => null,
            'responsive' => null,
            'source_url' => null,
            'folder' => null,
            'share_url' => null,
        ]);
    }
}
