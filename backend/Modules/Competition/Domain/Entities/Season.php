<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Entities;

use InvalidArgumentException;
use Modules\Competition\Domain\Events\RegistrationClosed;
use Modules\Competition\Domain\Events\RegistrationOpened;
use Modules\Competition\Domain\Events\SeasonCreated;

/**
 * Season Aggregate Root
 *
 * Governing aggregate root for competition seasons, registration windows, and state transitions.
 */
final class Season
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    public function __construct(
        public readonly string $id,
        private string $slug,
        private int $year,
        private string $registrationStartIso,
        private string $registrationEndIso,
        private string $startDateIso,
        private string $endDateIso,
        private string $status = 'draft', // 'draft', 'registration_open', 'registration_closed', 'active', 'completed'
        private bool $isActive = false,
        private array $translations = [],
    ) {}

    public static function create(
        string $id,
        string $slug,
        int $year,
        string $regStartIso,
        string $regEndIso,
        string $startDateIso,
        string $endDateIso,
        array $translations = []
    ): self {
        $season = new self(
            id: $id,
            slug: $slug,
            year: $year,
            registrationStartIso: $regStartIso,
            registrationEndIso: $regEndIso,
            startDateIso: $startDateIso,
            endDateIso: $endDateIso,
            status: 'draft',
            isActive: false,
            translations: $translations
        );

        $season->recordEvent(new SeasonCreated($id, $slug, $year, now()->toIso8601String()));

        return $season;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getRegistrationStartIso(): string
    {
        return $this->registrationStartIso;
    }

    public function getRegistrationEndIso(): string
    {
        return $this->registrationEndIso;
    }

    public function getStartDateIso(): string
    {
        return $this->startDateIso;
    }

    public function getEndDateIso(): string
    {
        return $this->endDateIso;
    }

    public function openRegistration(): void
    {
        if ($this->status !== 'draft') {
            throw new InvalidArgumentException("Cannot open registration from status: {$this->status}");
        }

        $this->status = 'registration_open';
        $this->isActive = true;
        $this->recordEvent(new RegistrationOpened($this->id, now()->toIso8601String()));
    }

    public function closeRegistration(): void
    {
        if ($this->status !== 'registration_open') {
            throw new InvalidArgumentException("Cannot close registration from status: {$this->status}");
        }

        $this->status = 'registration_closed';
        $this->recordEvent(new RegistrationClosed($this->id, now()->toIso8601String()));
    }

    public function isRegistrationOpen(): bool
    {
        return $this->status === 'registration_open';
    }

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
