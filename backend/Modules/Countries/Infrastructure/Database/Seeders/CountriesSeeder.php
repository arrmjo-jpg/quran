<?php

declare(strict_types=1);

namespace Modules\Countries\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Countries\Domain\Entities\Country;
use Modules\Countries\Domain\Repositories\CountryRepositoryContract;
use Modules\Countries\Domain\ValueObjects\CountryId;
use Modules\Countries\Domain\ValueObjects\CountryIso2;
use Modules\Countries\Domain\ValueObjects\CountryIso3;
use Modules\Countries\Domain\ValueObjects\PhoneCode;

/**
 * CountriesSeeder
 *
 * Official platform seeder for reference country ISO data.
 */
final class CountriesSeeder extends Seeder
{
    private const SAMPLE_COUNTRIES = [
        [
            'iso2' => 'JO',
            'iso3' => 'JOR',
            'phone' => '+962',
            'flag' => 'https://cdn.quranplatform.com/flags/jo.svg',
            'name_ar' => 'الأردن',
            'name_en' => 'Jordan',
        ],
        [
            'iso2' => 'SA',
            'iso3' => 'SAU',
            'phone' => '+966',
            'flag' => 'https://cdn.quranplatform.com/flags/sa.svg',
            'name_ar' => 'المملكة العربية السعودية',
            'name_en' => 'Saudi Arabia',
        ],
        [
            'iso2' => 'EG',
            'iso3' => 'EGY',
            'phone' => '+20',
            'flag' => 'https://cdn.quranplatform.com/flags/eg.svg',
            'name_ar' => 'مصر',
            'name_en' => 'Egypt',
        ],
        [
            'iso2' => 'AE',
            'iso3' => 'ARE',
            'phone' => '+971',
            'flag' => 'https://cdn.quranplatform.com/flags/ae.svg',
            'name_ar' => 'الإمارات العربية المتحدة',
            'name_en' => 'United Arab Emirates',
        ],
        [
            'iso2' => 'MA',
            'iso3' => 'MAR',
            'phone' => '+212',
            'flag' => 'https://cdn.quranplatform.com/flags/ma.svg',
            'name_ar' => 'المغرب',
            'name_en' => 'Morocco',
        ],
    ];

    public function run(CountryRepositoryContract $repository): void
    {
        foreach (self::SAMPLE_COUNTRIES as $data) {
            $iso2 = new CountryIso2($data['iso2']);

            if ($repository->findByIso2($iso2) !== null) {
                continue;
            }

            $country = Country::create(
                id: CountryId::generate(),
                iso2: $iso2,
                iso3: new CountryIso3($data['iso3']),
                phoneCode: new PhoneCode($data['phone']),
                flagUrl: $data['flag'],
                translations: [
                    'ar' => $data['name_ar'],
                    'en' => $data['name_en'],
                ]
            );

            $repository->save($country);
        }
    }
}
