<?php

declare(strict_types=1);

namespace Modules\Contestants\Domain\Services;

use Modules\Contestants\Domain\Entities\Contestant;

/**
 * EligibilityService
 *
 * Domain Service verifying contestant age eligibility for competition seasons per ADR-010.
 */
final class EligibilityService
{
    /**
     * @return array{is_eligible: bool, age: int, reason: ?string}
     */
    public function checkEligibility(Contestant $contestant, string $seasonStartIso, int $minAge = 6, int $maxAge = 40): array
    {
        $age = $contestant->getDateOfBirth()->calculateAgeAt($seasonStartIso);

        if ($age < $minAge) {
            return [
                'is_eligible' => false,
                'age' => $age,
                'reason' => "Contestant age ({$age}) is below minimum required age ({$minAge}).",
            ];
        }

        if ($age > $maxAge) {
            return [
                'is_eligible' => false,
                'age' => $age,
                'reason' => "Contestant age ({$age}) exceeds maximum allowed age ({$maxAge}).",
            ];
        }

        return [
            'is_eligible' => true,
            'age' => $age,
            'reason' => null,
        ];
    }

    /**
     * @return array{completeness_percent: int, is_complete: bool, missing_fields: array<int, string>}
     */
    public function calculateProfileCompleteness(Contestant $contestant): array
    {
        $missing = [];
        $total = 5;
        $filled = 0;

        if ($contestant->getFullName() !== '') {
            $filled++;
        } else {
            $missing[] = 'full_name';
        }

        if ($contestant->getPhoneNumber() !== '') {
            $filled++;
        } else {
            $missing[] = 'phone_number';
        }

        if ($contestant->getDateOfBirth() !== null) {
            $filled++;
        } else {
            $missing[] = 'date_of_birth';
        }

        if ($contestant->getPhotoMediaId() !== null) {
            $filled++;
        } else {
            $missing[] = 'photo_media_asset_id';
        }

        if ($contestant->countryId !== '') {
            $filled++;
        } else {
            $missing[] = 'country_id';
        }

        $percent = (int) round(($filled / $total) * 100);

        return [
            'completeness_percent' => $percent,
            'is_complete' => $percent === 100,
            'missing_fields' => $missing,
        ];
    }
}
