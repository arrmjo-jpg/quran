<?php

declare(strict_types=1);

namespace Modules\Countries\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone_code' => ['sometimes', 'string', 'max:10'],
            'flag_url' => ['sometimes', 'string', 'url'],
            'name_ar' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
