<?php

declare(strict_types=1);

use Mozex\Iminify\Enums\AltTextStatus;
use Mozex\Iminify\Enums\CropAnchor;
use Mozex\Iminify\Enums\GroupFormat;
use Mozex\Iminify\Enums\GroupStatus;
use Mozex\Iminify\Enums\ImageStatus;
use Mozex\Iminify\Enums\Priority;
use Mozex\Iminify\Exceptions\ConflictException;
use Mozex\Iminify\Exceptions\UnexpectedResponseException;
use Mozex\Iminify\Exceptions\WaitTimeoutException;
use Mozex\Iminify\Http\ApiRequest;
use Mozex\Iminify\Http\Upload;
use Mozex\Iminify\Responses\Group;
use Mozex\Iminify\Testing\FakeClient;
use Mozex\Iminify\Testing\Fixtures;
use Mozex\Iminify\Tests\Fakes\RecordingHttpClient;

const GROUP_ID = '9d3c5c02-4e1f-4a6b-8d2c-3f5a7b9c1e24';

describe('opening a group', function () {
    it('sends what it was given, enums as their values, ephemeral and priority among them', function () {
        $upload = Upload::fromContents('source-bytes', 'IMG_2041.jpg');
        $http = new RecordingHttpClient([jsonResponse(['data' => Fixtures::openGroup()], 201)]);

        $group = clientAnswering($http)->groups()->create([
            'name' => 'IMG_2041',
            'source' => ['format' => 'jpg', 'size' => $upload->size(), 'width' => 2560, 'height' => 1707],
            'outputs' => [
                ['name' => 'full', 'width' => 2560, 'height' => 1707],
                ['name' => 'thumbnail', 'width' => 150, 'height' => 150, 'crop' => [CropAnchor::Center, CropAnchor::End]],
            ],
            'formats' => [GroupFormat::Original, GroupFormat::Webp],
            'ephemeral' => true,
            'priority' => Priority::Background,
            'alt_text_language' => null,
        ]);

        $request = $http->requests[0];

        expect($request->getMethod())->toBe('POST')
            ->and((string) $request->getUri())->toBe('https://www.iminify.com/api/v1/groups')
            ->and(json_decode((string) $request->getBody(), true))->toBe([
                'name' => 'IMG_2041',
                'source' => ['format' => 'jpg', 'size' => 12, 'width' => 2560, 'height' => 1707],
                'outputs' => [
                    ['name' => 'full', 'width' => 2560, 'height' => 1707],
                    ['name' => 'thumbnail', 'width' => 150, 'height' => 150, 'crop' => ['center', 'end']],
                ],
                'formats' => ['original', 'webp'],
                'ephemeral' => true,
                'priority' => 'background',
            ])
            ->and($group->status)->toBe(GroupStatus::AwaitingSource)
            ->and($group->uploadBy?->format(DATE_ATOM))->toBe('2026-10-01T10:40:00+00:00')
            ->and($group->source->format)->toBe('jpg')
            ->and($group->source->size)->toBe(2841205)
            ->and($group->source->width)->toBe(2560)
            ->and($group->source->height)->toBe(1707)
            ->and($group->settings->level)->toBe('smart')
            ->and($group->settings->formats)->toBe(['original', 'webp'])
            ->and($group->settings->keepMetadata)->toBeFalse()
            ->and($group->settings->ephemeral)->toBeTrue()
            ->and($group->settings->priority)->toBe('interactive')
            ->and($group->files)->toBe(6)
            ->and($group->outputs)->toBe([])
            ->and($group->altText)->toBeNull()
            ->and($group->downloadUrl)->toBeNull()
            ->and($group->receivedAt)->toBeNull()
            ->and($group->isInProgress())->toBeFalse()
            ->and($group->isFinished())->toBeFalse();
    });
});

describe('sending its source', function () {
    it('sends the file as a multipart form to the group given by id or record', function () {
        $fake = new FakeClient([Fixtures::groupInProgress(), Fixtures::groupInProgress()]);
        $upload = Upload::fromContents('source-bytes', 'IMG_2041.jpg');

        $group = $fake->groups()->sendSource(GROUP_ID, $upload);
        $fake->groups()->sendSource(Group::from(Fixtures::openGroup(['id' => 'from-the-record'])), $upload);

        $fake->assertSent('POST', 'groups/'.GROUP_ID.'/source', fn (ApiRequest $request): bool => $request->upload === $upload);
        $fake->assertSent('POST', 'groups/from-the-record/source');

        expect($group->status)->toBe(GroupStatus::InProgress)
            ->and($group->isInProgress())->toBeTrue();
    });

    it('takes a path, and refuses a stream with no name', function () {
        $path = (string) tempnam(sys_get_temp_dir(), 'iminify');
        $fake = new FakeClient([Fixtures::groupInProgress()]);

        try {
            $fake->groups()->sendSource(GROUP_ID, $path, 'IMG_2041.jpg');
        } finally {
            unlink($path);
        }

        $stream = fopen('php://memory', 'r+b');

        expect($fake->requests()[0]->upload?->name)->toBe('IMG_2041.jpg')
            ->and(fn () => $fake->groups()->sendSource(GROUP_ID, $stream))->toThrow(InvalidArgumentException::class, 'needs a name');

        fclose($stream);
    });

    it('reaches its exception when the group is closed', function () {
        $fake = new FakeClient([Fixtures::refusal('group_closed', 409)]);

        expect(fn () => $fake->groups()->sendSource(GROUP_ID, Upload::fromContents('a', 'a.jpg')))->toThrow(ConflictException::class);
    });
});

