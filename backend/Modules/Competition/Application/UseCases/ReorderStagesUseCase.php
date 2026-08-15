<?php

declare(strict_types=1);

namespace Modules\Competition\Application\UseCases;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Competition\Domain\Entities\Stage;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Repositories\StageRepositoryContract;
use Modules\Competition\Domain\Services\SeasonEditingPolicy;

/**
 * ReorderStagesUseCase
 *
 * Renumbers a season's stages to 1..N in the order given. The payload
 * must list the season's stages exactly — every one of them, each once —
 * rather than a partial "move stage X to position 3": a partial reorder
 * has no single correct interpretation of where everything else lands,
 * and leaves the caller unable to predict the result. Sending the whole
 * ordering makes the outcome exactly what was asked for.
 *
 * This is the only Use Case that writes stage_number, which is what keeps
 * uk_stages_season_number contention to a single code path (see
 * StageRepository::applyOrder() for how the write avoids colliding with
 * itself mid-swap).
 */
final readonly class ReorderStagesUseCase
{
    public function __construct(
        private StageRepositoryContract $stages,
        private SeasonRepositoryContract $seasons,
        private SeasonEditingPolicy $editing,
    ) {}

    /**
     * @param  array<int, string>  $orderedStageIds
     * @return array<int, Stage>
     */
    public function execute(string $seasonId, array $orderedStageIds): array
    {
        return DB::transaction(function () use ($seasonId, $orderedStageIds): array {
            $this->editing->assertEditable($this->seasons->findOrFail($seasonId), 'stages');

            $this->assertCoversEveryStage($seasonId, $orderedStageIds);

            $this->stages->applyOrder($seasonId, array_values($orderedStageIds));

            return $this->stages->findBySeason($seasonId);
        });
    }

    /**
     * @param  array<int, string>  $orderedStageIds
     */
    private function assertCoversEveryStage(string $seasonId, array $orderedStageIds): void
    {
        if (count($orderedStageIds) !== count(array_unique($orderedStageIds))) {
            throw new InvalidArgumentException('The stage order contains the same stage more than once.');
        }

        $existing = array_map(
            static fn (Stage $stage): string => $stage->id,
            $this->stages->findBySeason($seasonId)
        );

        sort($existing);
        $submitted = $orderedStageIds;
        sort($submitted);

        if ($existing !== $submitted) {
            throw new InvalidArgumentException(
                'The stage order must list every stage of the season exactly once.'
            );
        }
    }
}
