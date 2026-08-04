<?php

declare(strict_types=1);

namespace Modules\Evaluations\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PublishResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'min_qualification_threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
