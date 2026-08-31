<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Database\Repositories;

use Modules\Notifications\Domain\Entities\NotificationLog;
use Modules\Notifications\Domain\Repositories\NotificationLogRepositoryContract;
use Modules\Notifications\Domain\ValueObjects\NotificationChannel;
use Modules\Notifications\Domain\ValueObjects\NotificationId;
use Modules\Notifications\Infrastructure\Database\Models\NotificationLogModel;

final class NotificationLogRepository implements NotificationLogRepositoryContract
{
    public function findOrFail(NotificationId $id): NotificationLog
    {
        $model = NotificationLogModel::query()->findOrFail($id->value);

        return $this->toDomain($model);
    }

    public function findByUserId(string $userId): array
    {
        $models = NotificationLogModel::query()->where('user_id', $userId)->get();

        return $models->map(fn (NotificationLogModel $m): NotificationLog => $this->toDomain($m))->toArray();
    }

    public function save(NotificationLog $log): void
    {
        NotificationLogModel::query()->updateOrCreate(
            ['id' => $log->id->value],
            [
                'user_id' => $log->userId,
                'channel' => (string) $log->channel,
                'template_key' => $log->templateKey,
                'payload' => $log->payload,
                'status' => $log->getStatus(),
                // ADR-020 D2/D10. Both were absent, so a round trip through
                // save() silently discarded markSent()'s timestamp and
                // markFailed()'s reason -- toDomain() read columns that
                // nothing ever wrote.
                'sent_at' => $log->getSentAtIso(),
                'error' => $log->getErrorMessage(),
            ]
        );
    }

    private function toDomain(NotificationLogModel $model): NotificationLog
    {
        return new NotificationLog(
            id: new NotificationId($model->id),
            userId: $model->user_id,
            channel: new NotificationChannel($model->channel),
            templateKey: $model->template_key,
            payload: $model->payload ?? [],
            status: $model->status,
            sentAtIso: $model->sent_at?->toIso8601String(),
            errorMessage: $model->error
        );
    }
}
