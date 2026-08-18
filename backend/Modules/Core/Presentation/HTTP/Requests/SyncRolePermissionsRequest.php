<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // `present` and not `required`: clearing a role's permissions
            // is a legitimate edit, and `required` rejects an empty array.
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ];
    }
}
