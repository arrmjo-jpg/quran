<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Competition\Domain\Entities\Season;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;

/**
 * PublicSeasonResource
 *
 * What an unauthenticated caller may see of a season. The public routes
 * previously returned SeasonResource — the admin projection — so
 * GET /api/v1/seasons handed anyone on the internet the id of the admin
 * who archived a season, the internal archive reason, and the season's
 * whole judging configuration.
 *
 * Deliberately absent, and why:
 *  - archived_by_user_id  — a real admin account id
 *  - archive_reason       — written for internal record, not for entrants
 *  - archived_at, frozen_at, is_frozen — operational state; `status`
 *                           already tells a visitor what they need
 *  - participation_type_id, tajweed_level_id — raw lookup ids, useless
 *                           without the catalogs they point into, and the
 *                           catalogs are an admin surface
 *
 * min_age/max_age stay: they are how a would-be entrant works out whether
 * they can enter at all.
 */
final class PublicSeasonResource extends JsonResource
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
            'min_age' => $season->getMinAge(),
            'max_age' => $season->getMaxAge(),
            'registration_start' => $season->getRegistrationStartIso(),
            'registration_end' => $season->getRegistrationEndIso(),
            'start_date' => $season->getStartDateIso(),
            'end_date' => $season->getEndDateIso(),
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
