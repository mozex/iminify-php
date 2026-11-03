<?php

declare(strict_types=1);

use Mozex\Iminify\Enums\AltTextStatus;
use Mozex\Iminify\Enums\ContentCredentialsStatus;
use Mozex\Iminify\Enums\ImageStatus;
use Mozex\Iminify\Enums\ResponsiveStatus;
use Mozex\Iminify\Enums\SizeLimitStatus;
use Mozex\Iminify\Responses\Image;
use Mozex\Iminify\Responses\Page;
use Mozex\Iminify\Responses\RateLimit;
use Mozex\Iminify\Testing\Fixtures;

describe('an image', function () {
    it('reads every field of a finished one', function () {
        $rateLimit = new RateLimit(300, 299);
        $image = Image::from(Fixtures::image(['folder' => 'holiday/beach', 'share_url' => 'https://www.iminify.com/shared/abc', 'source_url' => 'https://example.com/team-offsite.jpg']), $rateLimit);

        expect($image->id)->toBe('9d3c5b8e-6f0a-4c1e-9b7d-2a4e8f1c6b35')
            ->and($image->status)->toBe(ImageStatus::Completed)
            ->and($image->name)->toBe('team-offsite.webp')
            ->and($image->original->format)->toBe('jpg')
            ->and($image->original->size)->toBe(2841205)
            ->and($image->optimized->format)->toBe('webp')
            ->and($image->optimized->width)->toBe(1600)
            ->and($image->optimized->height)->toBe(1200)
            ->and($image->optimized->downloadUrl)->toEndWith('/download')
            ->and($image->savedBytes)->toBe(2628328)
            ->and($image->savedPercent)->toBe(92.51)
            ->and($image->settings->level)->toBe('smart')
            ->and($image->settings->format)->toBe('webp')
            ->and($image->settings->keepMetadata)->toBeFalse()
            ->and($image->settings->width)->toBe(1600)
            ->and($image->settings->sizeLimit)->toBe(250)
            ->and($image->settings->responsiveWidths)->toBe([480, 768, 1200, 1600])
            ->and($image->sizeLimitStatus)->toBe(SizeLimitStatus::Under)
            ->and($image->contentCredentials->status)->toBe(ContentCredentialsStatus::Removed)
            ->and($image->contentCredentials->generativeAi)->toBeFalse()
            ->and($image->altText->status)->toBe(AltTextStatus::Written)
            ->and($image->altText->text)->toBe('Six colleagues laughing around a picnic table on a lakeside lawn')
            ->and($image->altText->language)->toBe('en')
            ->and($image->altText->suggestedName)->toBe('colleagues-picnic-table-lakeside')
            ->and($image->responsive->status)->toBe(ResponsiveStatus::Ready)
            ->and($image->responsive->files[0]->width)->toBe(480)
            ->and($image->responsive->files[0]->name)->toBe('team-offsite-480w.webp')
            ->and($image->responsive->downloadUrl)->toEndWith('/responsive.zip')
            ->and($image->responsive->html)->toContain('<img')
            ->and($image->sourceUrl)->toBe('https://example.com/team-offsite.jpg')
            ->and($image->folder)->toBe('holiday/beach')
            ->and($image->shareUrl)->toEndWith('/shared/abc')
            ->and($image->createdAt->format(DATE_ATOM))->toBe(Fixtures::image()['created_at'])
            ->and($image->updatedAt->format(DATE_ATOM))->toBe(Fixtures::image()['updated_at'])
            ->and($image->rateLimit)->toBe($rateLimit)
            ->and($image->isFinished())->toBeTrue()
            ->and($image->hasResult())->toBeTrue();
    });

    it('reads a queued one, whose optional parts are null', function () {
        $image = Image::from(Fixtures::queuedImage(['settings' => ['level' => 'smart', 'format' => null, 'keep_metadata' => false], 'alt_text' => null, 'responsive' => null]));

        expect($image->status)->toBe(ImageStatus::Queued)
            ->and($image->optimized)->toBeNull()
            ->and($image->savedBytes)->toBeNull()
            ->and($image->savedPercent)->toBeNull()
            ->and($image->settings->format)->toBeNull()
            ->and($image->settings->responsiveWidths)->toBeNull()
            ->and($image->sizeLimitStatus)->toBeNull()
            ->and($image->contentCredentials)->toBeNull()
            ->and($image->altText)->toBeNull()
            ->and($image->responsive)->toBeNull()
            ->and($image->scanId)->toBeNull()
            ->and($image->sourceUrl)->toBeNull()
            ->and($image->folder)->toBeNull()
            ->and($image->shareUrl)->toBeNull()
            ->and($image->rateLimit->limit)->toBeNull()
            ->and($image->isFinished())->toBeFalse()
            ->and($image->hasResult())->toBeFalse();
    });

    it('reads a whole-number percentage as a float', function () {
        expect(Image::from(Fixtures::image(['saved_percent' => 80]))->savedPercent)->toBe(80.0);
    });

    it('hands the answer back as sent, a field this client does not know included', function () {
        $attributes = Fixtures::image(['added_later' => ['a' => 1]]);

        expect(Image::from($attributes)->toArray())->toBe($attributes);
    });

    it('reads a status it does not know as unknown rather than failing', function () {
        $image = Image::from(Fixtures::image([
            'status' => 'resizing',
            'size_limit_status' => 'close',
            'content_credentials' => ['status' => 'rewritten', 'generative_ai' => false],
            'alt_text' => ['status' => 'translating', 'text' => null, 'language' => null, 'suggested_name' => null],
            'responsive' => ['status' => 'partial', 'files' => [], 'download_url' => null, 'html' => null],
        ]));

        expect($image->status)->toBe(ImageStatus::Unknown)
            ->and($image->sizeLimitStatus)->toBe(SizeLimitStatus::Unknown)
            ->and($image->contentCredentials->status)->toBe(ContentCredentialsStatus::Unknown)
            ->and($image->altText->status)->toBe(AltTextStatus::Unknown)
            ->and($image->responsive->status)->toBe(ResponsiveStatus::Unknown);
    });

    it('counts as finished once it is no longer queued or optimizing', function (string $status, bool $finished) {
        expect(Image::from(Fixtures::image(['status' => $status]))->isFinished())->toBe($finished);
    })->with([
        ['queued', false],
        ['optimizing', false],
        ['completed', true],
        ['already-optimized', true],
        ['failed', true],
        ['cancelled', true],
    ]);
});

