<?php

declare(strict_types=1);

namespace Modules\Media\Infrastructure\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Media\Contracts\MediaServiceContract;
use Modules\Media\Contracts\ResolvedMediaDTO;
use Modules\Media\Infrastructure\Database\Models\MediaAssetModel;
use Throwable;

/**
 * The Media module's boundary.
 *
 * The url and thumbnail rules are the same ones MediaAssetResource applies,
 * and they live here as well as there rather than being shared, because the
 * resource is a Presentation class in this module and a consumer importing
 * it would be reaching across exactly the boundary ADR-002 draws. Two small
 * copies of a disk lookup are a cheaper price than a module that depends on
 * another module's HTTP layer.
 */
final class MediaService implements MediaServiceContract
{
    public function findResolvedByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // Not withTrashed: a deleted asset has no file behind it, and a url
        // pointing at one is worse than no url at all.
        return MediaAssetModel::query()
            ->whereIn('id', $ids)
            ->get(['id', 'disk', 'file_path', 'mime_type', 'custom_properties'])
            ->mapWithKeys(function (MediaAssetModel $asset): array {
                $mimeType = $asset->mime_type ?? '';

                return [
                    (string) $asset->id => new ResolvedMediaDTO(
                        id: (string) $asset->id,
                        url: $this->resolveUrl($asset->disk, $asset->file_path),
                        thumb: $this->resolveThumb($asset),
                        mimeType: $mimeType,
                        isImage: str_starts_with($mimeType, 'image/'),
                    ),
                ];
            })
            ->all();
    }

    /**
     * A public disk has a permanent address; a private one issues presigned
     * URLs on demand and therefore has none to give here. Null rather than a
     * guess, so a caller can fall back instead of rendering a broken image.
     */
    private function resolveUrl(string $disk, string $filePath): ?string
    {
        try {
            if (str_contains($disk, 'public')) {
                return Storage::disk($disk)->url($filePath);
            }

            return Storage::url($filePath);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Only a thumbnail that was actually generated. Deriving one by naming
     * convention would produce a 404 where the full-size image would have
     * rendered.
     */
    private function resolveThumb(MediaAssetModel $asset): ?string
    {
        $custom = is_array($asset->custom_properties) ? $asset->custom_properties : [];

        return $custom['thumb_url'] ?? null;
    }
}
