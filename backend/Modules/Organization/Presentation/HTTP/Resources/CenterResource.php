<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Organization\Infrastructure\Database\Models\CenterModel;

/**
 * A centre as the admin panel sees it.
 *
 * Coordinates travel as a nested pair rather than two sibling keys, matching
 * the value object: latitude without longitude is not half a location, and a
 * client that received them separately could render one.
 */
final class CenterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CenterModel $center */
        $center = $this->resource;

        return [
            'id' => (string) $center->id,
            'name' => $center->name,
            'country_id' => (string) $center->country_id,
            'city' => $center->city,
            'address' => $center->address,
            'coordinates' => $center->latitude === null || $center->longitude === null
                ? null
                : ['latitude' => $center->latitude, 'longitude' => $center->longitude],
            'created_at' => $center->created_at?->toIso8601String(),
        ];
    }
}
