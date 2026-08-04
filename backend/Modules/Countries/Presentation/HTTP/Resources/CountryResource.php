<?php

declare(strict_types=1);

namespace Modules\Countries\Presentation\HTTP\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Countries\Domain\Entities\Country;

final class CountryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locale = strtolower($request->header('Accept-Language', 'ar'));
        if (str_contains($locale, 'en')) {
            $locale = 'en';
        } else {
            $locale = 'ar';
        }

        /** @var Country|object $resource */
        $resource = $this->resource;

        if ($resource instanceof Country) {
            return [
                'id' => $resource->id->value,
                'iso2' => (string) $resource->getIso2(),
                'iso3' => (string) $resource->getIso3(),
                'phone_code' => (string) $resource->getPhoneCode(),
                'flag_url' => $resource->getFlagUrl(),
                'name' => $resource->getName($locale),
                'is_active' => $resource->isActive(),
            ];
        }

        return [
            'id' => $resource->id,
            'iso2' => $resource->iso_code,
            'iso3' => $resource->iso3_code,
            'phone_code' => $resource->phone_code,
            'flag_url' => $resource->flag_url,
            'name' => $resource->translations->where('locale', $locale)->first()?->name ?? $resource->iso_code,
            'is_active' => (bool) $resource->is_active,
        ];
    }
}
