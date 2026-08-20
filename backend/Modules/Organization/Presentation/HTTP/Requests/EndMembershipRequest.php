<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ending a membership.
 *
 * `reason` is required rather than optional, matching the aggregate. A closed
 * membership with no explanation is the row an administrator finds a year
 * later and cannot act on: it says a contestant left and nothing about whether
 * they moved, stopped attending, or were entered by mistake.
 */
final class EndMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'left_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
