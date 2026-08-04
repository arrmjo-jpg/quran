<?php

declare(strict_types=1);

namespace Modules\Countries\Domain\Entities;

use Modules\Countries\Domain\Events\CountryActivated;
use Modules\Countries\Domain\Events\CountryCreated;
use Modules\Countries\Domain\Events\CountryDeactivated;
use Modules\Countries\Domain\Events\CountryUpdated;
use Modules\Countries\Domain\ValueObjects\CountryId;
use Modules\Countries\Domain\ValueObjects\CountryIso2;
use Modules\Countries\Domain\ValueObjects\CountryIso3;
use Modules\Countries\Domain\ValueObjects\PhoneCode;

/**
 * Country Aggregate Root
 *
 * Reference data aggregate root governing country codes and activation states.
 * Hard deletes are strictly forbidden to preserve historical references.
 */
final class Country
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    /**
     * @param  array<string, string>  $translations  Map of locale => name (e.g. ['ar' => 'الأردن', 'en' => 'Jordan'])
     */
    public function __construct(
        public readonly CountryId $id,
        private CountryIso2 $iso2,
        private CountryIso3 $iso3,
        private PhoneCode $phoneCode,
        private ?string $flagUrl = null,
        private bool $isActive = true,
        private array $translations = [],
    ) {}

    public static function create(
        CountryId $id,
        CountryIso2 $iso2,
        CountryIso3 $iso3,
        PhoneCode $phoneCode,
        ?string $flagUrl = null,
        array $translations = []
    ): self {
        $country = new self(
            id: $id,
            iso2: $iso2,
            iso3: $iso3,
            phoneCode: $phoneCode,
            flagUrl: $flagUrl,
            isActive: true,
            translations: $translations
        );

        $country->recordEvent(new CountryCreated($id->value, (string) $iso2, (string) $iso3, now()->toIso8601String()));

        return $country;
    }

    public function getIso2(): CountryIso2
    {
        return $this->iso2;
    }

    public function getIso3(): CountryIso3
    {
        return $this->iso3;
    }

    public function getPhoneCode(): PhoneCode
    {
        return $this->phoneCode;
    }

    public function getFlagUrl(): ?string
    {
        return $this->flagUrl;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /** @return array<string, string> */
    public function getTranslations(): array
    {
        return $this->translations;
    }

    public function getName(string $locale = 'ar'): string
    {
        return $this->translations[$locale] ?? $this->translations['en'] ?? (string) $this->iso2;
    }

    public function updateDetails(PhoneCode $phoneCode, ?string $flagUrl, array $translations): void
    {
        $this->phoneCode = $phoneCode;
        $this->flagUrl = $flagUrl;
        $this->translations = array_merge($this->translations, $translations);

        $this->recordEvent(new CountryUpdated($this->id->value, (string) $this->iso2, now()->toIso8601String()));
    }

    public function activate(): void
    {
        if (! $this->isActive) {
            $this->isActive = true;
            $this->recordEvent(new CountryActivated($this->id->value, (string) $this->iso2, now()->toIso8601String()));
        }
    }

    public function deactivate(): void
    {
        if ($this->isActive) {
            $this->isActive = false;
            $this->recordEvent(new CountryDeactivated($this->id->value, (string) $this->iso2, now()->toIso8601String()));
        }
    }

    /** @return array<int, object> */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    protected function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
