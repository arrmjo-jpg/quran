<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Entities;

use InvalidArgumentException;
use Modules\Core\Domain\ValueObjects\SocialLinks;
use Modules\Core\Domain\ValueObjects\UserId;

/**
 * An administrator's profile — ADR-016 D1.
 *
 * Deliberately separate from the User aggregate. `users` answers "may this
 * request proceed"; this answers "what does this person want shown". Keeping
 * them apart is what lets the admin path edit an account without acquiring the
 * ability to rewrite someone's biography — which is the whole reason Story 3
 * did not simply widen UpdateUserProfileUseCase.
 *
 * EVERY FIELD IS OPTIONAL, including all of them at once. An account that
 * never opened the form has a profile in the sense that one can be built for
 * it, and nothing in it. There is no such thing as an incomplete profile here,
 * so there is nothing to validate about completeness.
 */
final class UserProfile
{
    private function __construct(
        public readonly string $id,
        public readonly UserId $userId,
        private ?string $displayName,
        private ?string $bio,
        private ?SocialLinks $socialLinks,
        private ?string $avatarMediaId,
    ) {}

    public static function create(string $id, UserId $userId): self
    {
        return new self($id, $userId, null, null, null, null);
    }

    /** Rebuilt from storage. */
    public static function reconstitute(
        string $id,
        UserId $userId,
        ?string $displayName,
        ?string $bio,
        ?SocialLinks $socialLinks,
        ?string $avatarMediaId,
    ): self {
        return new self($id, $userId, $displayName, $bio, $socialLinks, $avatarMediaId);
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function getSocialLinks(): ?SocialLinks
    {
        return $this->socialLinks;
    }

    public function getAvatarMediaId(): ?string
    {
        return $this->avatarMediaId;
    }

    /**
     * Sets the display name, or clears it.
     *
     * Clearing is a real operation, not an accident of a null default: someone
     * who set a display name and then wants their account name shown instead
     * needs a way to say so. The caller decides whether this is called at all —
     * an omitted field never reaches here.
     */
    public function setDisplayName(?string $displayName): void
    {
        $trimmed = $displayName === null ? null : trim($displayName);

        if ($trimmed !== null && mb_strlen($trimmed) > 255) {
            throw new InvalidArgumentException('A display name cannot exceed 255 characters.');
        }

        $this->displayName = $trimmed === '' ? null : $trimmed;
    }

    public function setBio(?string $bio): void
    {
        $trimmed = $bio === null ? null : trim($bio);

        if ($trimmed !== null && mb_strlen($trimmed) > 1000) {
            throw new InvalidArgumentException('A biography cannot exceed 1000 characters.');
        }

        $this->bio = $trimmed === '' ? null : $trimmed;
    }

    /**
     * Replaces the whole set, or clears it.
     *
     * Never merges. The links are one value under D2, so sending two of them
     * means the profile now has two — not that two were added to whatever was
     * there. An empty set clears; that is what `{}` means on the wire.
     */
    public function setSocialLinks(?SocialLinks $socialLinks): void
    {
        $this->socialLinks = $socialLinks === null || $socialLinks->isEmpty() ? null : $socialLinks;
    }
}
