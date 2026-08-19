<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['sometimes', 'string', 'in:ar,en,es'],

            // EMAIL IS DELIBERATELY NOT EDITABLE HERE, and its absence is a
            // decision rather than an oversight. The address is the login
            // identity and the delivery channel an invitation was sent to;
            // changing it silently repoints both, with no verification step to
            // confirm the new mailbox is reachable or belongs to the same
            // person. That needs email verification, which this epic does not
            // build. Until then an address change is a support action, not a
            // form field.
        ];
    }
}
