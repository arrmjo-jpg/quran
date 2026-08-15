<?php

declare(strict_types=1);

namespace Modules\Competition\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Archiving a season that ran its full course. The reason is optional
 * here — the season completed normally, so there is nothing to justify —
 * unlike cancelling, where the aggregate requires one.
 */
final class ArchiveSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
