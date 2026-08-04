<?php

declare(strict_types=1);

namespace Modules\Countries\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'iso2' => ['required', 'string', 'size:2', 'unique:countries,iso_code'],
            'iso3' => ['required', 'string', 'size:3', 'unique:countries,iso3_code'],
            'phone_code' => ['required', 'string', 'max:10'],
            'flag_url' => ['required', 'string', 'url'],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['required', 'string', 'max:255'],
        ];
    }
}
