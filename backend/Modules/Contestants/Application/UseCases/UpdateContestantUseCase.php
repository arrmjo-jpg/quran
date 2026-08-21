<?php

declare(strict_types=1);

namespace Modules\Contestants\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Events\ContestantUpdated;
use Modules\Contestants\Domain\Repositories\ContestantRepositoryContract;
use Modules\Contestants\Domain\ValueObjects\ContestantId;

/**
 * The administrative edit — Epic 4 Story 1.
 *
 * Takes only the fields the caller actually sent, so omitting one leaves it
 * alone rather than blanking it. The aggregate applies them and reports which
 * genuinely changed; an edit that changes nothing writes no event, for the
 * same reason RestoreUserUseCase returns early on a live account — an audit
 * entry claiming something happened when nothing did is worse than none.
 *
 * @see Contestant::applyAdminEdit() for why user_id and country_id are not editable
 */
final readonly class UpdateContestantUseCase
{
    public function __construct(
        private ContestantRepositoryContract $contestants,
    ) {}

    /** @param array<string, mixed> $changes */
    public function execute(string $contestantId, array $changes, ?string $byUserId = null): Contestant
    {
        return DB::transaction(function () use ($contestantId, $changes, $byUserId): Contestant {
            $contestant = $this->contestants->findOrFail(new ContestantId($contestantId));

            $changed = $contestant->applyAdminEdit($changes);

            if ($changed === []) {
                return $contestant;
            }

            $this->contestants->save($contestant);

            event(new ContestantUpdated(
                contestantId: $contestant->id->value,
                changed: $changed,
                byUserId: $byUserId,
                occurredAt: now()->toIso8601String(),
            ));

            return $contestant;
        });
    }
}
