<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

/**
 * Shared domain-event recording for aggregate roots. Pure PHP, zero
 * framework/infrastructure dependency — safe for any module's Domain
 * layer. Every aggregate that records events (Season, Contestant,
 * Application, Evaluation, Judge, ...) should use this instead of
 * duplicating the same three members.
 */
trait HasDomainEvents
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    /** @return array<int, object> */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    protected function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
