<?php

declare(strict_types=1);

namespace Modules\Notifications\Contracts;

use Illuminate\Mail\Mailable;

/**
 * What a rebuilt notification needs in order to be sent again — ADR-020 D5.
 *
 * BOTH HALVES COME FROM THE OWNING MODULE, and that is the point. The
 * Notifications log stores `user_id`, never an address: administrators read
 * other people's notification rows, so the recipient is resolved at send time
 * rather than copied into a table. Notifications has no way to turn a user id
 * into an email — asking Core for one would put a second cross-module
 * dependency where one already runs the other way.
 *
 * So the module that owns the template hands back both: it already knows the
 * account, and it is the only thing that knows how to build the message.
 */
final class NotificationRetryEnvelope
{
    public function __construct(
        public readonly string $recipient,
        public readonly Mailable $mail,
    ) {}
}
