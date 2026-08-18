<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SyncUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The intended FINAL set, not a delta: two clients sending add
            // and remove separately could interleave and leave a set neither
            // intended. `present` rather than `required` because stripping
            // every role is a legitimate change.
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'uuid', 'exists:roles,id'],
        ];
    }
}
