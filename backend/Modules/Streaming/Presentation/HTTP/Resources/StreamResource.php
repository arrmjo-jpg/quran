<?php

declare(strict_types=1);

namespace Modules\Streaming\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * StreamResource
 *
 * API Response contract for live stream entries.
 * Admin context: includes stream_key and rtmp_url ingestion parameters.
 * Public context: includes only playback HLS URL and status.
 */
final class StreamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = str_contains($request->path(), 'admin');

        $data = [
            'id' => $this->id,
            'season_id' => $this->season_id,
            'stage_id' => $this->stage_id,
            'title' => $this->title,
            'status' => $this->status, // idle | live | ended
            'hls_url' => $this->hls_url,
            'scheduled_start' => $this->scheduled_start?->toIso8601String(),
            'actual_start' => $this->actual_start?->toIso8601String(),
            'actual_end' => $this->actual_end?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($isAdmin) {
            $data['stream_key'] = $this->stream_key;
            $data['rtmp_url'] = $this->rtmp_url;
        }

        return $data;
    }
}
