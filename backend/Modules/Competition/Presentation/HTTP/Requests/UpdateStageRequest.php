<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Competition\Domain\Entities\Stage;

final class UpdateStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Stage::TYPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'evaluation_template_id' => ['nullable', 'uuid', 'exists:evaluation_templates,id'],

            // Optional here, unlike on create: an update may touch only
            // the schedule and leave every locale's content as it stands.
            'translations' => ['sometimes', 'array'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.public_name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
        ];
    }
}
