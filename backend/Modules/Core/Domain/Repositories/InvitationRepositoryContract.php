<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Repositories;

use Modules\Core\Domain\Entities\Invitation;
use Modules\Core\Domain\ValueObjects\InvitationId;
use Modules\Core\Domain\ValueObjects\UserId;

interface InvitationRepositoryContract
{
    public function find(InvitationId $id): ?Invitation;

    /**
     * Look an invitation up by the plaintext a person presented.
     *
     * Takes the plaintext and hashes it here rather than making every caller
     * remember to: a lookup by raw token would mean the raw token had been
     * used as a query value somewhere, which is how secrets end up in query
     * logs. Returns null for an unknown token, and an Invitation for a known
     * one whatever its state — expiry and single-use are the aggregate's
     * questions to answer, not the repository's.
     */
    public function findByToken(string $plaintext): ?Invitation;

    /**
     * The invitation still open for this account, if any.
     *
     * "Open" means not yet accepted. An expired-but-unaccepted invitation is
     * still returned, because the screen that asks this needs to distinguish
     * "never invited" from "invited and the link went stale" — and Epic 12's
     * resend has to know which one it is looking at.
     */
    public function findOpenForUser(UserId $userId): ?Invitation;

    public function save(Invitation $invitation): void;
}
