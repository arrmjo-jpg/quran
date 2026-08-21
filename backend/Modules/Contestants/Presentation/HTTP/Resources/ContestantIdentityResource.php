<?php

declare(strict_types=1);

namespace Modules\Contestants\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Services\EligibilityService;
use Modules\Core\Contracts\ResolvedUserDTO;
use Modules\Countries\Contracts\ResolvedCountryDTO;
use Modules\Media\Contracts\ResolvedMediaDTO;
use Modules\Organization\Contracts\ResolvedMembershipDTO;

/**
 * Identity 360 — one contestant and everything that links to them
 * (ADR-016 D16, D19, D20).
 *
 * A RELATIONSHIP VIEW, NOT A RECORD VIEW, and the difference decides what is
 * here. D18's rule — *shows what identifies, explains, or links; does not
 * show what describes* — is why `national_id` is absent although the caller
 * holds the permission that would reveal it on `GET /admin/contestants/{id}`.
 * An identity document describes a person; it links nothing. Whoever needs
 * it opens the contestant deliberately, which is the act Story 1 reserved it
 * for.
 *
 * The account branch is whatever Core hands over, which is exactly the four
 * fields D18 admits — enforced by ResolvedUserDTO having nowhere to put a
 * fifth, not by this class remembering to leave one out.
 *
 * `withheld` names the branches suppressed because the reader lacks the
 * permission that governs them (D20). It is what makes an empty array
 * readable: `memberships: []` alone cannot distinguish a contestant who has
 * never joined a circle from a reader who may not be told either way, and
 * showing the first when the second is true is the panel asserting something
 * untrue about a person.
 *
 * There is no applications or appeals branch. No endpoint returns either
 * against a contestant, and a branch that were always empty would be
 * indistinguishable from a contestant who has never applied.
 *
 * `photo` carries no `withheld` entry, unlike the memberships branch. Every
 * seeded role holds media.view, so a withheld photo would be an unreachable
 * state dressed as a permission boundary (D24). It still resolves through
 * MediaServiceContract rather than a model read, because ADR-002 is about
 * coupling and not about permissions.
 */
final class ContestantIdentityResource extends JsonResource
{
    /**
     * @param  array<int, ResolvedMembershipDTO>  $memberships
     * @param  array<int, string>  $withheld
     */
    public function __construct(
        Contestant $contestant,
        private readonly ?ResolvedUserDTO $user,
        private readonly ?ResolvedCountryDTO $country,
        private readonly array $memberships,
        private readonly array $withheld,
        private readonly string $locale,
        // Handed in finished rather than calculated here. The arithmetic
        // belongs to BirthDate, which has done it since the module was
        // written; a presentation class doing domain maths is the thing
        // ADR-012 draws its layers to prevent (D24).
        private readonly ?int $age = null,
        private readonly ?ResolvedMediaDTO $photo = null,
    ) {
        parent::__construct($contestant);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Contestant $contestant */
        $contestant = $this->resource;

        return [
            'contestant' => [
                'id' => $contestant->id->value,
                'user_id' => $contestant->userId,
                'country_id' => $contestant->countryId,
                'full_name' => $contestant->getFullName(),
                'date_of_birth' => (string) $contestant->getDateOfBirth(),
                'gender' => (string) $contestant->getGender(),
                'phone_number' => $contestant->getPhoneNumber(),
                'age' => $this->age,

                // Both. The bare id was in this contract before Story 4 and
                // a consumer already reads it, so `photo` arrives beside it
                // rather than replacing it.
                'photo_media_asset_id' => $contestant->getPhotoMediaId(),
                'photo' => $this->photo === null ? null : [
                    'id' => $this->photo->id,
                    'url' => $this->photo->url,
                    'thumb' => $this->photo->thumb,
                    'mime_type' => $this->photo->mimeType,
                    'is_image' => $this->photo->isImage,
                ],

                'is_deleted' => $contestant->isDeleted(),
                'profile_completeness' => (new EligibilityService)->calculateProfileCompleteness($contestant),
            ],

            // Null when the account behind user_id no longer resolves. That
            // is a broken link rather than an absent one — contestants.user_id
            // is NOT NULL and RESTRICT — so it is reported as null rather
            // than invented, and the screen can say the account is missing.
            'user' => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'status' => $this->user->status,
                'type' => $this->user->type,
            ],

            'country' => $this->country === null ? null : [
                'id' => $this->country->id,
                'iso2' => $this->country->iso2,
                // The requested locale, then Arabic, then the ISO code. The
                // fallback chain is here rather than in the client because a
                // country with no Spanish translation is a data gap, and a
                // blank cell would read as a country with no name.
                'name' => $this->country->name[$this->locale]
                    ?? $this->country->name['ar']
                    ?? $this->country->iso2,
            ],

            'memberships' => array_map(
                fn (ResolvedMembershipDTO $membership): array => [
                    'id' => $membership->id,
                    'circle_id' => $membership->circleId,
                    'circle_name' => $membership->circleName,
                    'center_id' => $membership->centerId,
                    'center_name' => $membership->centerName,
                    'center_city' => $membership->centerCity,
                    'joined_at' => $membership->joinedAt,
                    'left_at' => $membership->leftAt,
                    'is_active' => $membership->isActive,
                    'reason' => $membership->reason,
                ],
                $this->memberships,
            ),

            'withheld' => $this->withheld,
        ];
    }
}
