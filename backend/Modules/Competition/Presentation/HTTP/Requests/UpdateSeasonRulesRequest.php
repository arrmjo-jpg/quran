<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSeasonRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'min_age' => ['required', 'integer', 'min:1', 'max:120'],
            'max_age' => ['required', 'integer', 'min:1', 'max:120', 'gte:min_age'],
            'participation_type_id' => ['required', 'uuid', 'exists:participation_types,id'],
            'tajweed_level_id' => ['required', 'uuid', 'exists:tajweed_levels,id'],
            'country_ids' => ['required', 'array', 'min:1'],
            'country_ids.*' => ['uuid', 'exists:countries,id'],
        ];
    }
}
