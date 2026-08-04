<?php

declare(strict_types=1);

namespace Modules\Evaluations\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'criteria_scores' => ['required', 'array'],
            'criteria_scores.*.criterion_id' => ['required', 'string', 'uuid'],
            'criteria_scores.*.score' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
