<?php

declare(strict_types=1);

namespace Modules\Applications\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'season_id' => ['required', 'string', 'uuid', 'exists:seasons,id'],
            'stage_id' => ['required', 'string', 'uuid', 'exists:stages,id'],
            'video_media_asset_id' => ['required', 'string', 'uuid', 'exists:media_assets,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
