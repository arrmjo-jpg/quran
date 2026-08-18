<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancelling a season before registration ever opened. Season::cancel()
 * rejects a blank reason itself; requiring it here means the caller gets a
 * field-level 422 rather than a thrown domain error.
 */
final class CancelSeasonRequest extends FormRequest
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
