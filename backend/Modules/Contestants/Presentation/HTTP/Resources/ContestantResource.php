<?php

declare(strict_types=1);

namespace Modules\Contestants\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contestants\Domain\Entities\Contestant;

final class ContestantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Contestant|object $resource */
        $resource = $this->resource;

        if ($resource instanceof Contestant) {
            return [
                'id' => $resource->id->value,
                'country_id' => $resource->countryId,
                'full_name' => $resource->getFullName(),
                'photo_media_asset_id' => $resource->getPhotoMediaId(),
            ];
        }

        return [
            'id' => $resource->id,
            'country_id' => $resource->country_id,
            'full_name' => $resource->full_name,
            'photo_media_asset_id' => $resource->photo_media_id,
        ];
    }
}
