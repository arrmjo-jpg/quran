<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Countries\Domain\Repositories\CountryRepositoryContract;
use Modules\Countries\Domain\ValueObjects\CountryIso2;
use Modules\Countries\Infrastructure\Database\Seeders\CountriesSeeder;

uses(RefreshDatabase::class)->group('countries', 'feature', 'seeders');

test('countries seeder populates reference ISO countries in database', function (): void {
    /** @var CountryRepositoryContract $repository */
    $repository = app(CountryRepositoryContract::class);

    $seeder = new CountriesSeeder;
    $seeder->run($repository);

    $jordan = $repository->findByIso2(new CountryIso2('JO'));

    expect($jordan)->not->toBeNull();
    expect($jordan->getName('ar'))->toBe('الأردن');
    expect($jordan->getName('en'))->toBe('Jordan');
    expect((string) $jordan->getPhoneCode())->toBe('+962');
});
