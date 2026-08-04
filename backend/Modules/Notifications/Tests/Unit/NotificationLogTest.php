<?php

declare(strict_types=1);

use Modules\Notifications\Domain\Entities\NotificationLog;
use Modules\Notifications\Domain\ValueObjects\NotificationChannel;
use Modules\Notifications\Domain\ValueObjects\NotificationId;

uses()->group('notifications', 'unit', 'domain');

test('notification log aggregate tracks dispatch state transition correctly', function (): void {
    $log = NotificationLog::create(
        id: NotificationId::generate(),
        userId: fake()->uuid(),
        channel: new NotificationChannel('email'),
        templateKey: 'application_submitted_confirmation',
        payload: ['application_number' => 'APP-2026-001']
    );

    expect($log->getStatus())->toBe('queued');
    expect((string) $log->channel)->toBe('email');

    $log->markSent(now()->toIso8601String());
    expect($log->getStatus())->toBe('sent');
});
