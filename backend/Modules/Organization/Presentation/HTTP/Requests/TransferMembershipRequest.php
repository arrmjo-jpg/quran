<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Moving a contestant to another circle.
 *
 * Keyed on the contestant rather than on the membership being ended: the
 * caller knows who is moving, and which membership is currently open is a fact
 * the system holds — asking the client for it would invite them to send a
 * stale one. `to_circle_id` is the only circle in the payload for the same
 * reason; where they are now is not the client's to assert.
 *
 * One `at` covers both ends of the move. A transfer is a single moment, and
 * two independent dates would permit a gap or an overlap between the two
 * memberships it produces.
 */
final class TransferMembershipRequest extends FormRequest
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
            'to_circle_id' => ['required', 'string', 'uuid', 'exists:circles,id'],
            'reason' => ['required', 'string', 'max:500'],
            'at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
