<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Entities;

use InvalidArgumentException;
use Modules\Competition\Domain\Events\StageCreated;
use Modules\Competition\Domain\Exceptions\IncompleteSeasonRulesException;
use Modules\Competition\Domain\ValueObjects\StageTranslation;
use Modules\Core\Domain\Concerns\HasDomainEvents;

/**
 * Stage Aggregate Root
 *
 * Governs competition stage ordering, schedules, and active evaluation
 * template linkage. Everything that changes a stage's own state goes
 * through a method on this class.
 *
 * Note on mutability: a stage carries no frozen flag of its own. Whether
 * it may still be edited is a property of its *season* (a frozen season's
 * stages are locked), and a stage aggregate never loads its season — so
 * that guard lives in the Use Cases, which have both in hand. This class
 * only enforces what it can see: its own field-level invariants.
 */
final class Stage
{
    use HasDomainEvents;

    public const TYPES = ['preliminary', 'semi_final', 'final'];

    /** @var array<string, StageTranslation> locale => translation */
    private array $translationsByLocale;

    /**
     * @param  array<string, StageTranslation>  $translationsByLocale  Repository hydration path, mirroring Season's.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $seasonId,
        private int $stageNumber,
        private string $type,
        private string $startDateIso,
        private string $endDateIso,
        private ?string $evaluationTemplateId = null,
        private string $status = 'pending', // 'pending', 'active', 'completed'
        array $translationsByLocale = [],
    ) {
        $this->assertValidStageNumber($stageNumber);
        $this->assertValidType($type);
        $this->assertValidSchedule($startDateIso, $endDateIso);

        $this->translationsByLocale = $translationsByLocale;
    }

    public static function create(
        string $id,
        string $seasonId,
        int $stageNumber,
        string $type,
        string $startDateIso,
        string $endDateIso,
        ?string $evaluationTemplateId = null,
    ): self {
        $stage = new self(
            id: $id,
            seasonId: $seasonId,
            stageNumber: $stageNumber,
            type: $type,
            startDateIso: $startDateIso,
            endDateIso: $endDateIso,
            evaluationTemplateId: $evaluationTemplateId,
            status: 'pending',
        );

        $stage->recordEvent(new StageCreated($id, $seasonId, $stageNumber, $type, now()->toIso8601String()));

        return $stage;
    }

    public function getStageNumber(): int
    {
        return $this->stageNumber;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStartDateIso(): string
    {
        return $this->startDateIso;
    }

    public function getEndDateIso(): string
    {
        return $this->endDateIso;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getEvaluationTemplateId(): ?string
    {
        return $this->evaluationTemplateId;
    }

    public function setStageNumber(int $stageNumber): void
    {
        $this->assertValidStageNumber($stageNumber);
        $this->stageNumber = $stageNumber;
    }

    public function setType(string $type): void
    {
        $this->assertValidType($type);
        $this->type = $type;
    }

    public function setSchedule(string $startDateIso, string $endDateIso): void
    {
        $this->assertValidSchedule($startDateIso, $endDateIso);
        $this->startDateIso = $startDateIso;
        $this->endDateIso = $endDateIso;
    }

    public function setEvaluationTemplate(?string $evaluationTemplateId): void
    {
        $this->evaluationTemplateId = $evaluationTemplateId;
    }

    public function getTranslation(string $locale): ?StageTranslation
    {
        return $this->translationsByLocale[$locale] ?? null;
    }

    public function setTranslation(StageTranslation $translation): void
    {
        $this->translationsByLocale[$translation->locale] = $translation;
    }

    /**
     * The stage-level counterpart of Season::assertTranslationsComplete().
     * Not called during normal editing — a stage is allowed to sit
     * half-translated while its season is still a draft. This exists for
     * the season-freeze path to call once that gate is wired up.
     */
    public function assertTranslationsComplete(): void
    {
        $missing = [];

        foreach (['ar', 'en', 'es'] as $locale) {
            $translation = $this->translationsByLocale[$locale] ?? null;
            if ($translation === null || ! $translation->isComplete()) {
                $missing[] = "stage.{$this->stageNumber}.translation.{$locale}";
            }
        }

        if ($missing !== []) {
            throw new IncompleteSeasonRulesException($missing);
        }
    }

    public function activate(): void
    {
        $this->status = 'active';
    }

    public function complete(): void
    {
        $this->status = 'completed';
    }

    private function assertValidStageNumber(int $stageNumber): void
    {
        if ($stageNumber < 1) {
            throw new InvalidArgumentException("stage_number must be 1 or greater, got {$stageNumber}.");
        }
    }

    private function assertValidType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported stage type: {$type}.");
        }
    }

    private function assertValidSchedule(string $startDateIso, string $endDateIso): void
    {
        if (strtotime($startDateIso) >= strtotime($endDateIso)) {
            throw new InvalidArgumentException("A stage's start_date ({$startDateIso}) must be before its end_date ({$endDateIso}).");
        }
    }
}
