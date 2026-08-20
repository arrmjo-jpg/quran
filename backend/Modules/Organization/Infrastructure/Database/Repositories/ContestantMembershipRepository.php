<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Database\Repositories;

use Modules\Organization\Domain\Entities\ContestantMembership;
use Modules\Organization\Domain\Repositories\ContestantMembershipRepositoryContract;
use Modules\Organization\Domain\ValueObjects\CircleId;
use Modules\Organization\Domain\ValueObjects\MembershipId;
use Modules\Organization\Infrastructure\Database\Models\ContestantMembershipModel;
use RuntimeException;

/** Eloquent is confined to this class — ADR-002. */
final class ContestantMembershipRepository implements ContestantMembershipRepositoryContract
{
    public function find(MembershipId $id): ?ContestantMembership
    {
        $row = ContestantMembershipModel::query()->find($id->value);

        return $row === null ? null : $this->toEntity($row);
    }

    public function findOrFail(MembershipId $id): ContestantMembership
    {
        $membership = $this->find($id);

        if ($membership === null) {
            throw new RuntimeException("Membership {$id->value} not found.");
        }

        return $membership;
    }

    public function findActiveForContestant(string $contestantId): ?ContestantMembership
    {
        // `left_at IS NULL` is the whole definition of active, here and in the
        // unique index. There is no soft-delete scope to also account for,
        // which is why the table has none.
        $row = ContestantMembershipModel::query()
            ->where('contestant_id', $contestantId)
            ->whereNull('left_at')
            ->first();

        return $row === null ? null : $this->toEntity($row);
    }

    public function countActiveInCircle(CircleId $circleId): int
    {
        return ContestantMembershipModel::query()
            ->where('circle_id', $circleId->value)
            ->whereNull('left_at')
            ->count();
    }

    public function save(ContestantMembership $membership): void
    {
        ContestantMembershipModel::query()->updateOrCreate(
            ['id' => $membership->id->value],
            [
                'contestant_id' => $membership->getContestantId(),
                'circle_id' => $membership->getCircleId(),
                'joined_at' => $membership->getJoinedAt(),
                'left_at' => $membership->getLeftAt(),
                'reason' => $membership->getReason(),
            ]
        );
    }

    private function toEntity(ContestantMembershipModel $row): ContestantMembership
    {
        return ContestantMembership::reconstitute(
            id: new MembershipId((string) $row->id),
            contestantId: (string) $row->contestant_id,
            circleId: (string) $row->circle_id,
            joinedAt: $row->joined_at->toIso8601String(),
            leftAt: $row->left_at?->toIso8601String(),
            reason: $row->reason === null ? null : (string) $row->reason,
        );
    }
}
