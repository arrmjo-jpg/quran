<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     *
     * The locale set is `ar,en,es` — the languages the platform actually
     * ships. It read `ar,en,fr` until 2026-08-20, which was wrong in both
     * directions at once: `fr` has no translations anywhere in frontend-admin,
     * and `es` does, so an account could put itself into a language the
     * interface cannot render and could not choose one it can. It also
     * disagreed with CreateAdminUserRequest and UpdateUserRequest, which write
     * the same column and both accept `es` — meaning an administrator could
     * set a locale for someone that the person could not set for themselves.
     *
     * RegisterUserRequest still carries `fr`. That is a different surface —
     * public registration rather than self-service — and is left to its own
     * change rather than swept in here.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'preferred_locale' => ['sometimes', 'string', 'in:ar,en,es'],
        ];
    }
}
