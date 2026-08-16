<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The reason is required because nothing else records why a closed
 * registration window was reopened — the season row only carries the
 * resulting status, and the next close overwrites it. The
 * SeasonRegistrationReopened event is the sole trace, so it must not be
 * empty.
 */
final class ReopenRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
