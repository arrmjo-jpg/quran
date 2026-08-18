<?php

declare(strict_types=1);

namespace Modules\Contestants\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contestants\Domain\Entities\Contestant;
use Modules\Contestants\Domain\Services\EligibilityService;

final class ContestantPrivateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Contestant|object $resource */
        $resource = $this->resource;
        $eligibilityService = new EligibilityService;

        if ($resource instanceof Contestant) {
            $completeness = $eligibilityService->calculateProfileCompleteness($resource);

            return [
                'id' => $resource->id->value,
                'user_id' => $resource->userId,
                'country_id' => $resource->countryId,
                'full_name' => $resource->getFullName(),
                'date_of_birth' => (string) $resource->getDateOfBirth(),
                'gender' => (string) $resource->getGender(),
                'phone_number' => $resource->getPhoneNumber(),
                'national_id' => $resource->getNationalId(),
                'photo_media_asset_id' => $resource->getPhotoMediaId(),
                'profile_completeness' => $completeness,
            ];
        }

        return [
            'id' => $resource->id,
            'user_id' => $resource->user_id,
            'country_id' => $resource->country_id,
            'full_name' => $resource->full_name,
            'date_of_birth' => $resource->date_of_birth?->format('Y-m-d'),
            'gender' => $resource->gender,
            'phone_number' => $resource->phone_number,
            'national_id' => $resource->national_id,
            'photo_media_asset_id' => $resource->photo_media_id,
        ];
    }
}
