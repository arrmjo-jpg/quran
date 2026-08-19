<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Carries an invitation link to the person it was issued for.
 *
 * ONE TRANSACTIONAL MESSAGE, NOT A NOTIFICATION SYSTEM. The Notifications
 * module (Epic 8) is about in-app notifications, digests and retries; this is
 * a single email the identity flow cannot work without, and building the
 * former to send the latter would pull an entire epic forward. When Epic 8
 * arrives this can move behind its contract without any behaviour changing.
 *
 * THE LINK IS THE ONLY PLACE THE TOKEN GOES. It is not returned by the API,
 * not shown to the administrator who created the account, and not logged —
 * because an administrator holding the link could claim the account and set
 * its password, which is exactly what ADR-016 D14 forbids. Email is the
 * delivery channel precisely because it reaches the invitee and nobody else.
 */
final class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $acceptUrl,
        public readonly int $expiresInDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('You have been invited to the Quran Competitions platform'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invitation');
    }
}
