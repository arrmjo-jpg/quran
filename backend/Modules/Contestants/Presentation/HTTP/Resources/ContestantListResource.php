<?php

declare(strict_types=1);

namespace Modules\Contestants\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Services\EligibilityService;

/**
 * A contestant as it appears in a list — Epic 4 Story 1.
 *
 * Identical to ContestantPrivateResource except that `national_id` is
 * absent, and that absence is the reason the class exists.
 *
 * WHY THE LIST DROPS IT. Before Story 1 the list returned every contestant
 * on the platform, each with their identity document in full — so a single
 * request handed the caller every national ID the system held. Bounding the
 * list with pagination reduces that per request but not in principle: paging
 * through it still collects them all. A list is a browsing surface, and
 * nothing in the admin UI displays or uses this field there. The detail
 * endpoint keeps it, because an administrator looking at one contestant may
 * legitimately need it and is looking at one person deliberately.
 *
 * This is a change to the contract the Golden Master pinned at d9b9000, made
 * openly rather than drifted into, and tested in both directions.
 *
 * `profile_completeness` is retained. It is computed from fields already in
 * memory and issues no query, and the admin drawer renders it as a badge from
 * the list row.
 */
final class ContestantListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Contestant $contestant */
        $contestant = $this->resource;

        return [
            'id' => $contestant->id->value,
            'user_id' => $contestant->userId,
            'country_id' => $contestant->countryId,
            'full_name' => $contestant->getFullName(),
            'date_of_birth' => (string) $contestant->getDateOfBirth(),
            'gender' => (string) $contestant->getGender(),
            'phone_number' => $contestant->getPhoneNumber(),
            'photo_media_asset_id' => $contestant->getPhotoMediaId(),
            'is_deleted' => $contestant->isDeleted(),
            'profile_completeness' => (new EligibilityService)->calculateProfileCompleteness($contestant),
        ];
    }
}
