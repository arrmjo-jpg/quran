<?php

declare(strict_types=1);

namespace Modules\Applications\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Applications\Domain\Entities\Application;

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
        ];
    }
}
