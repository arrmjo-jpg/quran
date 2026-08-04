<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * NotificationLogResource
 *
 * Response contract for a single notification log entry.
 * Maps NotificationLogModel columns to API JSON.
 *
 * Admin view: full record including payload, error, user_id.
 * Contestant view: same structure (filtered by ownership at controller level).
 */
final class NotificationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'channel' => $this->channel,       // email | sms | push
            'template_key' => $this->template_key,
            'status' => $this->status,        // queued | sent | failed | retrying
            'sent_at' => $this->sent_at?->toIso8601String(),
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
