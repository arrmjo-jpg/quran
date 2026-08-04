<?php

declare(strict_types=1);

namespace Modules\Contestants\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateContestantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'country_id' => ['required', 'string', 'uuid'],
            'full_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date_format:Y-m-d'],
            'gender' => ['required', 'string', 'in:male,female'],
            'phone_number' => ['required', 'string', 'max:20'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'photo_media_asset_id' => ['nullable', 'string', 'uuid'],
        ];
    }
}
