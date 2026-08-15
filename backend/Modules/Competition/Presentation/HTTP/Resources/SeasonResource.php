<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;

final class SeasonResource extends JsonResource
{
    private const SUPPORTED_LOCALES = ['ar', 'en', 'es'];

    /**
     * @param  Season  $resource
     * @param  array<int, string>  $countryIds  the season's eligible countries, ids only
     */
    public function __construct(
        $resource,
        private readonly array $countryIds = [],
    ) {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Season $season */
        $season = $this->resource;

        $translations = [];
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $translation = $season->getTranslation($locale);

            if ($translation !== null) {
                $translations[$locale] = $this->translationToArray($translation);
            }
        }

        $displayTranslation = $season->getTranslation(app()->getLocale()) ?? $season->getTranslation('en');

        return [
            'id' => $season->id,
            'slug' => $season->getSlug(),
            'year' => $season->getYear(),
            'title' => $displayTranslation?->title ?? $season->getSlug(),
            'status' => $season->getStatus(),
            'is_active' => $season->isActive(),
            'is_frozen' => $season->isFrozen(),
            'min_age' => $season->getMinAge(),
            'max_age' => $season->getMaxAge(),
            'participation_type_id' => $season->getParticipationTypeId(),
            'tajweed_level_id' => $season->getTajweedLevelId(),
            // Ids only, by design — the names belong to the countries
            // catalog, and repeating them here would mean two places to
            // keep in step. This exists so a client can read the set it is
            // about to replace via PATCH .../rules, which takes the whole
            // set every time.
            'country_ids' => $this->countryIds,
            'registration_start' => $season->getRegistrationStartIso(),
            'registration_end' => $season->getRegistrationEndIso(),
            'start_date' => $season->getStartDateIso(),
            'end_date' => $season->getEndDateIso(),
            'frozen_at' => $season->getFrozenAtIso(),
            'archived_at' => $season->getArchivedAtIso(),
            'archived_by_user_id' => $season->getArchivedByUserId(),
            'archive_reason' => $season->getArchiveReason(),
            'translations' => $translations,
        ];
    }

    /** @return array<string, string|null> */
    private function translationToArray(SeasonTranslation $translation): array
    {
        return [
            'title' => $translation->title,
            'public_name' => $translation->publicName,
            'public_short_name' => $translation->publicShortName,
            'description' => $translation->description,
        ];
    }
}
