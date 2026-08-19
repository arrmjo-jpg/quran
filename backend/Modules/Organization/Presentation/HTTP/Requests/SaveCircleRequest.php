<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by create and update.
 *
 * center_id is required on create and prohibited on update, mirroring
 * SaveCenterRequest's treatment of country_id: a circle cannot move between
 * centres (see Circle::update), so accepting the field on update would mean
 * accepting a value that is silently discarded. `prohibited` tells the caller
 * rather than overruling them quietly.
 */
final class SaveCircleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        return [
            'name' => ['required', 'string', 'max:255'],
            'center_id' => [$isCreate ? 'required' : 'prohibited', 'string', 'uuid', 'exists:centers,id'],

            // Nullable on both: a circle may run before anyone is appointed to
            // it, and passing null is how an appointment is withdrawn. D9 makes
            // the supervisor a User, so this validates against users and not
            // against some separate supervisor table that does not exist.
            'supervisor_user_id' => ['nullable', 'string', 'uuid', 'exists:users,id'],
        ];
    }
}
