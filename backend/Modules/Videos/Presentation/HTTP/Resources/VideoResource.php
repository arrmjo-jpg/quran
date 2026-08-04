<?php

declare(strict_types=1);

namespace Modules\Videos\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * VideoResource
 *
 * Response contract for a single Video record.
 * Maps VideoModel (Eloquent) to API JSON.
 *
 * Contestant-facing: exposes HLS URL for playback.
 * Admin-facing: includes processing status, variants, duration.
 *
 * NEVER exposes raw file paths — only resolved URLs.
 */
final class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $model = $this->resource;

        return [
            'id' => $model->id,
            'application_id' => $model->application_id,
            'raw_media_asset_id' => $model->raw_media_asset_id,
            'status' => $model->status,        // uploaded|processing|ready|failed
            'duration_seconds' => $model->duration_seconds,
            'hls_url' => $this->resolveHlsUrl($model),
            'thumbnail_url' => $this->resolveThumbnailUrl($model),
            'variants' => $this->resolveVariants($model),
            'created_at' => $model->created_at?->toIso8601String(),
            'updated_at' => $model->updated_at?->toIso8601String(),
        ];
    }

    private function resolveHlsUrl(mixed $model): ?string
    {
        if (empty($model->hls_master_playlist_path)) {
            return null;
        }

        try {
            return Storage::url($model->hls_master_playlist_path);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveThumbnailUrl(mixed $model): ?string
    {
        if (empty($model->thumbnail_path)) {
            return null;
        }

        try {
            return Storage::url($model->thumbnail_path);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve variants array to public URLs.
     * variants is stored as JSON: ['1080p' => 'path/to/1080p.m3u8', ...]
     *
     * @return array<string, string|null>
     */
    private function resolveVariants(mixed $model): array
    {
        $variants = is_array($model->variants) ? $model->variants : [];
        $resolved = [];

        foreach ($variants as $quality => $path) {
            try {
                $resolved[$quality] = Storage::url($path);
            } catch (\Throwable) {
                $resolved[$quality] = null;
            }
        }

        return $resolved;
    }
}
