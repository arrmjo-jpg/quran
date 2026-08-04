<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Entities;

use InvalidArgumentException;

/**
 * EvaluationTemplate Aggregate Root
 *
 * Dynamic rubric configurator per ADR-010.
 * Enforces that criteria weight percentages sum to exactly 100.00%.
 */
final class EvaluationTemplate
{
    /**
     * @param  array<int, array{id: string, code: string, name: string, max_score: float, weight_percent: float}>  $criteria
     */
    public function __construct(
        public readonly string $id,
        public readonly string $seasonId,
        public readonly string $name,
        public readonly float $maxTotalScore = 100.0,
        private array $criteria = [],
        private bool $isActive = true,
    ) {
        $this->guardValidWeights();
    }

    /**
     * @return array<int, array{id: string, code: string, name: string, max_score: float, weight_percent: float}>
     */
    public function getCriteria(): array
    {
        return $this->criteria;
    }

    public function getCriterionByCode(string $code): ?array
    {
        foreach ($this->criteria as $criterion) {
            if ($criterion['code'] === $code) {
                return $criterion;
            }
        }

        return null;
    }

    private function guardValidWeights(): void
    {
        if (empty($this->criteria)) {
            return;
        }

        $totalWeight = array_sum(array_column($this->criteria, 'weight_percent'));

        if (abs($totalWeight - 100.0) > 0.01) {
            throw new InvalidArgumentException("Criteria weights must sum to exactly 100%. Current sum: {$totalWeight}%");
        }
    }
}
