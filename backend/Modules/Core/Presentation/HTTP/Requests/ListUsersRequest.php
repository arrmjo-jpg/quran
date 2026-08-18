<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Domain\ValueObjects\UserType;

final class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            // The value object is the single definition of the two permitted
            // types, so the filter cannot drift from the column.
            'type' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', UserType::ALLOWED)],
            'is_active' => ['sometimes', 'boolean'],
            'with_deleted' => ['sometimes', 'boolean'],
            'role' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
