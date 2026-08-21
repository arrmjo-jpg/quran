<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     *
     * The locale set matches the Locale value object, and must: this request
     * validates a value that AuthController::register hands straight to
     * `new Locale(...)`. It read `ar,en,fr` until 2026-08-20, which was
     * harmless while the value object accepted `fr` too — a disagreement with
     * the interface and nothing worse. Narrowing the value object to the
     * languages the panel ships turned it into a 500: the request let `fr`
     * through and the domain threw, with nothing catching it.
     *
     * Registration was deliberately left out of that change because it is a
     * different surface from self-service. Leaving it also left this, which is
     * why it is fixed here rather than folded in there.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'locale' => ['nullable', 'string', 'in:ar,en,es'],
        ];
    }
}
