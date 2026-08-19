<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use DomainException;

/**
 * The presented invitation cannot be redeemed.
 *
 * Deliberately says no more than that. Unknown token, wrong token, expired,
 * already used, or issued for an account that has since been claimed — all
 * arrive here, and all leave as the same response.
 *
 * The reason is that this is reached from a public endpoint whose input is a
 * secret. An error that distinguished "no such token" from "expired token"
 * would answer "does this token exist?" for anyone willing to guess, which
 * converts a 64-character secret into a search problem with feedback.
 *
 * The cost is real and accepted: an invitee whose link has genuinely expired
 * is told only that it does not work. Telling them why, and offering a new
 * one, belongs to the resend flow in Epic 12 — a place where the person has
 * already been identified by other means.
 */
final class InvalidInvitationException extends DomainException
{
    public function __construct()
    {
        parent::__construct('This invitation link is not valid.');
    }
}
