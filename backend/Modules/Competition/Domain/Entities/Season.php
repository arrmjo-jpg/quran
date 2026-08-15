<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Entities;

use InvalidArgumentException;
use Modules\Competition\Domain\Events\SeasonArchived;
use Modules\Competition\Domain\Events\SeasonCancelled;
use Modules\Competition\Domain\Events\SeasonCompetitionStarted;
use Modules\Competition\Domain\Events\SeasonCompleted;
use Modules\Competition\Domain\Events\SeasonCreated;
use Modules\Competition\Domain\Events\SeasonJudgingStarted;
use Modules\Competition\Domain\Events\SeasonRegistrationClosed;
use Modules\Competition\Domain\Events\SeasonRegistrationOpened;
use Modules\Competition\Domain\Events\SeasonRulesFrozen;
use Modules\Competition\Domain\Exceptions\IncompleteSeasonRulesException;
use Modules\Competition\Domain\Exceptions\InvalidSeasonTransitionException;
use Modules\Competition\Domain\Exceptions\SeasonAlreadyFrozenException;
use Modules\Competition\Domain\Services\SeasonRuleSnapshotFactory;
use Modules\Competition\Domain\Services\SeasonStateMachine;
use Modules\Competition\Domain\ValueObjects\ResolvedSeasonRules;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;
use Modules\Core\Domain\Concerns\HasDomainEvents;

/**
 * Season Aggregate Root
 *
 * Governing aggregate root for competition seasons, registration windows, and state transitions.
 * Everything that changes a season's own state — its lifecycle status, its
 * judging configuration, its archival — goes through a method on this
 * class. Nothing outside the domain layer mutates a Season's fields
 * directly.
 *
 * NOTE (Phase 2): openRegistration()/closeRegistration() below are the
 * original, still-live methods the current AdminSeasonController and
 * SeasonsTest exercise — intentionally left untouched so nothing already
 * working breaks. freeze() is the new richer replacement (state
 * transition + translation/rules completeness guard + rule snapshot,
 * all atomic in-memory) that Phase 3 will wire the repository/controller
 * to call instead, once real resolved lookup data exists to pass it. The
 * two are not yet unified to avoid touching Repositories/Controllers in
 * this phase, per the approved phase order.
 */
final class Season
{
    use HasDomainEvents;

    /** @var array<string, SeasonTranslation> locale => translation */
    private array $translationsByLocale;

