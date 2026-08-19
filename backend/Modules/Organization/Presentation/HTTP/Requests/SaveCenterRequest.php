<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by create and update.
 *
 * country_id is required on create and absent on update — a centre cannot
 * change country (see UpdateCenterUseCase), so accepting the field on update
 * would mean accepting a value that is silently discarded.
 */
final class SaveCenterRequest extends FormRequest
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
            'country_id' => [$isCreate ? 'required' : 'prohibited', 'string', 'uuid', 'exists:countries,id'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],

            // Both or neither, enforced here as well as in the value object so
            // the caller gets a field error rather than a domain exception.
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }
}
