<?php

declare(strict_types=1);

namespace Modules\Evaluations\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveAppealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'admin_response' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
