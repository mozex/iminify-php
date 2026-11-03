<?php

declare(strict_types=1);

namespace Mozex\Iminify\Testing;

use Http\Discovery\Psr17FactoryDiscovery;
use Mozex\Iminify\Http\ApiResponse;

/**
 * Answers shaped as the API's published document gives them, for your tests. Each record is
 * that document's own example, so pass only the fields your test cares about. Queue them on a
 * `FakeClient`, or read one with its response class, as in `Image::from(Fixtures::image())`.
 */
class Fixtures
{
    /**
     * A finished image, every optional part filled in, as `GET /images/{id}` answers.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function image(array $overrides = []): array
    {
        return $overrides + [
            'id' => '9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35',
            'status' => 'completed',
            'name' => 'team-offsite.webp',
            'original' => [
                'name' => 'team-offsite.jpg',
                'format' => 'jpg',
                'size' => 2841205,
                'width' => 4032,
                'height' => 3024,
                'download_url' => 'https://www.iminify.com/api/v1/images/9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35/download?type=original',
            ],
            'optimized' => [
                'name' => 'team-offsite.webp',
                'format' => 'webp',
                'size' => 212877,
                'width' => 1600,
                'height' => 1200,
                'download_url' => 'https://www.iminify.com/api/v1/images/9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35/download',
            ],
            'saved_bytes' => 2628328,
            'saved_percent' => 92.51,
            'settings' => [
                'level' => 'smart',
                'format' => 'webp',
                'keep_metadata' => false,
                'width' => 1600,
                'height' => null,
                'scale' => null,
                'size_limit' => 250,
                'responsive_widths' => [
                    480,
                    768,
                    1200,
                    1600,
                ],
            ],
            'size_limit_status' => 'under',
            'content_credentials' => [
                'status' => 'removed',
                'generative_ai' => false,
            ],
            'alt_text' => [
                'status' => 'written',
                'text' => 'Six colleagues laughing around a picnic table on a lakeside lawn',
                'language' => 'en',
                'suggested_name' => 'colleagues-picnic-table-lakeside',
            ],
            'responsive' => [
                'status' => 'ready',
                'files' => [
                    [
                        'width' => 480,
                        'height' => 360,
                        'size' => 24118,
                        'name' => 'team-offsite-480w.webp',
                        'download_url' => 'https://www.iminify.com/api/v1/images/9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35/responsive/480',
                    ],
                    [
                        'width' => 768,
                        'height' => 576,
                        'size' => 55904,
                        'name' => 'team-offsite-768w.webp',
                        'download_url' => 'https://www.iminify.com/api/v1/images/9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35/responsive/768',
                    ],
                    [
                        'width' => 1200,
                        'height' => 900,
                        'size' => 128330,
                        'name' => 'team-offsite-1200w.webp',
                        'download_url' => 'https://www.iminify.com/api/v1/images/9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35/responsive/1200',
                    ],
                    [
                        'width' => 1600,
                        'height' => 1200,
                        'size' => 212877,
                        'name' => 'team-offsite-1600w.webp',
                        'download_url' => 'https://www.iminify.com/api/v1/images/9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35/responsive/1600',
                    ],
                ],
                'download_url' => 'https://www.iminify.com/api/v1/images/9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35/responsive.zip',
                'html' => '<img
  src="team-offsite-1600w.webp"
  srcset="team-offsite-480w.webp 480w, team-offsite-768w.webp 768w, team-offsite-1200w.webp 1200w, team-offsite-1600w.webp 1600w"
  sizes="(max-width: 1600px) 100vw, 1600px"
  width="1600"
  height="1200"
  alt="Six colleagues laughing around a picnic table on a lakeside lawn"
  style="max-width: 100%; height: auto"
  decoding="async"
>',
            ],
            'scan_id' => null,
            'source_url' => null,
            'folder' => null,
            'share_url' => null,
            'created_at' => '2026-10-01T09:30:00+00:00',
            'updated_at' => '2026-10-01T09:30:09+00:00',
        ];
    }

    /**
     * An image just sent, as `POST /images` answers.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function queuedImage(array $overrides = []): array
    {
        return $overrides + [
            'id' => '9d3c5b91-0b2e-4a7f-8c1d-5e6f7a8b9c0d',
            'status' => 'queued',
            'name' => 'IMG_4471.heic',
            'original' => [
                'name' => 'IMG_4471.heic',
                'format' => 'heic',
                'size' => 3402118,
                'width' => 4032,
                'height' => 3024,
                'download_url' => 'https://www.iminify.com/api/v1/images/9d3c5b91-0b2e-4a7f-8c1d-5e6f7a8b9c0d/download?type=original',
            ],
            'optimized' => null,
            'saved_bytes' => null,
            'saved_percent' => null,
            'settings' => [
                'level' => 'ultra',
                'format' => 'auto',
                'keep_metadata' => true,
                'width' => null,
                'height' => null,
                'scale' => null,
                'size_limit' => null,
                'responsive_widths' => [
                    480,
                    768,
                    1200,
                    1600,
                ],
            ],
            'size_limit_status' => null,
            'content_credentials' => null,
            'alt_text' => [
                'status' => 'pending',
                'text' => null,
                'language' => 'de',
                'suggested_name' => null,
            ],
            'responsive' => [
                'status' => 'pending',
                'files' => [],
                'download_url' => null,
                'html' => null,
            ],
            'scan_id' => null,
            'source_url' => null,
            'folder' => null,
            'share_url' => null,
            'created_at' => '2026-10-01T09:31:12+00:00',
            'updated_at' => '2026-10-01T09:31:12+00:00',
        ];
    }

    /**
     * A finished scan with its images and page report, as `GET /scans/{id}` answers.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function scan(array $overrides = []): array
    {
        return $overrides + [
            'id' => '9d3c4f12-7a8b-4c9d-a0e1-f2a3b4c5d6e7',
            'url' => 'https://example.com/pricing',
            'crawl_id' => null,
            'status' => 'completed',
            'note' => 'Rendered in a headless browser. 14 image URLs found: 12 queued for compression, 1 duplicates of an image already queued, 1 in a format the compressor does not accept.',
            'settings' => [
                'level' => 'smart',
                'format' => 'webp',
                'keep_metadata' => false,
                'width' => 1600,
                'height' => null,
                'scale' => null,
            ],
            'skipped' => [
                [
                    'url' => 'https://cdn.example.com/pricing/hero.jpg?v=2',
                    'reason' => 'duplicate',
                    'message' => 'The same image as https://example.com/images/hero.jpg, so it was compressed once.',
                ],
                [
                    'url' => 'https://example.com/images/logo.svg',
                    'reason' => 'unsupported_format',
                    'message' => 'The file is SVG, a format Iminify does not compress.',
                ],
            ],
            'images_count' => 12,
            'images_in_progress' => 0,
            'alt_texts_in_progress' => 0,
            'images_finished' => 12,
            'original_size' => 18406331,
            'optimized_size' => 2114902,
            'saved_bytes' => 16291429,
            'saved_percent' => 88.51,
            'download_url' => 'https://www.iminify.com/api/v1/scans/9d3c4f12-7a8b-4c9d-a0e1-f2a3b4c5d6e7/download',
            'images' => [
                [
                    'id' => '9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35',
                    'status' => 'completed',
                    'name' => 'team-offsite.webp',
                    'original' => [
                        'name' => 'team-offsite.jpg',
                        'format' => 'jpg',
                        'size' => 2841205,
                        'width' => 4032,
                        'height' => 3024,
                        'download_url' => 'https://www.iminify.com/api/v1/images/9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35/download?type=original',
                    ],
                    'optimized' => [
                        'name' => 'team-offsite.webp',
                        'format' => 'webp',
                        'size' => 212877,
                        'width' => 1600,
                        'height' => 1200,
                        'download_url' => 'https://www.iminify.com/api/v1/images/9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35/download',
                    ],
                    'saved_bytes' => 2628328,
                    'saved_percent' => 92.51,
                    'settings' => [
                        'level' => 'smart',
                        'format' => 'webp',
                        'keep_metadata' => false,
                        'width' => 1600,
                        'height' => null,
                        'scale' => null,
                        'size_limit' => null,
                        'responsive_widths' => null,
                    ],
                    'size_limit_status' => null,
                    'content_credentials' => [
                        'status' => 'removed',
                        'generative_ai' => false,
                    ],
                    'alt_text' => null,
                    'responsive' => null,
                    'scan_id' => '9d3c4f12-7a8b-4c9d-a0e1-f2a3b4c5d6e7',
                    'source_url' => 'https://example.com/images/team-offsite.jpg',
                    'folder' => null,
                    'share_url' => null,
                    'created_at' => '2026-10-01T09:30:00+00:00',
                    'updated_at' => '2026-10-01T09:30:09+00:00',
                ],
                [
                    'id' => '9d3c5b91-0b2e-4a7f-8c1d-5e6f7a8b9c0d',
                    'status' => 'completed',
                    'name' => 'hero-banner.webp',
                    'original' => [
                        'name' => 'hero-banner.jpg',
                        'format' => 'jpg',
                        'size' => 2841205,
                        'width' => 4032,
                        'height' => 3024,
                        'download_url' => 'https://www.iminify.com/api/v1/images/9d3c5b91-0b2e-4a7f-8c1d-5e6f7a8b9c0d/download?type=original',
                    ],
                    'optimized' => [
                        'name' => 'hero-banner.webp',
                        'format' => 'webp',
                        'size' => 212877,
                        'width' => 1600,
                        'height' => 1200,
                        'download_url' => 'https://www.iminify.com/api/v1/images/9d3c5b91-0b2e-4a7f-8c1d-5e6f7a8b9c0d/download',
                    ],
                    'saved_bytes' => 2628328,
                    'saved_percent' => 92.51,
                    'settings' => [
                        'level' => 'smart',
                        'format' => 'webp',
                        'keep_metadata' => false,
                        'width' => 1600,
                        'height' => null,
                        'scale' => null,
                        'size_limit' => null,
                        'responsive_widths' => null,
                    ],
                    'size_limit_status' => null,
                    'content_credentials' => [
                        'status' => 'removed',
                        'generative_ai' => false,
                    ],
                    'alt_text' => null,
                    'responsive' => null,
                    'scan_id' => '9d3c4f12-7a8b-4c9d-a0e1-f2a3b4c5d6e7',
                    'source_url' => 'https://example.com/images/hero-banner.jpg',
                    'folder' => null,
                    'share_url' => null,
                    'created_at' => '2026-10-01T09:30:00+00:00',
                    'updated_at' => '2026-10-01T09:30:09+00:00',
                ],
            ],
            'audit' => [
                'rendered' => true,
                'images_found' => 14,
                'largest_paint' => [
                    'kind' => 'image',
                    'element' => 'img',
                    'url' => 'https://example.com/images/hero-banner.jpg',
                ],
                'findings' => [
                    'oversized' => 1,
                    'without_dimensions' => 1,
                    'lazy_above_fold' => 0,
                    'eager_below_fold' => 1,
                    'missing_alt' => 1,
                    'lcp_not_prioritized' => 1,
                ],
                'oversized_bytes' => 180384,
                'bytes' => 899380,
                'formats' => [
                    [
                        'format' => 'webp',
                        'count' => 2,
                    ],
                    [
                        'format' => 'jpg',
                        'count' => 1,
                    ],
                    [
                        'format' => 'svg',
                        'count' => 1,
                    ],
                ],
                'images' => [
                    [
                        'url' => 'https://example.com/images/hero-banner.jpg',
                        'format' => 'webp',
                        'bytes' => 412306,
                        'natural_width' => 3200,
                        'natural_height' => 1800,
                        'rendered_width' => 1200,
                        'rendered_height' => 675,
                        'above_fold' => true,
                        'largest_paint' => true,
                        'alt' => 'present',
                        'findings' => [
                            'oversized',
                            'lcp_not_prioritized',
                        ],
                        'wasted_bytes' => 180384,
                        'outcome' => 'compressed',
                        'skip_reason' => null,
                        'image_id' => '9d3c5b91-0b2e-4a7f-8c1d-5e6f7a8b9c0d',
                        'format_sizes' => null,
                        'suggested_alt' => null,
                    ],
                    [
                        'url' => 'https://example.com/images/team-offsite.jpg',
                        'format' => 'webp',
                        'bytes' => 96118,
                        'natural_width' => 1600,
                        'natural_height' => 1200,
                        'rendered_width' => 800,
                        'rendered_height' => 600,
                        'above_fold' => false,
                        'largest_paint' => false,
                        'alt' => 'missing',
                        'findings' => [
                            'without_dimensions',
                            'eager_below_fold',
                            'missing_alt',
                        ],
                        'wasted_bytes' => null,
                        'outcome' => 'compressed',
                        'skip_reason' => null,
                        'image_id' => '9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35',
                        'format_sizes' => null,
                        'suggested_alt' => null,
                    ],
                    [
                        'url' => 'https://cdn.example.com/pricing/hero.jpg?v=2',
                        'format' => 'jpg',
                        'bytes' => 388042,
                        'natural_width' => null,
                        'natural_height' => null,
                        'rendered_width' => null,
                        'rendered_height' => null,
                        'above_fold' => null,
                        'largest_paint' => false,
                        'alt' => null,
                        'findings' => [],
                        'wasted_bytes' => null,
                        'outcome' => 'skipped',
                        'skip_reason' => 'duplicate',
                        'image_id' => null,
                        'format_sizes' => null,
                        'suggested_alt' => null,
                    ],
                    [
                        'url' => 'https://example.com/images/logo.svg',
                        'format' => 'svg',
                        'bytes' => 2914,
                        'natural_width' => 160,
                        'natural_height' => 40,
                        'rendered_width' => 160,
                        'rendered_height' => 40,
                        'above_fold' => true,
                        'largest_paint' => false,
                        'alt' => 'present',
                        'findings' => [],
                        'wasted_bytes' => null,
                        'outcome' => 'skipped',
                        'skip_reason' => 'unsupported_format',
                        'image_id' => null,
                        'format_sizes' => null,
                        'suggested_alt' => null,
                    ],
                ],
            ],
            'share_url' => null,
            'created_at' => '2026-10-01T11:05:00+00:00',
            'updated_at' => '2026-10-01T11:05:41+00:00',
        ];
    }

    /**
     * A scan just started, as `POST /scans` answers.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function queuedScan(array $overrides = []): array
    {
        return $overrides + [
            'id' => '9d3c4f12-7a8b-4c9d-a0e1-f2a3b4c5d6e7',
            'url' => 'https://example.com/pricing',
            'crawl_id' => null,
            'status' => 'queued',
            'note' => null,
            'settings' => [
                'level' => 'smart',
                'format' => 'webp',
                'keep_metadata' => false,
                'width' => 1600,
                'height' => null,
                'scale' => null,
            ],
            'skipped' => [],
            'images_count' => 0,
            'images_in_progress' => 0,
            'alt_texts_in_progress' => 0,
            'images_finished' => 0,
            'original_size' => 0,
            'optimized_size' => 0,
            'saved_bytes' => 0,
            'saved_percent' => null,
            'download_url' => 'https://www.iminify.com/api/v1/scans/9d3c4f12-7a8b-4c9d-a0e1-f2a3b4c5d6e7/download',
            'share_url' => null,
            'created_at' => '2026-10-01T11:05:00+00:00',
            'updated_at' => '2026-10-01T11:05:00+00:00',
        ];
    }

    /**
     * A finished upload group, as `GET /groups/{id}` answers.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function group(array $overrides = []): array
    {
        return $overrides + [
            'id' => '9d3c5c02-4e1f-4a6b-8d2c-3f5a7b9c1e24',
            'name' => 'IMG_2041',
            'status' => 'finished',
            'upload_by' => null,
            'source' => [
                'format' => 'jpg',
                'size' => 2841205,
                'width' => 2560,
                'height' => 1707,
            ],
            'settings' => [
                'level' => 'smart',
                'formats' => [
                    'original',
                    'webp',
                ],
                'keep_metadata' => false,
                'ephemeral' => true,
                'priority' => 'interactive',
            ],
            'files' => 6,
            'outputs' => [
                [
                    'name' => 'full',
                    'format' => 'original',
                    'status' => 'completed',
                    'file' => 'full.jpg',
                    'width' => 2560,
                    'height' => 1707,
                    'size' => 612480,
                    'replace' => true,
                ],
                [
                    'name' => 'full',
                    'format' => 'webp',
                    'status' => 'completed',
                    'file' => 'full.webp',
                    'width' => 2560,
                    'height' => 1707,
                    'size' => 401226,
                    'replace' => true,
                ],
                [
                    'name' => 'medium',
                    'format' => 'original',
                    'status' => 'completed',
                    'file' => 'medium.jpg',
                    'width' => 300,
                    'height' => 200,
                    'size' => 14880,
                    'replace' => true,
                ],
                [
                    'name' => 'medium',
                    'format' => 'webp',
                    'status' => 'completed',
                    'file' => 'medium.webp',
                    'width' => 300,
                    'height' => 200,
                    'size' => 11402,
                    'replace' => true,
                ],
                [
                    'name' => 'thumbnail',
                    'format' => 'original',
                    'status' => 'completed',
                    'file' => 'thumbnail.jpg',
                    'width' => 150,
                    'height' => 150,
                    'size' => 6950,
                    'replace' => true,
                ],
                [
                    'name' => 'thumbnail',
                    'format' => 'webp',
                    'status' => 'completed',
                    'file' => null,
                    'width' => 150,
                    'height' => 150,
                    'size' => 7402,
                    'replace' => false,
                ],
            ],
            'alt_text' => [
                'status' => 'written',
                'text' => 'A red tram crossing a stone bridge at dusk',
                'language' => 'en',
                'suggested_name' => 'red-tram-stone-bridge-dusk',
            ],
            'download_url' => 'https://www.iminify.com/api/v1/groups/9d3c5c02-4e1f-4a6b-8d2c-3f5a7b9c1e24/download',
            'created_at' => '2026-10-01T09:40:00+00:00',
            'received_at' => '2026-10-01T09:40:21+00:00',
        ];
    }

    /**
     * A group waiting for its source, as `POST /groups` answers.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function openGroup(array $overrides = []): array
    {
        return $overrides + [
            'id' => '9d3c5c02-4e1f-4a6b-8d2c-3f5a7b9c1e24',
            'name' => 'IMG_2041',
            'status' => 'awaiting_source',
            'upload_by' => '2026-10-01T10:40:00+00:00',
            'source' => [
                'format' => 'jpg',
                'size' => 2841205,
                'width' => 2560,
                'height' => 1707,
            ],
            'settings' => [
                'level' => 'smart',
                'formats' => [
                    'original',
                    'webp',
                ],
                'keep_metadata' => false,
                'ephemeral' => true,
                'priority' => 'interactive',
            ],
            'files' => 6,
            'outputs' => [],
            'alt_text' => null,
            'download_url' => null,
            'created_at' => '2026-10-01T09:40:00+00:00',
            'received_at' => null,
        ];
    }

    /**
     * A group making its files, as `POST /groups/{id}/source` answers.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function groupInProgress(array $overrides = []): array
    {
        return $overrides + [
            'id' => '9d3c5c02-4e1f-4a6b-8d2c-3f5a7b9c1e24',
            'name' => 'IMG_2041',
            'status' => 'in_progress',
            'upload_by' => null,
            'source' => [
                'format' => 'jpg',
                'size' => 2841205,
                'width' => 2560,
                'height' => 1707,
            ],
            'settings' => [
                'level' => 'smart',
                'formats' => [
                    'original',
                    'webp',
                ],
                'keep_metadata' => false,
                'ephemeral' => true,
                'priority' => 'interactive',
            ],
            'files' => 6,
            'outputs' => [
                [
                    'name' => 'full',
                    'format' => 'original',
                    'status' => 'completed',
                    'file' => 'full.jpg',
                    'width' => 2560,
                    'height' => 1707,
                    'size' => 612480,
                    'replace' => true,
                ],
                [
                    'name' => 'full',
                    'format' => 'webp',
                    'status' => 'completed',
                    'file' => 'full.webp',
                    'width' => 2560,
                    'height' => 1707,
                    'size' => 401226,
                    'replace' => true,
                ],
                [
                    'name' => 'medium',
                    'format' => 'original',
                    'status' => 'queued',
                    'file' => null,
                    'width' => null,
                    'height' => null,
                    'size' => null,
                    'replace' => false,
                ],
                [
                    'name' => 'medium',
                    'format' => 'webp',
                    'status' => 'queued',
                    'file' => null,
                    'width' => null,
                    'height' => null,
                    'size' => null,
                    'replace' => false,
                ],
                [
                    'name' => 'thumbnail',
                    'format' => 'original',
                    'status' => 'queued',
                    'file' => null,
                    'width' => null,
                    'height' => null,
                    'size' => null,
                    'replace' => false,
                ],
                [
                    'name' => 'thumbnail',
                    'format' => 'webp',
                    'status' => 'queued',
                    'file' => null,
                    'width' => null,
                    'height' => null,
                    'size' => null,
                    'replace' => false,
                ],
            ],
            'alt_text' => [
                'status' => 'pending',
                'text' => null,
                'language' => 'en',
                'suggested_name' => null,
            ],
            'download_url' => null,
            'created_at' => '2026-10-01T09:40:00+00:00',
            'received_at' => '2026-10-01T09:40:21+00:00',
        ];
    }

    /**
     * A finished crawl with its pages and the site's report, as `GET /crawls/{id}` answers.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function crawl(array $overrides = []): array
    {
        return $overrides + [
            'id' => '9d3c4e07-5c6d-4e7f-9a0b-c1d2e3f4a5b6',
            'url' => 'https://example.com/sitemap.xml',
            'source' => 'sitemap',
            'monitor_id' => null,
            'status' => 'completed',
            'note' => null,
            'settings' => [
                'level' => 'smart',
                'format' => 'webp',
                'keep_metadata' => false,
                'width' => 1600,
                'height' => null,
                'scale' => null,
            ],
            'pages_count' => 2,
            'pages_in_progress' => 0,
            'pages_failed' => 0,
            'pages_left_out' => 0,
            'images_count' => 12,
            'images_in_progress' => 0,
            'images_finished' => 12,
            'original_size' => 18406331,
            'optimized_size' => 2114902,
            'saved_bytes' => 16291429,
            'saved_percent' => 88.51,
            'download_url' => 'https://www.iminify.com/api/v1/crawls/9d3c4e07-5c6d-4e7f-9a0b-c1d2e3f4a5b6/download',
            'pages' => [
                [
                    'id' => '9d3c4f2a-1b2c-4d3e-8f4a-5b6c7d8e9f01',
                    'url' => 'https://example.com/',
                    'status' => 'completed',
                    'note' => 'Rendered in a headless browser. 1 image URL found: 1 in a format the compressor does not accept.',
                    'images_count' => 0,
                    'images_in_progress' => 0,
                    'images_to_fix' => 0,
                ],
                [
                    'id' => '9d3c4f12-7a8b-4c9d-a0e1-f2a3b4c5d6e7',
                    'url' => 'https://example.com/pricing',
                    'status' => 'completed',
                    'note' => 'Rendered in a headless browser. 14 image URLs found: 12 queued for compression, 1 duplicates of an image already queued, 1 in a format the compressor does not accept.',
                    'images_count' => 12,
                    'images_in_progress' => 0,
                    'images_to_fix' => 2,
                ],
            ],
            'audit' => [
                'pages_audited' => 2,
                'images_found' => 4,
                'images_to_fix' => 2,
                'findings' => [
                    'oversized' => 1,
                    'without_dimensions' => 1,
                    'lazy_above_fold' => 0,
                    'eager_below_fold' => 1,
                    'missing_alt' => 1,
                    'lcp_not_prioritized' => 1,
                ],
                'oversized_bytes' => 180384,
                'bytes' => 899380,
            ],
            'created_at' => '2026-10-01T11:04:50+00:00',
            'updated_at' => '2026-10-01T11:04:55+00:00',
        ];
    }

    /**
     * A crawl just started, as `POST /crawls` answers.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function queuedCrawl(array $overrides = []): array
    {
        return $overrides + [
            'id' => '9d3c4e07-5c6d-4e7f-9a0b-c1d2e3f4a5b6',
            'url' => 'https://example.com/sitemap.xml',
            'source' => 'sitemap',
            'monitor_id' => null,
            'status' => 'queued',
            'note' => null,
            'settings' => [
                'level' => 'smart',
                'format' => 'webp',
                'keep_metadata' => false,
                'width' => 1600,
                'height' => null,
                'scale' => null,
            ],
            'pages_count' => 0,
            'pages_in_progress' => 0,
            'pages_failed' => 0,
            'pages_left_out' => 0,
            'images_count' => 0,
            'images_in_progress' => 0,
            'images_finished' => 0,
            'original_size' => 0,
            'optimized_size' => 0,
            'saved_bytes' => 0,
            'saved_percent' => null,
            'download_url' => 'https://www.iminify.com/api/v1/crawls/9d3c4e07-5c6d-4e7f-9a0b-c1d2e3f4a5b6/download',
            'created_at' => '2026-10-01T11:04:50+00:00',
            'updated_at' => '2026-10-01T11:04:50+00:00',
        ];
    }

    /**
     * A weekly check with its newest run, as `GET /monitors/{id}` answers.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function monitor(array $overrides = []): array
    {
        return $overrides + [
            'id' => '9d3c4e3b-8d9e-4f0a-b1c2-d3e4f5a6b7c8',
            'url' => 'https://example.com/sitemap.xml',
            'source' => 'sitemap',
            'crawl_id' => '9d3c4e07-5c6d-4e7f-9a0b-c1d2e3f4a5b6',
            'settings' => [
                'level' => 'smart',
                'format' => 'webp',
                'keep_metadata' => false,
                'width' => 1600,
                'height' => null,
                'scale' => null,
            ],
            'emails' => true,
            'paused' => false,
            'next_run_at' => '2026-10-15T11:04:55+00:00',
            'last_run' => [
                'id' => '9d3c6a14-2b3c-4d5e-8f6a-7b8c9d0e1f23',
                'url' => 'https://example.com/sitemap.xml',
                'source' => 'sitemap',
                'monitor_id' => '9d3c4e3b-8d9e-4f0a-b1c2-d3e4f5a6b7c8',
                'status' => 'completed',
                'note' => null,
                'settings' => [
                    'level' => 'smart',
                    'format' => 'webp',
                    'keep_metadata' => false,
                    'width' => 1600,
                    'height' => null,
                    'scale' => null,
                ],
                'pages_count' => 2,
                'pages_in_progress' => 0,
                'pages_failed' => 0,
                'pages_left_out' => 0,
                'images_count' => 1,
                'images_in_progress' => 0,
                'images_finished' => 1,
                'original_size' => 1204118,
                'optimized_size' => 151009,
                'saved_bytes' => 1053109,
                'saved_percent' => 87.46,
                'download_url' => 'https://www.iminify.com/api/v1/crawls/9d3c6a14-2b3c-4d5e-8f6a-7b8c9d0e1f23/download',
                'created_at' => '2026-10-08T11:09:00+00:00',
                'updated_at' => '2026-10-08T11:09:04+00:00',
            ],
            'created_at' => '2026-10-01T11:06:12+00:00',
        ];
    }

    /**
     * A weekly check just made, with no run yet, as `POST /monitors` answers.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function newMonitor(array $overrides = []): array
    {
        return $overrides + [
            'id' => '9d3c4e3b-8d9e-4f0a-b1c2-d3e4f5a6b7c8',
            'url' => 'https://example.com/sitemap.xml',
            'source' => 'sitemap',
            'crawl_id' => '9d3c4e07-5c6d-4e7f-9a0b-c1d2e3f4a5b6',
            'settings' => [
                'level' => 'smart',
                'format' => 'webp',
                'keep_metadata' => false,
                'width' => 1600,
                'height' => null,
                'scale' => null,
            ],
            'emails' => true,
            'paused' => false,
            'next_run_at' => '2026-10-08T11:06:12+00:00',
            'last_run' => null,
            'created_at' => '2026-10-01T11:06:12+00:00',
        ];
    }

    /**
     * An account on the free plan, as `GET /account` answers.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function account(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'plan' => 'free',
            'plan_ends_at' => null,
            'limits' => [
                'images_per_day' => 40,
                'scans_per_day' => 3,
                'alt_texts_per_day' => 40,
                'files_per_day' => 1500,
                'images_per_scan' => 20,
                'crawls_per_day' => 0,
                'pages_per_crawl' => 0,
                'monitors' => 0,
                'max_file_size' => 33554432,
                'max_megapixels' => 64,
                'compressions_per_minute' => 20,
                'scans_per_minute' => 1,
                'requests_per_minute' => 60,
                'max_batch' => 150,
            ],
            'usage' => [
                'images' => [
                    'used' => 12,
                    'remaining' => 28,
                    'resets_at' => '2026-10-02T09:30:00+00:00',
                ],
                'scans' => [
                    'used' => 1,
                    'remaining' => 2,
                    'resets_at' => '2026-10-02T11:05:00+00:00',
                ],
                'crawls' => [
                    'used' => 0,
                    'remaining' => 0,
                    'resets_at' => null,
                ],
                'alt_texts' => [
                    'used' => 12,
                    'remaining' => 28,
                    'resets_at' => '2026-10-02T09:30:00+00:00',
                ],
                'files' => [
                    'used' => 6,
                    'remaining' => 1494,
                    'resets_at' => '2026-10-02T09:40:00+00:00',
                ],
            ],
        ];
    }

    /**
     * An account on the unlimited plan: its daily images, page scans and files read `null`,
     * never zero.
     *
     * @param  array<string, mixed>  $overrides  replaces these fields whole
     * @return array<string, mixed>
     */
    public static function maxAccount(array $overrides = []): array
    {
        return $overrides + self::account([
            'plan' => 'max',
            'limits' => [
                'images_per_day' => null,
                'scans_per_day' => null,
                'alt_texts_per_day' => 2000,
                'files_per_day' => null,
                'images_per_scan' => 150,
                'crawls_per_day' => 10,
                'pages_per_crawl' => 100,
                'monitors' => 25,
                'max_file_size' => 94371840,
                'max_megapixels' => 64,
                'compressions_per_minute' => 120,
                'scans_per_minute' => 10,
                'requests_per_minute' => 600,
                'max_batch' => 150,
            ],
            'usage' => [
                'images' => ['used' => 12, 'remaining' => null, 'resets_at' => '2026-10-02T09:30:00+00:00'],
                'scans' => ['used' => 1, 'remaining' => null, 'resets_at' => '2026-10-02T11:05:00+00:00'],
                'crawls' => ['used' => 0, 'remaining' => 10, 'resets_at' => null],
                'alt_texts' => ['used' => 12, 'remaining' => 1988, 'resets_at' => '2026-10-02T09:30:00+00:00'],
                'files' => ['used' => 6, 'remaining' => null, 'resets_at' => '2026-10-02T09:40:00+00:00'],
            ],
        ]);
    }

