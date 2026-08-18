<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:100', 'unique:seasons,slug'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'registration_start' => ['required', 'date'],
            'registration_end' => ['required', 'date', 'after:registration_start'],
            'start_date' => ['required', 'date', 'after_or_equal:registration_end'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'title_es' => ['required', 'string', 'max:255'],
            'public_name_ar' => ['required', 'string', 'max:255'],
            'public_name_en' => ['required', 'string', 'max:255'],
            'public_name_es' => ['required', 'string', 'max:255'],
        ];
    }
}
