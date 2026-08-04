<?php

declare(strict_types=1);

namespace Modules\Media\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UploadMediaRequest
 *
 * Validates multipart file upload for the Media Library.
 * Supports images and videos per ADR-006 (R2 Storage).
 */
final class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth enforced via route middleware
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:524288'], // 512 MB max
            'collection' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'A file is required.',
            'file.max' => 'File size must not exceed 512 MB.',
        ];
    }
}
