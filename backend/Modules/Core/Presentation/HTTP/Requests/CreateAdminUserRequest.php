<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Unique across soft-deleted rows too: users are never hard
            // deleted (ADR-016 D5), so a deleted account still holds its
            // address and re-inviting it would collide on the column. The
            // right answer there is restore, not create — Story 5.
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],

            // Roles at creation rather than afterwards, so PE-1 refuses an
            // over-privileged grant while the administrator is still looking
            // at the form.
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'uuid', 'exists:roles,id'],

            'locale' => ['sometimes', 'string', 'in:ar,en,es'],

            // No password field, and there never will be one. ADR-016 D14:
            // the account's owner chooses it, through the invitation.
        ];
    }
}
