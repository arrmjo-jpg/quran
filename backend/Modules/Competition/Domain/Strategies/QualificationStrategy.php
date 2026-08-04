<?php

declare(strict_types=1);

namespace Modules\Competition\Domain\Strategies;

/**
 * QualificationStrategy
 *
 * Encapsulates score threshold qualification evaluation.
 */
final class QualificationStrategy
{
    public function execute(float $score, float $threshold = 80.0): string
    {
        return $score >= $threshold ? 'qualified' : 'eliminated';
    }
}
