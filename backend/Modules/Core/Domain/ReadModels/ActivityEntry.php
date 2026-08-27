<?php

declare(strict_types=1);

namespace Modules\Core\Domain\ReadModels;

/**
 * One activity row, as anything outside Infrastructure may see it.
 *
 * A READ MODEL, NOT AN AGGREGATE, and the distinction is why this is not an
 * entity. Nothing enforces an invariant on it, nothing mutates it, and there
 * is no behaviour to protect: a log row is written once by a listener and read
 * forever after. Giving it an aggregate's shape would promise a lifecycle it
 * does not have.
 *
 * It exists so that ActivityLogController can render the feed without
 * importing ActivityLogModel. A controller reaching past its own application
 * layer into Eloquent is the ADR-009/ADR-012 violation the repaired
 * architecture guard now detects — and it detected this one, on the first run
 * after the endpoint was written.
 */
final readonly class ActivityEntry
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $action,
        public string $entityType,
        public string $entityId,
        public ?string $actorId,
        public string $actorType,
        public array $payload,
        public ?string $correlationId,
        public ?string $occurredAt,
        public ?string $recordedAt,
    ) {}
}
