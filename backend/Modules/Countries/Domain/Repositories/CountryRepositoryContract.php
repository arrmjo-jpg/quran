<?php

declare(strict_types=1);

namespace Modules\Countries\Domain\Repositories;

use Modules\Countries\Domain\Entities\Country;
use Modules\Countries\Domain\ValueObjects\CountryId;
use Modules\Countries\Domain\ValueObjects\CountryIso2;
use Modules\Countries\Domain\ValueObjects\CountryIso3;

interface CountryRepositoryContract
{
    public function findOrFail(CountryId $id): Country;

    public function find(CountryId $id): ?Country;

    public function findByIso2(CountryIso2 $iso2): ?Country;

    public function findByIso3(CountryIso3 $iso3): ?Country;

    /** @return array<int, Country> */
    public function getAllActive(): array;

    public function save(Country $country): void;
}
