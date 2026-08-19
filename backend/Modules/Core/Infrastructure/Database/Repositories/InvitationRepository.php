<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Repositories;

use Modules\Core\Domain\Entities\Invitation;
use Modules\Core\Domain\Repositories\InvitationRepositoryContract;
use Modules\Core\Domain\ValueObjects\InvitationId;
use Modules\Core\Domain\ValueObjects\InvitationToken;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Infrastructure\Database\Models\InvitationModel;
use RuntimeException;

/**
 * Eloquent is confined to this class — ADR-002. Everything above it sees only
 * the Invitation aggregate.
 */
final class InvitationRepository implements InvitationRepositoryContract
{
    public function find(InvitationId $id): ?Invitation
    {
        $row = InvitationModel::query()->find($id->value);

        return $row === null ? null : $this->toEntity($row);
    }

    public function findByToken(string $plaintext): ?Invitation
    {
        // Hashed here so the plaintext never becomes a query binding. It is
        // the one value in this system that must not appear in a slow-query
        // log or a database audit trail.
        $row = InvitationModel::query()
            ->where('token_hash', InvitationToken::hashOf($plaintext))
            ->first();

        return $row === null ? null : $this->toEntity($row);
    }

    public function findOpenForUser(UserId $userId): ?Invitation
    {
        $row = InvitationModel::query()
            ->where('user_id', $userId->value)
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->first();

        return $row === null ? null : $this->toEntity($row);
    }

    public function save(Invitation $invitation): void
    {
        InvitationModel::query()->updateOrCreate(
            ['id' => $invitation->id->value],
            [
                'user_id' => $invitation->userId->value,
                'token_hash' => $invitation->tokenHash(),
                'expires_at' => $invitation->expiresAt(),
                'accepted_at' => $invitation->acceptedAt(),
                'invited_by_user_id' => $invitation->invitedBy?->value,
            ]
        );
    }

    private function toEntity(InvitationModel $row): Invitation
    {
        if ($row->expires_at === null) {
            // Not defensive noise: expires_at is NOT NULL in the schema, so a
            // null here means the row was written by something bypassing this
            // repository. An invitation with no expiry never expires, which is
            // the one state this design must not have.
            throw new RuntimeException("Invitation {$row->id} has no expiry.");
        }

        return Invitation::reconstitute(
            id: new InvitationId((string) $row->id),
            userId: new UserId((string) $row->user_id),
            token: InvitationToken::fromHash((string) $row->token_hash),
            expiresAt: $row->expires_at->toIso8601String(),
            acceptedAt: $row->accepted_at?->toIso8601String(),
            invitedBy: $row->invited_by_user_id === null
                ? null
                : new UserId((string) $row->invited_by_user_id),
        );
    }
}
