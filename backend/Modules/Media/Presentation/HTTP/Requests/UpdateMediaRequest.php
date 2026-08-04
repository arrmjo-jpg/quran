<?php

declare(strict_types=1);

namespace Modules\Media\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateMediaRequest
 *
 * PATCH /api/v1/admin/media/{id}
 * Edits editorial metadata (alt / caption / credit / source) without re-uploading.
 */
final class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'alt' => ['nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'credit' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:500'],
        ];
    }
}