describe('a page', function () {
    it('holds its records and says which page comes next', function () {
        $body = [
            'data' => [Fixtures::image(['id' => 'a']), Fixtures::image(['id' => 'b'])],
            'links' => ['next' => 'https://www.iminify.com/api/v1/images?page=3'],
            'meta' => ['current_page' => 2, 'last_page' => 3, 'per_page' => 2, 'total' => 5],
        ];
        $page = Page::from($body, Image::from(...), new RateLimit(60, 59));

        expect($page)->toHaveCount(2)
            ->and(array_map(fn (Image $image): string => $image->id, iterator_to_array($page)))->toBe(['a', 'b'])
            ->and($page->items[1]->id)->toBe('b')
            ->and($page->currentPage)->toBe(2)
            ->and($page->lastPage)->toBe(3)
            ->and($page->perPage)->toBe(2)
            ->and($page->total)->toBe(5)
            ->and($page->nextPage)->toBe(3)
            ->and($page->rateLimit->limit)->toBe(60)
            ->and($page->toArray())->toBe($body);
    });

    it('has no next page on the last one, or on an empty list', function () {
        $last = Page::from(['data' => [], 'meta' => ['current_page' => 3, 'last_page' => 3]], Image::from(...));
        $empty = Page::from(['data' => []], Image::from(...));

        expect($last->nextPage)->toBeNull()
            ->and($empty->nextPage)->toBeNull()
            ->and($empty->items)->toBe([])
            ->and($empty->total)->toBe(0);
    });
});
