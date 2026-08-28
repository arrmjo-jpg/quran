<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Domain\ReadModels\LoginAttempt;

/**
 * One login attempt — ADR-018 D2.
 *
 * Carries no password, no email and no token. The email is deliberate: an
 * attempt against an unknown address has no account behind it, and echoing the
 * string back would turn a security screen into a list of addresses somebody
 * guessed. `user_id` is null in that case, which says the same thing without
 * publishing the guess.
 */
final class LoginAttemptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var LoginAttempt $row */
        $row = $this->resource;

        return [
            'id' => $row->id,

            // Null means the email matched no account (ADR-018 D10).
            'user_id' => $row->userId,
            'user_type' => $row->userType,

            // Both: the name is what a reader understands, the number is what
            // they can correlate with the API logs.
            'outcome' => $row->outcome,
            'status' => $row->status,

            'ip' => $row->ip,
            'user_agent' => $row->userAgent,

            // Null on every row written before the client began sending the
            // header (D7) -- which is most of them, and not an error.
            'device_id' => $row->deviceId,

            'correlation_id' => $row->correlationId,
            'attempted_at' => $row->attemptedAt,
        ];
    }
}
