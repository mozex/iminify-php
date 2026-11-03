<?php

declare(strict_types=1);

namespace Mozex\Iminify\Enums;

/**
 * What a webhook is telling you. `Ping` is the test event sent from Settings > Webhooks.
 *
 * `Unknown` stands for a value this version of the client doesn't know yet.
 */
enum WebhookEventType: string
{
    case ImageCompleted = 'image.completed';
    case ImageFailed = 'image.failed';
    case ImageResponsiveFinished = 'image.responsive_finished';
    case ScanCompleted = 'scan.completed';
    case ScanFailed = 'scan.failed';
    case Ping = 'ping';
    case Unknown = 'unknown';
}
