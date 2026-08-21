<?php

declare(strict_types=1);

namespace Modules\Contestants\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Events\ContestantCreated;
use Modules\Contestants\Domain\Exceptions\UserAlreadyHasContestantException;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Contestants\Domain\ValueObjects\BirthDate;
use Modules\Contestants\Domain\ValueObjects\ContestantId;
use Modules\Contestants\Domain\ValueObjects\Gender;

/**
 * Registers a contestant against an account that already exists — Epic 4
 * Story 1.
 *
 * IT DOES NOT CREATE THE ACCOUNT, and that is a decision rather than an
 * omission. ADR-016 D14 and Q7 settled that accounts are created Pending
 * Activation and activated by invitation, and that no administrator ever sets
 * another user's password. A contestant-creation route that also minted an
 * account would have to either break that rule or reimplement Epic 1's
 * invitation flow inside this module. Linking to an existing account keeps
 * one way in.
 *
 * `user_id` and `country_id` are fixed at creation and readonly on the
 * aggregate thereafter — see Contestant::applyAdminEdit() for why.
 */
final readonly class CreateContestantUseCase
{
    public function __construct(
        private ContestantRepositoryContract $contestants,
    ) {}

    public function execute(
        string $userId,
        string $countryId,
        string $fullName,
        string $dateOfBirth,
        string $gender,
        string $phoneNumber,
        ?string $nationalId = null,
        ?string $photoMediaId = null,
        ?string $byUserId = null,
    ): Contestant {
        return DB::transaction(function () use (
            $userId, $countryId, $fullName, $dateOfBirth, $gender,
            $phoneNumber, $nationalId, $photoMediaId, $byUserId
        ): Contestant {
            // Checked here rather than left to the UNIQUE index so the
            // refusal is a 409 an administrator can read, not a 500. The
            // request rule cannot do it alone: it would have to know that
            // soft-deleted rows still occupy the account.
            if ($this->contestants->existsForUser($userId)) {
                throw new UserAlreadyHasContestantException($userId);
            }

            $contestant = Contestant::create(
                id: ContestantId::generate(),
                userId: $userId,
                countryId: $countryId,
                fullName: trim($fullName),
                dateOfBirth: new BirthDate($dateOfBirth),
                gender: new Gender($gender),
                phoneNumber: trim($phoneNumber),
                nationalId: $nationalId === null ? null : trim($nationalId),
                photoMediaId: $photoMediaId,
            );

            $this->contestants->save($contestant);

            event(new ContestantCreated(
                contestantId: $contestant->id->value,
                byUserId: $byUserId,
                occurredAt: now()->toIso8601String(),
            ));

            return $contestant;
        });
    }
}
