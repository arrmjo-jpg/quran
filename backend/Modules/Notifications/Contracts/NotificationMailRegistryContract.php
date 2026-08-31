<?php

declare(strict_types=1);

namespace Modules\Notifications\Contracts;

use Closure;
use Modules\Notifications\Domain\Exceptions\TemplateNotRetryableException;

/**
 * How a notification's message is rebuilt so it can be sent again — ADR-020 D5.
 *
 * WHY A REGISTRY AND NOT A LOOKUP TABLE OF TEMPLATES. ADR-020 keeps templating
 * out of scope, and this does not smuggle it back in: nothing here renders,
 * stores or edits a message. It records which module can rebuild which
 * `template_key`, so a retry has something to dispatch.
 *
 * IT INVERTS THE DEPENDENCY DELIBERATELY. Notifications must not import Core
 * to rebuild an invitation; Core registers itself at boot instead. The
 * arrangement scales to the templates Epic 8 does not cover — every module
 * teaches this one about its own mail and Notifications stays ignorant of all
 * of them (ADR-002).
 *
 * A TEMPLATE MAY LEGITIMATELY BE ABSENT. Registration is a claim that the
 * message can be reconstructed from `payload` alone, and not every message
 * can: an invitation's accept token is unrecoverable by design, so Core's
 * factory issues a fresh invitation rather than pretending to reproduce the
 * old one. A module that cannot do either registers nothing, and rebuild()
 * refuses out loud.
 */
interface NotificationMailRegistryContract
{
    /**
     * Teach this module how to rebuild one template's message.
     *
     * @param  Closure(string $userId, array<string, mixed> $payload): NotificationRetryEnvelope  $factory
     */
    public function register(string $templateKey, Closure $factory): void;

    public function has(string $templateKey): bool;

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws TemplateNotRetryableException
     */
    public function rebuild(string $templateKey, string $userId, array $payload): NotificationRetryEnvelope;
}
