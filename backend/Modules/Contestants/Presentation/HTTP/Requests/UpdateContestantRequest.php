<?php

declare(strict_types=1);

namespace Modules\Contestants\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Every field is `sometimes`: a partial update leaves what it omits alone.
 *
 * `user_id` and `country_id` are absent, and sending them changes nothing —
 * re-pointing a contestant at another account would move a person's whole
 * history, and country is frozen onto their applications (ADR-016 D8).
 */
final class UpdateContestantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'date_of_birth' => ['sometimes', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['sometimes', 'string', 'in:male,female'],
            'phone_number' => ['sometimes', 'string', 'max:50'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'photo_media_id' => ['sometimes', 'nullable', 'uuid', 'exists:media_assets,id'],
        ];
    }
}
