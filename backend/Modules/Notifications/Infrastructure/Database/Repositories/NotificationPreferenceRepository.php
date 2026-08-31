<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Database\Repositories;

use Illuminate\Support\Str;
use Modules\Notifications\Domain\Repositories\NotificationPreferenceRepositoryContract;
use Modules\Notifications\Infrastructure\Database\Models\NotificationPreferenceModel;

final class NotificationPreferenceRepository implements NotificationPreferenceRepositoryContract
{
    public function declinedTypesFor(string $userId): array
    {
        return NotificationPreferenceModel::query()
            ->where('user_id', $userId)
            ->where('enabled', false)
            ->pluck('notification_type')
            ->all();
    }

    public function set(string $userId, string $type, bool $enabled): void
    {
        // RE-ENABLING DELETES THE ROW RATHER THAN WRITING `true`.
        //
        // Absence already means enabled, so a row saying `enabled = true` would
        // be a second way to express the default -- and two representations of
        // one state is how a query ends up needing to remember which it is
        // looking at. It also keeps the table to what it is for: the list of
        // things somebody has actively turned off.
        if ($enabled) {
            NotificationPreferenceModel::query()
                ->where('user_id', $userId)
                ->where('notification_type', $type)
                ->delete();

            return;
        }

        // NOT updateOrCreate. Its attribute array would be applied on update
        // too, so passing a fresh `id` there rewrites the primary key of a row
        // that already exists -- the kind of quiet key churn that breaks
        // anything holding a reference to it.
        $existing = NotificationPreferenceModel::query()
            ->where('user_id', $userId)
            ->where('notification_type', $type)
            ->first();

        if ($existing !== null) {
            $existing->update(['enabled' => false]);

            return;
        }

        NotificationPreferenceModel::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'notification_type' => $type,
            'enabled' => false,
        ]);
    }
}