    /**
     * @param  array<string, SeasonTranslation>  $translationsByLocale  Repository hydration path: reconstructs
     *                                                                  a previously `setTranslation()`-populated
     *                                                                  season without going through the guarded
     *                                                                  setter (which would reject a frozen season).
     */
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
        private ?int $minAge = null,
        private ?int $maxAge = null,
        private ?string $participationTypeId = null,
        private ?string $tajweedLevelId = null,
        private ?string $frozenAtIso = null,
        private ?string $archivedAtIso = null,
        private ?string $archivedByUserId = null,
        private ?string $archiveReason = null,
        array $translationsByLocale = [],
    ) {
        $this->translationsByLocale = $translationsByLocale;
    }

    public static function create(
        string $id,
        string $slug,
        int $year,
        string $regStartIso,
        string $regEndIso,
        string $startDateIso,
        string $endDateIso,
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

    /**
     * @deprecated Use freeze() instead — it performs this same transition
     * plus the mandatory translation/rules-completeness guard and the
     * rule snapshot, all atomically in-memory. Kept working, unchanged,
     * until AdminSeasonController is migrated to call freeze() with real
     * repository-resolved data (Phase 3) — do not call this from new code.
     */
    public function openRegistration(): void
    {
        if ($this->status !== 'draft') {
            throw new InvalidArgumentException("Cannot open registration from status: {$this->status}");
        }

        $this->status = 'registration_open';
        $this->isActive = true;
        $this->recordEvent(new SeasonRegistrationOpened($this->id, now()->toIso8601String()));
    }

    /**
     * @deprecated No replacement needed yet — closeRegistration() itself
     * is not part of what freeze() subsumes. Marked alongside
     * openRegistration() only because the two are conventionally called
     * as a pair; safe to keep calling until Phase 3 revisits the pair
     * together.
     */
    public function closeRegistration(): void
    {
        if ($this->status !== 'registration_open') {
            throw new InvalidArgumentException("Cannot close registration from status: {$this->status}");
        }

        $this->status = 'registration_closed';
        $this->recordEvent(new SeasonRegistrationClosed($this->id, now()->toIso8601String()));
    }

    public function isRegistrationOpen(): bool
    {
        return $this->status === 'registration_open';
    }

    public function getMinAge(): ?int
    {
        return $this->minAge;
    }

    public function getMaxAge(): ?int
    {
        return $this->maxAge;
    }

    public function getParticipationTypeId(): ?string
    {
        return $this->participationTypeId;
    }

    public function getTajweedLevelId(): ?string
    {
        return $this->tajweedLevelId;
    }

    public function getFrozenAtIso(): ?string
    {
        return $this->frozenAtIso;
    }

    public function isFrozen(): bool
    {
        return $this->frozenAtIso !== null;
    }

    public function getArchivedAtIso(): ?string
    {
        return $this->archivedAtIso;
    }

    public function getArchivedByUserId(): ?string
    {
        return $this->archivedByUserId;
    }

    public function getArchiveReason(): ?string
    {
        return $this->archiveReason;
    }

    public function getTranslation(string $locale): ?SeasonTranslation
    {
        return $this->translationsByLocale[$locale] ?? null;
    }

    /**
     * Set (or replace) one locale's translation. Blocked once the season
     * is frozen — competition content must not change while entries are
     * being accepted.
     */
    public function setTranslation(SeasonTranslation $translation): void
    {
        $this->assertMutableSettings('translation');
        $this->translationsByLocale[$translation->locale] = $translation;
    }

    public function setAgeRange(?int $minAge, ?int $maxAge): void
    {
        $this->assertMutableSettings('age_range');

        if ($minAge !== null && $maxAge !== null && $minAge > $maxAge) {
            throw new InvalidArgumentException("min_age ({$minAge}) cannot be greater than max_age ({$maxAge}).");
        }

        $this->minAge = $minAge;
        $this->maxAge = $maxAge;
    }

    public function setParticipationType(string $participationTypeId): void
    {
        $this->assertMutableSettings('participation_type_id');
        $this->participationTypeId = $participationTypeId;
    }

    public function setTajweedLevel(string $tajweedLevelId): void
    {
        $this->assertMutableSettings('tajweed_level_id');
        $this->tajweedLevelId = $tajweedLevelId;
    }

    /**
     * The unified draft -> registration_open transition: validates the
     * season is fully configured, transitions status via the state
     * machine, freezes it (frozen_at, and — from this point on —
     * assertMutableSettings() rejects any further change to age range,
     * participation type, tajweed level, or translations), and records a
     * SeasonRulesFrozen event carrying the resolved rule snapshot (version 1).
     * Persisting the season row and the season_rule_versions row from
     * that event, atomically in one transaction, is the repository/
     * application layer's job — this method only ever mutates in-memory
     * state and returns/records data, it never touches the database.
     */
    public function freeze(SeasonStateMachine $machine, SeasonRuleSnapshotFactory $snapshotFactory, ResolvedSeasonRules $rules): void
    {
        $this->assertTranslationsComplete();
        $this->assertRulesSelected();

        $this->status = $machine->transition($this->status, 'registration_open');
        $this->isActive = true;
        $this->frozenAtIso = now()->toIso8601String();

        $snapshot = $snapshotFactory->build(
            version: 1,
            frozenAtIso: $this->frozenAtIso,
            minAge: $this->minAge,
            maxAge: $this->maxAge,
            rules: $rules,
        );

        $this->recordEvent(new SeasonRegistrationOpened($this->id, $this->frozenAtIso));
        $this->recordEvent(new SeasonRulesFrozen($this->id, 1, $snapshot, $this->frozenAtIso));
    }

    public function startCompetition(SeasonStateMachine $machine): void
    {
        $this->status = $machine->transition($this->status, 'competition_running');
        $this->recordEvent(new SeasonCompetitionStarted($this->id, now()->toIso8601String()));
    }

    public function openJudging(SeasonStateMachine $machine): void
    {
        $this->status = $machine->transition($this->status, 'judging');
        $this->recordEvent(new SeasonJudgingStarted($this->id, now()->toIso8601String()));
    }

    public function complete(SeasonStateMachine $machine): void
    {
        $this->status = $machine->transition($this->status, 'completed');
        $this->recordEvent(new SeasonCompleted($this->id, now()->toIso8601String()));
    }

    /**
     * Archive a season that ran its full course. Only reachable from
     * 'completed' — see cancel() for ending a season before registration
     * ever opened.
     *
     * The state machine's own transition table allows 'draft' -> 'archived'
     * too (that edge exists for cancel()'s sake), so archive() cannot rely
     * on the machine alone to enforce "completed-only" — this explicit
     * guard is what actually does it.
     */
    public function archive(SeasonStateMachine $machine, ?string $reason, ?string $byUserId): void
    {
        if ($this->status !== 'completed') {
            throw new InvalidSeasonTransitionException($this->status, 'archived', []);
        }

        $this->status = $machine->transition($this->status, 'archived');
        $this->isActive = false;
        $this->archivedAtIso = now()->toIso8601String();
        $this->archivedByUserId = $byUserId;
        $this->archiveReason = $reason;
        $this->recordEvent(new SeasonArchived($this->id, reason: $reason, byUserId: $byUserId, occurredAt: $this->archivedAtIso));
    }

    /**
     * Cancel a season before it ever opens for registration. Only
     * reachable from 'draft' — the state machine itself enforces this
     * (registration_open/closed/etc. have no 'archived' transition), but
     * the mandatory reason is a domain-level rule on top of that.
     */
    public function cancel(SeasonStateMachine $machine, string $reason, ?string $byUserId): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required to cancel a season.');
        }

        $this->status = $machine->transition($this->status, 'archived');
        $this->isActive = false;
        $this->archivedAtIso = now()->toIso8601String();
        $this->archivedByUserId = $byUserId;
        $this->archiveReason = $reason;
        $this->recordEvent(new SeasonCancelled($this->id, reason: $reason, byUserId: $byUserId, occurredAt: $this->archivedAtIso));
    }

    private function assertMutableSettings(string $field): void
    {
        if ($this->isFrozen()) {
            throw new SeasonAlreadyFrozenException($this->id, $field);
        }
    }

    private function assertTranslationsComplete(): void
    {
        $missing = [];

        foreach (['ar', 'en', 'es'] as $locale) {
            $translation = $this->translationsByLocale[$locale] ?? null;
            if ($translation === null || ! $translation->isComplete()) {
                $missing[] = "translation.{$locale}";
            }
        }

        if ($missing !== []) {
            throw new IncompleteSeasonRulesException($missing);
        }
    }

    private function assertRulesSelected(): void
    {
        $missing = [];

        if ($this->minAge === null || $this->maxAge === null) {
            $missing[] = 'age_range';
        }

        if ($this->participationTypeId === null) {
            $missing[] = 'participation_type_id';
        }

        if ($this->tajweedLevelId === null) {
            $missing[] = 'tajweed_level_id';
        }

        if ($missing !== []) {
            throw new IncompleteSeasonRulesException($missing);
        }
    }
}
