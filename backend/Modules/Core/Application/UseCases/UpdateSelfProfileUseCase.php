<?php

declare(strict_types=1);

namespace Modules\Core\Application\UseCases;

use Modules\Core\Domain\Entities\UserProfile;
use Modules\Core\Domain\Repositories\UserProfileRepositoryContract;
use Modules\Core\Domain\ValueObjects\SocialLinks;
use Modules\Core\Domain\ValueObjects\UserId;
use Symfony\Component\Uid\Uuid;

/**
 * Edits the `user_profiles` half of an account — ADR-016 D1, D2.
 *
 * SEPARATE FROM UpdateUserProfileUseCase ON PURPOSE. That one edits `users`
 * and is called by the admin path; widening it to reach this table would have
 * handed every administrator the ability to rewrite someone else's biography
 * and links, which Story 3 explicitly does not grant. Two use cases is what
 * keeps that door shut by construction rather than by a check somewhere.
 *
 * NO TRANSACTION HERE. This is called alongside the account edit by
 * UpdateSelfUseCase, which owns the transaction for the whole operation — a
 * request that changes a name and a set of links must not be able to apply
 * half of itself.
 *
 * ABSENT IS NOT NULL. `$changes` carries only the fields the caller actually
 * sent, so omitting `bio` leaves the biography alone while sending it as null
 * clears it. Nullable parameters could not express that difference, and a
 * profile editor that silently wiped the field you did not mention would be
 * the worst possible reading of PATCH.
 */
final class UpdateSelfProfileUseCase
{
    public function __construct(
        private UserProfileRepositoryContract $profiles,
    ) {}

    /**
     * @param  array<string, mixed>  $changes  only the keys the caller sent:
     *                                         `display_name`, `bio`, `social_links`
     */
    public function execute(string $userId, array $changes): UserProfile
    {
        $id = new UserId($userId);

        // Created on first write rather than alongside the account. ADR-016
        // records the table as additive, so an account that has never edited
        // its profile simply has no row — not an empty one waiting.
        $profile = $this->profiles->findByUserId($id)
            ?? UserProfile::create((string) Uuid::v7(), $id);

        if (array_key_exists('display_name', $changes)) {
            $profile->setDisplayName($changes['display_name']);
        }

        if (array_key_exists('bio', $changes)) {
            $profile->setBio($changes['bio']);
        }

        if (array_key_exists('social_links', $changes)) {
            // Replaces the set, never merges: the links are one value under
            // D2, so `{}` clears and a set of two means the profile now has
            // exactly two. Built here rather than passed in already-built,
            // following how Coordinates is constructed inside the use case
            // that needs it.
            $profile->setSocialLinks(SocialLinks::fromArray($changes['social_links']));
        }

        $this->profiles->save($profile);

        return $profile;
    }
}
