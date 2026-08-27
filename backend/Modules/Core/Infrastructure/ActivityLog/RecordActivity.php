<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\ActivityLog;

use Illuminate\Support\Facades\Log;
use Modules\Core\Infrastructure\Database\Models\ActivityLogModel;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Writes one activity row per domain event — ADR-017 D3, D4, D5.
 *
 * SUBSCRIBED TO EVERYTHING, AND THAT IS DELIBERATE. It is registered as a
 * wildcard listener, so it never names — and never imports — a single event
 * class from another module. Core describing events owned by seven other
 * modules through imports would be the cross-module dependency ADR-002 forbids
 * and the repaired boundary guard now detects. The registry decides what is
 * loggable by string, and everything else the framework dispatches is ignored.
 *
 * A FAILURE HERE MUST NOT BREAK THE THING BEING LOGGED. Same rule as
 * AuditLoggingMiddleware: observability is not business logic, and a full disk
 * or a deadlock must not turn a successful contestant update into a 500. The
 * write is wrapped and its failure is logged instead of thrown.
 */
final class RecordActivity
{
    /** @param array<int, mixed> $payload */
    public function handle(string $eventName, array $payload): void
    {
        $event = $payload[0] ?? null;

        // Laravel dispatches framework events as strings with array payloads,
        // and dozens of them per request. Only objects the registry names are
        // activity.
        if (! is_object($event) || ! ActivityEventRegistry::knows($eventName)) {
            return;
        }

        try {
            $this->record($eventName, $event);
        } catch (Throwable $e) {
            Log::warning('Activity log write failed.', [
                'event' => $eventName,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function record(string $eventName, object $event): void
    {
        if (! method_exists($event, 'toPayload')) {
            return;
        }

        /** @var array<string, mixed> $data */
        $data = $event->toPayload();

        [$entityType, $idKey] = ActivityEventRegistry::describe($eventName);

        $entityId = $data[$idKey] ?? null;

        if (! is_string($entityId) || $entityId === '') {
            // The registry says which key holds the id; if it is not there the
            // registry and the event have drifted, and a row with no subject
            // is worse than none. ActivityEventRegistryTest is what should
            // have caught this.
            Log::warning('Activity log skipped: registry key absent from payload.', [
                'event' => $eventName,
                'expected_key' => $idKey,
            ]);

            return;
        }

        [$actorId, $actorType] = $this->resolveActor($data);

        ActivityLogModel::query()->create([
            'id' => (string) Uuid::v7(),
            'action' => $this->actionOf($event, $eventName),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'payload' => $data,
            'correlation_id' => $this->correlationId(),
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);
    }

    /**
     * The event's own TYPE, which all 46 declare. Falls back to the class's
     * short name only if one ever does not.
     */
    private function actionOf(object $event, string $eventName): string
    {
        $type = defined($eventName.'::TYPE') ? constant($eventName.'::TYPE') : null;

        return is_string($type) && $type !== ''
            ? $type
            : strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename($event)) ?? $eventName);
    }

    /**
     * ADR-017 D4, in order: the event's own actor, then the request's user,
     * then `system`.
     *
     * The event is preferred because it is right even when there is no request
     * at all. `system` is written as a type, never as an invented user id, so a
     * reader can tell "a scheduled job did this" from "we lost track of who".
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string|null, 1: string}
     */
    private function resolveActor(array $data): array
    {
        $fromEvent = $data['by_user_id'] ?? null;

        if (is_string($fromEvent) && $fromEvent !== '') {
            return [$fromEvent, 'user'];
        }

        // A listener runs inside the request that dispatched the event, so the
        // authenticated user is reachable without the event carrying one.
        $fromRequest = auth()->id();

        if (is_string($fromRequest) && $fromRequest !== '') {
            return [$fromRequest, 'user'];
        }

        return [null, 'system'];
    }

    /**
     * Read from the request, never from the event — correlation is an HTTP
     * concept and a domain event has no business knowing about it (D5). Null
     * for anything dispatched by a console command or a queued job.
     */
    private function correlationId(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $id = request()->header('X-Correlation-ID');

        return is_string($id) && $id !== '' ? $id : null;
    }
}
