<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Repositories;

use Modules\Core\Domain\ReadModels\ActivityEntry;
use Modules\Core\Domain\Repositories\ActivityLogRepositoryContract;
use Modules\Core\Infrastructure\Database\Models\ActivityLogModel;

final class ActivityLogRepository implements ActivityLogRepositoryContract
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    public function paginate(array $criteria): array
    {
        $query = ActivityLogModel::query();

        // The pair travels together — the request layer refuses one without
        // the other, because an id alone could collide across tables.
        if (! empty($criteria['entity_type']) && ! empty($criteria['entity_id'])) {
            $query->where('entity_type', $criteria['entity_type'])
                ->where('entity_id', $criteria['entity_id']);
        }

        if (! empty($criteria['actor_id'])) {
            $query->where('actor_id', $criteria['actor_id']);
        }

        if (! empty($criteria['action'])) {
            $query->where('action', $criteria['action']);
        }

        if (! empty($criteria['correlation_id'])) {
            $query->where('correlation_id', $criteria['correlation_id']);
        }

        // Windowed on occurred_at, never created_at: a backdated entry belongs
        // to the period it describes, not the one it was typed in.
        if (! empty($criteria['from'])) {
            $query->where('occurred_at', '>=', $criteria['from']);
        }

        if (! empty($criteria['to'])) {
            $query->where('occurred_at', '<=', $criteria['to']);
        }

        $perPage = min(max((int) ($criteria['per_page'] ?? self::DEFAULT_PER_PAGE), 1), self::MAX_PER_PAGE);

        $paginator = $query
            ->orderByDesc('occurred_at')
            // Tie-broken by id: one request dispatches several events in the
            // same second, and without this a page boundary can drop or repeat
            // a row.
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: max((int) ($criteria['page'] ?? 1), 1));

        return [
            'items' => array_map(
                fn (ActivityLogModel $row): ActivityEntry => new ActivityEntry(
                    id: (string) $row->id,
                    action: $row->action,
                    entityType: $row->entity_type,
                    entityId: (string) $row->entity_id,
                    actorId: $row->actor_id === null ? null : (string) $row->actor_id,
                    actorType: $row->actor_type,
                    payload: is_array($row->payload) ? $row->payload : [],
                    correlationId: $row->correlation_id === null ? null : (string) $row->correlation_id,
                    occurredAt: $row->occurred_at?->toIso8601String(),
                    recordedAt: $row->created_at?->toIso8601String(),
                ),
                $paginator->items()
            ),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
