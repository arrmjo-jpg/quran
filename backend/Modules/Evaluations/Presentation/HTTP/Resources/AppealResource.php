<?php

declare(strict_types=1);

namespace Modules\Evaluations\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AppealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'reason' => $this->reason,
            'status' => $this->status,
            'admin_response' => $this->admin_response,
            'resolved_at' => $this->resolved_at,
        ];
    }
}
