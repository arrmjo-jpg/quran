<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The payload must be the season's complete stage ordering. That the ids
 * are exactly the season's stages, each once, is checked by
 * ReorderStagesUseCase — it needs the season's current stages to say so,
 * which is a domain read, not a validation-rule concern.
 */
final class ReorderStagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stage_ids' => ['required', 'array', 'min:1'],
            'stage_ids.*' => ['uuid', 'exists:stages,id'],
        ];
    }
}
