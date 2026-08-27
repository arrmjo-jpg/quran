<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The login history's query string — ADR-018 D2.
 *
 * Authorisation is the route's (`can:security.view`), not this class's.
 */
final class ListLoginHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // "Show me this account's attempts" is the primary use: an admin
            // investigating one person, or one person's own history.
            'user_id' => ['sometimes', 'nullable', 'uuid'],

            // Constrained to the vocabulary the repository maps. Free text
            // here would silently match nothing and read as "no attempts",
            // which on a security screen is the wrong kind of empty.
            'outcome' => ['sometimes', 'nullable', 'string', 'in:success,invalid_credentials,account_inactive,invalid_request,rate_limited,failed'],

            // Not validated as an IP address. audit_logs stores whatever the
            // request presented, and refusing to search for a malformed value
            // would make the odd rows the least findable ones.
            'ip' => ['sometimes', 'nullable', 'string', 'max:45'],

            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],

            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
