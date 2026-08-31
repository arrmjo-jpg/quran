<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Exceptions;

use DomainException;

/**
 * Nothing knows how to rebuild this notification's message — ADR-020 D5.
 *
 * A retry re-dispatches the send job, and that job needs a Mailable. The log
 * stores `template_key` and `payload`; it deliberately stores neither the
 * recipient's address nor anything secret, so the message has to be rebuilt by
 * whichever module owns the template.
 *
 * If that module registered no factory, the honest answer is this exception.
 * The alternative — returning success and queueing nothing — is the exact
 * failure Epic 8 exists to remove, and it is worth an explicit refusal to
 * avoid reintroducing it one template at a time.
 */
final class TemplateNotRetryableException extends DomainException
{
    public static function for(string $templateKey): self
    {
        return new self(
            "No module can rebuild the message for template '{$templateKey}', so it cannot be retried."
        );
    }
}
