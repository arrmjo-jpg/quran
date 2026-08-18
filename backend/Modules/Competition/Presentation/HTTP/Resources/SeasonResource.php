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
