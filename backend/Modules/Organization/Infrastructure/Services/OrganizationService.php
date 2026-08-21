<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Services;

use Modules\Organization\Contracts\OrganizationServiceContract;
use Modules\Organization\Contracts\ResolvedMembershipDTO;
use Modules\Organization\Infrastructure\Database\Models\ContestantMembershipModel;

final class OrganizationService implements OrganizationServiceContract
{
    public function findMembershipsForContestant(string $contestantId): array
    {
        return ContestantMembershipModel::query()
            // circle.center in one eager load: three queries for the whole
            // history rather than two per row. The relations were declared
            // for exactly this — see CircleModel::center().
            ->with('circle.center')
            ->where('contestant_id', $contestantId)
            ->orderByDesc('joined_at')
            ->get()
            ->map(fn (ContestantMembershipModel $membership): ResolvedMembershipDTO => new ResolvedMembershipDTO(
                id: (string) $membership->id,
                contestantId: (string) $membership->contestant_id,
                circleId: (string) $membership->circle_id,
                // The circle is soft-deletable, so a history row can outlive
                // it and these read null. That is the honest answer: the
                // membership happened, and the circle it happened in is gone.
                circleName: $membership->circle?->name,
                centerId: $membership->circle?->center_id === null
                    ? null
                    : (string) $membership->circle->center_id,
                centerName: $membership->circle?->center?->name,
                centerCity: $membership->circle?->center?->city,
                joinedAt: $membership->joined_at?->toIso8601String(),
                leftAt: $membership->left_at?->toIso8601String(),
                isActive: $membership->left_at === null,
                reason: $membership->reason,
            ))
            ->all();
    }
}
