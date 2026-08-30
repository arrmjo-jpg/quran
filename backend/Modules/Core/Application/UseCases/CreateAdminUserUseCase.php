<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Domain\Entities\User;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Domain\ValueObjects\Email;
use Modules\Core\Domain\ValueObjects\Locale;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Mail\InvitationMail;
use Modules\Notifications\Contracts\NotificationsServiceContract;
use Throwable;

/**
 * Creates an administrator account and invites its owner — ADR-016 Q7, D14.
 *
 * This is the endpoint ADR-015 said would never exist. ADR-016 supersedes that
 * decision, and the shape it takes is what makes the reversal safe: the
 * administrator creates the account and chooses its roles, and the person
 * named on it chooses the password. No administrator ever knows another
 * account's credential — not from the response, not from a log, not from the
 * link, which goes to the invitee's mailbox and nowhere else.
 *
 * THE MAIL IS SENT AFTER THE TRANSACTION COMMITS, not inside it. An email
 * carrying a token for an invitation that was rolled back is worse than no
 * email: the link would be permanently dead and the recipient would have no
 * way to know why. Committing first means a sent link always corresponds to a
 * stored invitation.
 *
 * The reverse failure — committed account, mail that never sends — is the one
 * this accepts. It leaves an account visible in the users list as pending with
 * an invitation that can be resent (Epic 12), which is recoverable. The other
 * ordering is not.
 *
 * ADR-020 D2 makes that accepted failure VISIBLE rather than merely tolerated:
 * the send is recorded before it is attempted and marked sent or failed after,
 * so "the account was created and the mail did not go" is a row somebody can
 * find instead of a silence.
 */
final class CreateAdminUserUseCase
{
    public function __construct(
        private UserRepositoryContract $users,
        private IssueInvitationUseCase $issueInvitation,
        private SyncUserRolesUseCase $syncRoles,
        // The Notifications module's public boundary, never its internals --
        // ADR-002, ADR-020 D7.
        private NotificationsServiceContract $notifications,
    ) {}

    /**
     * @param  array<int, string>  $roleIds
     */
    public function execute(
        string $email,
        string $name,
        array $roleIds,
        ?string $byUserId = null,
        string $locale = 'ar',
    ): User {
        [$user, $token] = DB::transaction(function () use ($email, $name, $roleIds, $byUserId, $locale): array {
            $user = User::invite(
                id: UserId::generate(),
                email: new Email($email),
                name: $name,
                type: UserType::admin(),
                preferredLocale: new Locale($locale),
            );

            $this->users->save($user);

            // Through the use case, not the aggregate, so PE-1 applies: an
            // administrator cannot grant a new account roles exceeding their
            // own permissions. Enforcing it at creation is the point — the
            // alternative is discovering the over-grant days later when the
            // invitee signs in with more authority than the person who
            // invited them.
            if ($roleIds !== []) {
                $this->syncRoles->execute((string) $user->id, $roleIds, $byUserId);
            }

            $issued = $this->issueInvitation->execute((string) $user->id, $byUserId);

            return [$user, $issued['token']];
        });

        // ADR-020 D2. The platform's only real send, and until now it wrote
        // no record: notification_logs held zero rows while mail went out.
        // Recorded through the module's contract (D7), never by touching its
        // tables -- ADR-002.
        //
        // The invitation is MANDATORY (D9): it is the only way an account can
        // be claimed, so no preference is consulted before sending it. That is
        // deliberate and is the reason preferences are a list of what may be
        // declined rather than a switch over everything.
        $notificationId = $this->notifications->record(
            userId: (string) $user->id,
            channel: 'email',
            templateKey: 'invitation.created',
            payload: ['name' => $name, 'expires_in_days' => IssueInvitationUseCase::TTL_DAYS],
        );

        // The recipient address is deliberately NOT in the payload. The log is
        // read by administrators looking at other people's notifications, and
        // it already carries user_id -- storing the address as well would put
        // an email in a screen that has no reason to show one.
        try {
            Mail::to($email)->send(new InvitationMail(
                name: $name,
                acceptUrl: rtrim((string) config('core.admin_url'), '/').'/invitations/accept?token='.$token,
                expiresInDays: IssueInvitationUseCase::TTL_DAYS,
            ));

            $this->notifications->markSent($notificationId);
        } catch (Throwable $e) {
            // The account exists and its invitation token is valid -- the
            // transaction above already committed. Failing the whole request
            // now would leave an account nobody can reach AND report failure,
            // when the truth is narrower: the account was created and the mail
            // did not go. That is exactly what a failed log row says, and what
            // the retry exists to act on.
            $this->notifications->markFailed($notificationId, $e->getMessage());
        }

        return $user;
    }
}
