<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ADR-020 D4.
 *
 * VALIDATES SHAPE, NOT VOCABULARY. Whether a type exists and whether it may be
 * declined are questions the catalogue answers, and the service asks it — so
 * both rules live in one place and produce a message that says which type and
 * why, instead of a generic "the selected preferences.x is invalid".
 */
final class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Self-service: the route is authenticated, and the controller reads
        // the account from the request rather than the payload, so there is no
        // other account this could address.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array'],
            // Keys are notification types; the catalogue decides which are
            // real. `boolean` rather than a free value because a preference
            // has exactly two answers.
            'preferences.*' => ['required', 'boolean'],
        ];
    }
}
