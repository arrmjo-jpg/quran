<?php

declare(strict_types=1);

namespace Modules\Sponsors\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Infrastructure\Database\Models\MediaAssetModel;

final class SponsorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tier' => $this->tier, // headline | gold | silver | partner
            'logo_media_id' => $this->logo_media_id,
            'logo_url' => $this->resolveLogoUrl(),
            'website_url' => $this->website_url,
            'display_order' => $this->display_order,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function resolveLogoUrl(): ?string
    {
        if (! $this->logo_media_id) {
            return null;
        }

        try {
            $asset = MediaAssetModel::query()->find($this->logo_media_id);

            return $asset ? Storage::url($asset->file_path) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
