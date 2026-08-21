<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Repositories;

use Modules\Core\Domain\Entities\UserProfile;
use Modules\Core\Domain\Repositories\UserProfileRepositoryContract;
use Modules\Core\Domain\ValueObjects\SocialLinks;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Infrastructure\Database\Models\UserProfileModel;

/** Eloquent is confined to this class — ADR-002. */
final class UserProfileRepository implements UserProfileRepositoryContract
{
    public function findByUserId(UserId $userId): ?UserProfile
    {
        $row = UserProfileModel::query()->where('user_id', $userId->value)->first();

        if ($row === null) {
            return null;
        }

        return UserProfile::reconstitute(
            id: (string) $row->id,
            userId: $userId,
            displayName: $row->display_name,
            bio: $row->bio,
            // Rebuilt through the value object, not handed over as a raw
            // array: a row that somehow holds a link the platform no longer
            // accepts should surface here rather than reach a screen.
            socialLinks: SocialLinks::fromArray($row->social_links),
            avatarMediaId: $row->avatar_media_id,
        );
    }

    public function save(UserProfile $profile): void
    {
        UserProfileModel::query()->updateOrCreate(
            ['user_id' => $profile->userId->value],
            [
                'id' => $profile->id,
                'display_name' => $profile->getDisplayName(),
                'bio' => $profile->getBio(),
                // Null rather than `{}` when there are no links. The column is
                // nullable precisely so "never set anything" and "set nothing"
                // are the same state, which is what Q5 decided when it said an
                // unset platform carries no key.
                'social_links' => $profile->getSocialLinks()?->toArray(),

                // avatar_media_id is deliberately NOT written. Story 3 reads
                // the column and adds no way to set it: doing so needs an
                // upload, and uploading needs `media.create`, which
                // self-service does not carry. Listing it here would make the
                // repository silently clear an avatar set by any other means.
            ]
        );
    }
}
