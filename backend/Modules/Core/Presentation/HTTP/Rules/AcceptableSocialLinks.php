<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;
use Modules\Core\Domain\ValueObjects\SocialLinks;

/**
 * Validates social links by asking the value object — ADR-016 D2, Q5.
 *
 * THE RULE OWNS NO KNOWLEDGE. It does not list the eight platforms, the hosts
 * or the scheme rules; it hands the input to SocialLinks::fromArray and
 * reports what that refuses. Restating the rules here would create a second
 * copy to keep in step, and the copy in a request class is the one that gets
 * forgotten.
 *
 * IT EXISTS SO THE REFUSAL IS A 422 AND NOT A 500. The value object throws,
 * which is right for a domain invariant and wrong as an HTTP response. Failing
 * here also means nothing is written: the request never reaches the use case,
 * so a bad link in the same payload as a good biography rejects both rather
 * than saving half.
 */
final class AcceptableSocialLinks implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === []) {
            return;
        }

        if (! is_array($value)) {
            $fail('The :attribute must be an object of platform links.');

            return;
        }

        try {
            SocialLinks::fromArray($value);
        } catch (InvalidArgumentException $e) {
            // The value object's message names the platform and what was
            // wrong with it, which is more useful than anything this class
            // could phrase without duplicating its rules.
            $fail($e->getMessage());
        }
    }
}
