<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Competition\Domain\ValueObjects\ResolvedJudgeScoreSystem;
use Modules\Competition\Domain\ValueObjects\ResolvedLookupOption;

/**
 * LookupOptionResource
 *
 * One selectable catalog entry — participation type, tajweed level, or
 * judge score system — shaped for a picker: the id the write endpoints
 * expect, the code, and the per-locale name to display.
 *
 * Handles both resolved lookup types, because a judge score system is the
 * same thing plus a scale ceiling; max_score appears only for those, since
 * the picker has to show which scale it is and a percentage is later
 * resolved against it.
 */
final class LookupOptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ResolvedLookupOption|ResolvedJudgeScoreSystem $option */
        $option = $this->resource;

        $payload = [
            'id' => $option->id,
            'code' => $option->code,
            'name' => $option->name,
            'display_order' => $option->displayOrder,
        ];

        if ($option instanceof ResolvedJudgeScoreSystem) {
            $payload['max_score'] = $option->maxScore;
        }

        return $payload;
    }
}
