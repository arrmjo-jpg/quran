<?php

declare(strict_types=1);

namespace Modules\Contestants\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateContestantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The account must already exist: this endpoint links, it does
            // not provision (ADR-016 D14/Q7). `unique` catches the ordinary
            // duplicate as a 422 field error; the use case still checks,
            // because `unique` cannot see soft-deleted rows and the index
            // does not exclude them.
            'user_id' => ['required', 'uuid', 'exists:users,id', 'unique:contestants,user_id'],
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today'],
            'gender' => ['required', 'string', 'in:male,female'],
            'phone_number' => ['required', 'string', 'max:50'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'photo_media_id' => ['sometimes', 'nullable', 'uuid', 'exists:media_assets,id'],
        ];
    }
}
