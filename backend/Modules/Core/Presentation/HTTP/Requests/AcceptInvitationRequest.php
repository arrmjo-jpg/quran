<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Shape only, and deliberately loose: 64 lowercase hex is what
            // generate() produces, but rejecting anything else here would tell
            // a caller their guess was the wrong *shape*, which is a hint the
            // use case is careful not to give. Length is capped so an
            // oversized body cannot be used to probe the hashing path.
            'token' => ['required', 'string', 'max:255'],

            // 'confirmed' expects password_confirmation. Someone setting a
            // password they cannot retype has locked themselves out of an
            // account nobody else can unlock — no administrator can set it for
            // them (D14), so the retype is the only safeguard available.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
