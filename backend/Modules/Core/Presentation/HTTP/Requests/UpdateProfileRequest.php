<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Presentation\HTTP\Rules\AcceptableSocialLinks;

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

            // The profile half — ADR-016 D1 — nested, because that is how it
            // is read. A panel that sends back the object it was given must be
            // sending the shape that works; the alternative is an endpoint
            // that answers 200 to the wrong shape and saves none of it.
            'profile' => ['sometimes', 'array'],

            // `sometimes` throughout, so an omitted field is left alone while
            // one sent as null clears it.
            'profile.display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'profile.bio' => ['sometimes', 'nullable', 'string', 'max:1000'],

            // NOT `nullable`, unlike the two above. An empty object clears the
            // set and omitting the key leaves it alone, which covers both
            // intentions — so an explicit null would have to be given a third
            // meaning that no specification has chosen. Refused until one does.
            'profile.social_links' => ['sometimes', 'array', new AcceptableSocialLinks],
        ];
    }
}
