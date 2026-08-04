<?php

declare(strict_types=1);

namespace Modules\Countries\Domain\Specifications;

use Modules\Countries\Domain\Entities\Country;

/**
 * ActiveCountriesSpecification
 *
 * Enforces business specification rule that a country is eligible for user selections.
 */
final readonly class ActiveCountriesSpecification
{
    public function isSatisfiedBy(Country $country): bool
    {
        return $country->isActive();
    }
}
