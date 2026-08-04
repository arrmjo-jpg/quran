<?php

declare(strict_types=1);

namespace Modules\Media\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * MediaAssetResource
 *
 * Response envelope for a single MediaAsset.
 *
 * Contract mirrors admin-frontend MediaAssetData:
 *   id, uuid, kind, url, thumb, mime_type, is_image, size,
 *   original_name, alt, caption, credit, source,
 *   collection, hash_sha256, uploader_id, created_at
 *
 * Works with both:
 *   - MediaAssetModel (Eloquent)  — used for list / show
 *   - array snapshot              — used for upload response
 */
final class MediaAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $model = $this->resource;

        // Resolve URL from storage disk
        $url = $this->resolveUrl($model->disk, $model->file_path);
        $thumb = $this->resolveThumb($model);

        $mimeType = $model->mime_type ?? '';
        $isImage = str_starts_with($mimeType, 'image/');
        $isVideo = str_starts_with($mimeType, 'video/');

        $custom = is_array($model->custom_properties) ? $model->custom_properties : [];

        return [
            'id' => $model->id,
            'kind' => 'file',
            'url' => $url,
            'thumb' => $thumb,
            'mime_type' => $mimeType,
            'is_image' => $isImage,
            'is_video' => $isVideo,
            'collection' => $model->collection,
            'file_name' => $model->file_name,
            'size_bytes' => (int) $model->size_bytes,
            'hash_sha256' => $model->hash_sha256,
            'uploader_id' => $model->uploader_id,
            'alt' => $custom['alt'] ?? null,
            'caption' => $custom['caption'] ?? null,
            'credit' => $custom['credit'] ?? null,
            'source' => $custom['source'] ?? null,
            'created_at' => $model->created_at?->toIso8601String(),
            'deleted_at' => $model->deleted_at?->toIso8601String(),
        ];
    }

    private function resolveUrl(string $disk, string $filePath): ?string
    {
        // For R2/public disks — generate a public URL
        // For private disks — return null (presigned URLs generated on-demand)
        try {
            if (str_contains($disk, 'public')) {
                return Storage::disk($disk)->url($filePath);
            }

            // Local/testing — use default disk
            return Storage::url($filePath);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveThumb(mixed $model): ?string
    {
        // Check if there's a thumbnail conversion stored in custom_properties
        $custom = is_array($model->custom_properties) ? $model->custom_properties : [];

        return $custom['thumb_url'] ?? null;
    }
}
