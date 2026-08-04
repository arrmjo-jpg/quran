<?php

declare(strict_types=1);

namespace Modules\Judges\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Judges\Domain\Entities\Judge;

final class JudgeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Judge|object $resource */
        $resource = $this->resource;

        if ($resource instanceof Judge) {
            return [
                'id' => $resource->id,
                'user_id' => $resource->userId,
                'full_name' => $resource->getFullName(),
                'specialization' => $resource->getSpecialization(),
                'is_active' => $resource->isActive(),
            ];
        }

        return [
            'id' => $resource->id,
            'user_id' => $resource->user_id,
            'full_name' => $resource->full_name,
            'specialization' => $resource->specialization,
            'title' => $resource->title,
            'bio' => $resource->bio,
            'is_active' => (bool) $resource->is_active,
        ];
    }
}
