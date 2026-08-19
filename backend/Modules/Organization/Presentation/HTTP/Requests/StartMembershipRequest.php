<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Enrolling a contestant in a circle.
 *
 * `joined_at` is optional and may be backdated, because enrolment is recorded
 * after the fact more often than as it happens. It may not be in the future:
 * the aggregate refuses that too, and the two agree on purpose — this one
 * gives the field-level message, the aggregate holds the rule for every caller
 * that does not come through HTTP.
 */
final class StartMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'contestant_id' => ['required', 'string', 'uuid', 'exists:contestants,id'],
            'circle_id' => ['required', 'string', 'uuid', 'exists:circles,id'],
            'joined_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
