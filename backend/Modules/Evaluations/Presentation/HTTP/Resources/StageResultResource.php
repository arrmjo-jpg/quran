<?php

declare(strict_types=1);

namespace Modules\Evaluations\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StageResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'application_id' => $this->application_id,
            'final_score' => (float) $this->final_score,
            'rank' => (int) $this->rank,
            'qualification_status' => $this->qualification_status,
            'is_published' => (bool) $this->is_published,
        ];
    }
}
