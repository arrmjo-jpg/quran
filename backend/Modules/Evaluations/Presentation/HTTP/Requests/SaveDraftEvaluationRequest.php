<?php

declare(strict_types=1);

namespace Modules\Evaluations\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveDraftEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'criteria_scores' => ['nullable', 'array'],
            'criteria_scores.*.criterion_id' => ['required_with:criteria_scores', 'string', 'uuid'],
            'criteria_scores.*.score' => ['required_with:criteria_scores', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
