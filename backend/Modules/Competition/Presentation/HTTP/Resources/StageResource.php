<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Competition\Domain\Entities\Stage;
use Modules\Competition\Domain\ValueObjects\StageTranslation;

final class StageResource extends JsonResource
{
    private const SUPPORTED_LOCALES = ['ar', 'en', 'es'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Stage $stage */
        $stage = $this->resource;

        $translations = [];
        foreach (self::SUPPORTED_LOCALES as $locale) {
            $translation = $stage->getTranslation($locale);

            if ($translation !== null) {
                $translations[$locale] = $this->translationToArray($translation);
            }
        }

        $displayTranslation = $stage->getTranslation(app()->getLocale()) ?? $stage->getTranslation('en');

        return [
            'id' => $stage->id,
            'season_id' => $stage->seasonId,
            'stage_number' => $stage->getStageNumber(),
            'type' => $stage->getType(),
            'name' => $displayTranslation?->name,
            'status' => $stage->getStatus(),
            'start_date' => $stage->getStartDateIso(),
            'end_date' => $stage->getEndDateIso(),
            'evaluation_template_id' => $stage->getEvaluationTemplateId(),
            'translations' => $translations,
        ];
    }

    /** @return array<string, string|null> */
    private function translationToArray(StageTranslation $translation): array
    {
        return [
            'name' => $translation->name,
            'public_name' => $translation->publicName,
            'description' => $translation->description,
        ];
    }
}
