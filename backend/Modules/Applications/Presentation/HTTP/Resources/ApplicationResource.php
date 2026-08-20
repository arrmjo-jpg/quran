<?php

declare(strict_types=1);

namespace Modules\Applications\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Applications\Domain\Entities\Application;

/**
 * THE PLACEMENT IS READ FROM THE APPLICATION'S OWN COLUMNS, never resolved
 * from center_id against the live centres table — ADR-016 D8.
 *
 * Resolving it would be the bug D8 was written to prevent: a rename would
 * silently rewrite every past application, and the report that said a
 * contestant studied at the Central Centre in Amman would start saying
 * something else. The frozen names exist precisely so that this class does not
 * have to ask anyone what the centre is called today.
 */
final class ApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Application|object $resource */
        $resource = $this->resource;

        if ($resource instanceof Application) {
            return [
                'id' => $resource->id,
                'contestant_id' => $resource->contestantId,
                'season_id' => $resource->seasonId,
                'stage_id' => $resource->stageId,
                'status' => (string) $resource->getStatus(),
                'video_media_asset_id' => $resource->videoMediaId,
                'reupload_reason' => $resource->getReuploadReason(),
                'placement' => $resource->getPlacement() === null ? null : [
                    'center_id' => $resource->getPlacement()->centerId,
                    'circle_id' => $resource->getPlacement()->circleId,
                    'center_name' => $resource->getPlacement()->centerName,
                    'circle_name' => $resource->getPlacement()->circleName,
                ],
            ];
        }

        return [
            'id' => $resource->id,
            'contestant_id' => $resource->contestant_id,
            'season_id' => $resource->season_id,
            'stage_id' => $resource->stage_id,
            'status' => $resource->status,
            'video_media_asset_id' => $resource->video_media_id,
            'reupload_reason' => $resource->reupload_reason,
            'submitted_at' => $resource->created_at?->toIso8601String(),
            'placement' => $resource->center_id === null ? null : [
                'center_id' => $resource->center_id,
                'circle_id' => $resource->circle_id,
                'center_name' => $resource->center_name,
                'circle_name' => $resource->circle_name,
            ],
        ];
    }
}
