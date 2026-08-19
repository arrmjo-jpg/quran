<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Organization\Infrastructure\Database\Models\ContestantMembershipModel;

/**
 * A membership as the admin panel sees it.
 *
 * `is_active` is derived rather than stored, and travels because the client
 * would otherwise recompute `left_at === null` in three places and eventually
 * get one of them wrong. It is the same condition the unique index uses, named
 * once.
 *
 * The circle travels nested when eager loaded, for the reason CircleResource
 * nests its centre: a membership rendered as two opaque ids is a row nobody
 * can read.
 */
final class MembershipResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ContestantMembershipModel $membership */
        $membership = $this->resource;

        return [
            'id' => (string) $membership->id,
            'contestant_id' => (string) $membership->contestant_id,
            'circle_id' => (string) $membership->circle_id,
            'circle' => $this->whenLoaded('circle', fn (): ?array => $membership->circle === null ? null : [
                'id' => (string) $membership->circle->id,
                'name' => $membership->circle->name,
                'center_id' => (string) $membership->circle->center_id,
            ]),
            'joined_at' => $membership->joined_at?->toIso8601String(),
            'left_at' => $membership->left_at?->toIso8601String(),
            'is_active' => $membership->left_at === null,
            'reason' => $membership->reason,
            'created_at' => $membership->created_at?->toIso8601String(),
        ];
    }
}
