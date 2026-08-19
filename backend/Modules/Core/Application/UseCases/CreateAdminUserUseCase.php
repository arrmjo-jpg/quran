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
 */
final class CreateAdminUserUseCase
{
    public function __construct(
        private UserRepositoryContract $users,
        private IssueInvitationUseCase $issueInvitation,
        private SyncUserRolesUseCase $syncRoles,
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

        Mail::to($email)->send(new InvitationMail(
            name: $name,
            acceptUrl: rtrim((string) config('core.admin_url'), '/').'/invitations/accept?token='.$token,
            expiresInDays: IssueInvitationUseCase::TTL_DAYS,
        ));

        return $user;
    }
}
