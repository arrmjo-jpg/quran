<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Organization\Infrastructure\Database\Models\CircleModel;

/**
 * A circle as the admin panel sees it.
 *
 * The centre travels nested rather than as a bare id, because a circle has no
 * location of its own (Q3) and a list of circles showing only ids would send
 * the client back for every row. It is included only when the relation was
 * eager loaded, so a caller that did not ask for it does not pay N+1 queries
 * for a field it will not render.
 *
 * Only the centre's identifying fields are carried — not its address or
 * coordinates. A circle list is not a centre list, and a client needing the
 * full location can read the centre it now has the id for.
 */
final class CircleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CircleModel $circle */
        $circle = $this->resource;

        return [
            'id' => (string) $circle->id,
            'name' => $circle->name,
            'center_id' => (string) $circle->center_id,
            'center' => $this->whenLoaded('center', fn (): ?array => $circle->center === null ? null : [
                'id' => (string) $circle->center->id,
                'name' => $circle->center->name,
                'city' => $circle->center->city,
                'country_id' => (string) $circle->center->country_id,
            ]),
            'supervisor_user_id' => $circle->supervisor_user_id === null
                ? null
                : (string) $circle->supervisor_user_id,
            'created_at' => $circle->created_at?->toIso8601String(),
        ];
    }
}
