<?php

declare(strict_types=1);

namespace Modules\Evaluations\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class EvaluationScoreValue
{
    public function __construct(
        public float $value,
        public float $maxScore = 100.0,
    ) {
        if ($value < 0.0 || $value > $maxScore) {
            throw new InvalidArgumentException("Evaluation score must be between 0.0 and {$maxScore}. Got: {$value}");
        }
    }
}
