<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Shape only. Membership in the catalogue is the use case's
            // decision (CreateRoleUseCase::assertKnown), because a
            // validation rule here would be a second place that knows what
            // a permission is, and the two could disagree.
            // Uniqueness here so a taken name is a field error rather than
            // a 409. The use case checks it again — this is a nicer message,
            // not the guarantee.
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ];
    }
}