describe('a finished group', function () {
    it('reads each output in each format', function () {
        $group = (new FakeClient([Fixtures::group()]))->groups()->get(GROUP_ID);

        expect($group->id)->toBe(GROUP_ID)
            ->and($group->name)->toBe('IMG_2041')
            ->and($group->status)->toBe(GroupStatus::Finished)
            ->and($group->isFinished())->toBeTrue()
            ->and($group->uploadBy)->toBeNull()
            ->and($group->outputs)->toHaveCount(6)
            ->and($group->outputs[1]->name)->toBe('full')
            ->and($group->outputs[1]->format)->toBe('webp')
            ->and($group->outputs[1]->status)->toBe(ImageStatus::Completed)
            ->and($group->outputs[1]->file)->toBe('full.webp')
            ->and($group->outputs[1]->width)->toBe(2560)
            ->and($group->outputs[1]->height)->toBe(1707)
            ->and($group->outputs[1]->size)->toBe(401226)
            ->and($group->outputs[1]->replace)->toBeTrue()
            ->and($group->outputs[5]->file)->toBeNull()
            ->and($group->outputs[5]->replace)->toBeFalse()
            ->and($group->altText?->status)->toBe(AltTextStatus::Written)
            ->and($group->downloadUrl)->toEndWith('/download')
            ->and($group->createdAt->format(DATE_ATOM))->toBe('2026-10-01T09:40:00+00:00')
            ->and($group->receivedAt?->format(DATE_ATOM))->toBe('2026-10-01T09:40:21+00:00')
            ->and($group->toArray())->toBe(Fixtures::group());
    });

    it('refuses an answer it can not read', function (array $answer) {
        expect(fn () => (new FakeClient([$answer]))->groups()->get(GROUP_ID))->toThrow(UnexpectedResponseException::class);
    })->with([
        'no id' => [array_diff_key(Fixtures::group(), ['id' => true])],
        'no source' => [array_diff_key(Fixtures::group(), ['source' => true])],
        'no outputs' => [array_diff_key(Fixtures::group(), ['outputs' => true])],
        'an output without its name' => [Fixtures::group(['outputs' => [['format' => 'webp', 'status' => 'completed', 'replace' => true]]])],
    ]);

    it('downloads its zip and deletes it', function () {
        $fake = new FakeClient([Fixtures::file('zip-bytes', 'iminify-group.zip', 'application/zip'), Fixtures::noContent()]);

        $download = $fake->groups()->download(GROUP_ID);
        $fake->groups()->delete(GROUP_ID);

        $fake->assertSent('GET', 'groups/'.GROUP_ID.'/download');
        $fake->assertSent('DELETE', 'groups/'.GROUP_ID);

        expect($download->contents())->toBe('zip-bytes')
            ->and($download->name)->toBe('iminify-group.zip');
    });
});

describe('waiting for a group', function () {
    it('reads until its files are made', function () {
        $fake = new FakeClient([
            Fixtures::groupInProgress(),
            Fixtures::groupInProgress(),
            Fixtures::group(),
        ]);

        expect($fake->groups()->wait(GROUP_ID)->status)->toBe(GroupStatus::Finished);

        $fake->assertSentTimes('GET', 'groups/*', 3);
    });

    it('ends for what nothing but the caller would move on, whatever the status', function (string $status, GroupStatus $expected) {
        $fake = new FakeClient([Fixtures::openGroup(['status' => $status])]);

        expect($fake->groups()->wait(GROUP_ID)->status)->toBe($expected);

        $fake->assertSentTimes('GET', 'groups/*', 1);
    })->with([
        'still waiting for its source' => ['awaiting_source', GroupStatus::AwaitingSource],
        'expired' => ['expired', GroupStatus::Expired],
        'a status this version does not know' => ['paused', GroupStatus::Unknown],
    ]);

    it('runs out of time while it is at work', function () {
        $fake = new FakeClient(array_fill(0, 200, Fixtures::groupInProgress()));

        expect(fn () => $fake->groups()->wait(GROUP_ID, timeout: 3))->toThrow(WaitTimeoutException::class, 'The group');
    });

    it('waits on for what is asked, and an unfinished group ends it all the same', function () {
        $pending = ['status' => 'pending', 'text' => null, 'language' => 'en', 'suggested_name' => null];
        $fake = new FakeClient([Fixtures::group(['alt_text' => $pending]), Fixtures::group()]);
        $written = fn (Group $group): bool => $group->altText?->status === AltTextStatus::Written;

        expect($fake->groups()->wait(GROUP_ID, until: $written)->altText?->text)->toBe('A red tram crossing a stone bridge at dusk');

        $fake->assertSentTimes('GET', 'groups/*', 2);

        $expired = new FakeClient([Fixtures::openGroup(['status' => 'expired'])]);

        expect($expired->groups()->wait(GROUP_ID, until: $written)->status)->toBe(GroupStatus::Expired);
    });
});
