<?php

declare(strict_types=1);

namespace Modules\Reports\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type, // contestants_csv | evaluations_pdf
            'media_asset_id' => $this->media_asset_id,
            'status' => $this->status, // pending | processing | completed | failed
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
