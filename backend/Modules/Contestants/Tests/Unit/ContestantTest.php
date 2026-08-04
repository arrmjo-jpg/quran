<?php

declare(strict_types=1);

use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\ValueObjects\BirthDate;
use Modules\Contestants\Domain\ValueObjects\ContestantId;
use Modules\Contestants\Domain\ValueObjects\Gender;

uses()->group('contestants', 'unit', 'domain');

test('contestant aggregate calculates age correctly for competition eligibility', function (): void {
    $birthDate = new BirthDate('2000-05-15');
    $contestant = Contestant::create(
        id: ContestantId::generate(),
        userId: fake()->uuid(),
        countryId: fake()->uuid(),
        fullName: 'Ahmad Al-Mansoor',
        dateOfBirth: $birthDate,
        gender: new Gender('male'),
        phoneNumber: '+962790000000'
    );

    $age = $contestant->getDateOfBirth()->calculateAgeAt('2026-08-01');

    expect($age)->toBe(26);
    expect($contestant->getFullName())->toBe('Ahmad Al-Mansoor');
});
