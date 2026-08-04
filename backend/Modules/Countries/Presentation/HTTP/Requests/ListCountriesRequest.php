<?php

declare(strict_types=1);

namespace Modules\Countries\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListCountriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'in:name,iso2,iso3,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