    /**
     * One page of a list, as the API answers one.
     *
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    public static function page(array $records, int $currentPage = 1, int $lastPage = 1, int $perPage = 15, ?int $total = null): array
    {
        return [
            'data' => $records,
            'meta' => [
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total ?? count($records),
            ],
        ];
    }

    /**
     * A file, as a download answers.
     */
    public static function file(string $contents = 'the file', string $name = 'image.webp', string $mimeType = 'image/webp'): ApiResponse
    {
        return self::response($contents, 200, [
            'content-type' => $mimeType,
            'content-length' => (string) strlen($contents),
            'content-disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }

    /**
     * What a delete answers.
     */
    public static function noContent(): ApiResponse
    {
        return self::response('', 204);
    }

    /**
     * A refusal, which ends in the exception the real one would. The codes are listed at
     * https://www.iminify.com/docs/errors.
     *
     * @param  array<string, list<string>>  $errors  on a validation failure, each field's messages
     */
    public static function refusal(string $code, int $status, string $message = 'Refused.', array $errors = [], ?int $retryAfter = null): ApiResponse
    {
        return self::answer(
            ['message' => $message, 'code' => $code] + ($errors === [] ? [] : ['errors' => $errors]),
            $status,
            $retryAfter === null ? [] : ['Retry-After' => (string) $retryAfter],
        );
    }

    /**
     * Any JSON answer, whole, for the limit headers or a status the others don't give.
     *
     * @param  array<array-key, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public static function answer(array $body, int $status = 200, array $headers = []): ApiResponse
    {
        return self::response((string) json_encode($body), $status, ['content-type' => 'application/json'] + $headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected static function response(string $body, int $status, array $headers = []): ApiResponse
    {
        return new ApiResponse(
            $status,
            array_change_key_case($headers, CASE_LOWER),
            Psr17FactoryDiscovery::findStreamFactory()->createStream($body),
        );
    }
}
