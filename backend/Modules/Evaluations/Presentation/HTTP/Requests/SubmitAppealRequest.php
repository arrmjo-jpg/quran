<?php

declare(strict_types=1);

namespace Modules\Evaluations\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_id' => ['required', 'string', 'uuid'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }
}
