<?php

declare(strict_types=1);

namespace Modules\Judges\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateJudgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'uuid', 'exists:users,id', 'unique:judges,user_id'],
            'full_name' => ['required', 'string', 'max:255'],
            'specialization' => ['required', 'string', 'in:tajweed,hifz,sawt,general'],
            'title' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
