<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Competition\Domain\Entities\Season;

final class SeasonResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Season|object $resource */
        $resource = $this->resource;

        if ($resource instanceof Season) {
            return [
                'id' => $resource->id,
                'slug' => $resource->getSlug(),
                'year' => $resource->getYear(),
                'status' => $resource->getStatus(),
                'is_active' => $resource->isActive(),
            ];
        }

        return [
            'id' => $resource->id,
            'slug' => $resource->slug,
            'year' => (int) $resource->year,
            'status' => $resource->status,
            'is_active' => (bool) $resource->is_active,
            'title' => $resource->translations->firstWhere('locale', app()->getLocale())?->title
                ?? $resource->translations->first()?->title
                ?? $resource->slug,
            'registration_start' => $resource->registration_start?->toIso8601String(),
            'registration_end' => $resource->registration_end?->toIso8601String(),
        ];
    }
}
