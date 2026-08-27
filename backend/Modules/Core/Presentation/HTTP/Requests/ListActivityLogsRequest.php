<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The activity feed's query string — ADR-017 D3.
 *
 * Filters, not free-text search. Every column worth narrowing by is an exact
 * value the caller already holds — an entity, an actor, an action — and a
 * LIKE over a JSON payload would be a slow scan offering to match values the
 * events deliberately do not carry.
 */
final class ListActivityLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Reading one entity's history is the primary use, and the two
            // travel together: an id without a type could collide across
            // tables, so asking for one requires the other.
            // NO `sometimes` ON THESE TWO, deliberately. `sometimes` means
            // "skip every rule when the field is absent" — which skips
            // `required_with` too, so an entity_id with no entity_type sailed
            // through and filtered on a type of null. `nullable` still allows
            // both to be omitted.
            'entity_type' => ['nullable', 'string', 'max:50', 'required_with:entity_id'],
            'entity_id' => ['nullable', 'uuid', 'required_with:entity_type'],

            'actor_id' => ['sometimes', 'nullable', 'uuid'],
            'action' => ['sometimes', 'nullable', 'string', 'max:100'],
            'correlation_id' => ['sometimes', 'nullable', 'uuid'],

            // Bounded by occurred_at — when things happened — rather than by
            // created_at, because a backdated entry belongs in the window it
            // describes, not the one it was typed in (D3).
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],

            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
