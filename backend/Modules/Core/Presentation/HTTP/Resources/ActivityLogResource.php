<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Infrastructure\Database\Models\ActivityLogModel;

/**
 * One activity row — ADR-017 D3.
 *
 * The contract is the ADR's, not AuditExplorerPage's. That screen invented
 * `user_name`, `user_role` and `result` for an endpoint that never existed,
 * and two of the three are deliberately absent here:
 *
 *   `result` belongs to HTTP, where a request can fail. A domain event that
 *   has been dispatched has already happened; there is no unsuccessful one to
 *   report. audit_logs records failure, and correlation_id joins the two.
 *
 *   `user_role` would be a snapshot that stops being true the moment the role
 *   changes, and resolving it per row is a query per row. A reader who needs
 *   it joins against live data deliberately.
 *
 * The actor's NAME is carried, resolved once for the whole page by the
 * controller rather than per row — a display convenience that is not stored,
 * so it cannot go stale in the table.
 */
final class ActivityLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ActivityLogModel $row */
        $row = $this->resource;

        return [
            'id' => (string) $row->id,
            'action' => $row->action,

            'entity_type' => $row->entity_type,
            'entity_id' => (string) $row->entity_id,

            'actor_id' => $row->actor_id === null ? null : (string) $row->actor_id,

            // 'user' or 'system'. Null actor_id with type 'system' means a
            // console command or a scheduled job did this — which is a
            // different statement from "we did not record who".
            'actor_type' => $row->actor_type,

            // The event's own payload, verbatim. Events decide what is safe to
            // carry; nothing is added here from the database.
            'payload' => $row->payload,

            'correlation_id' => $row->correlation_id === null ? null : (string) $row->correlation_id,

            // Both, and they are not the same fact.
            'occurred_at' => $row->occurred_at?->toIso8601String(),
            'recorded_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
