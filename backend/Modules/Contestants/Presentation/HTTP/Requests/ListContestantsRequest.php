<?php

declare(strict_types=1);

namespace Modules\Contestants\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the contestant list's query string.
 *
 * `q` is the parameter this endpoint has always accepted and is kept so the
 * existing contract still works; `search` is accepted alongside it because it
 * is what every other admin list uses (users, centres, circles). One response
 * shape, two accepted spellings of the same input — not two contracts.
 */
final class ListContestantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country_id' => ['sometimes', 'nullable', 'uuid', 'exists:countries,id'],
            'gender' => ['sometimes', 'nullable', 'string', 'in:male,female'],
            'with_deleted' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function searchTerm(): ?string
    {
        return $this->validated('search') ?? $this->validated('q');
    }
}
