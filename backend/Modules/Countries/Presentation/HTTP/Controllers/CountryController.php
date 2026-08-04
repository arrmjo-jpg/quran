<?php

declare(strict_types=1);

namespace Modules\Countries\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Countries\Domain\Entities\Country;
use Modules\Countries\Domain\Repositories\CountryRepositoryContract;
use Modules\Countries\Domain\ValueObjects\CountryId;
use Modules\Countries\Domain\ValueObjects\CountryIso2;
use Modules\Countries\Domain\ValueObjects\CountryIso3;
use Modules\Countries\Domain\ValueObjects\PhoneCode;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;
use Modules\Countries\Presentation\HTTP\Requests\CreateCountryRequest;
use Modules\Countries\Presentation\HTTP\Requests\ListCountriesRequest;
use Modules\Countries\Presentation\HTTP\Resources\CountryResource;

final class CountryController extends Controller
{
    public function __construct(
        private readonly CountryRepositoryContract $repository,
    ) {}

    public function index(ListCountriesRequest $request): JsonResponse
    {
        $query = CountryModel::query()->with('translations');

        if ($request->boolean('active', true)) {
            $query->where('is_active', true);
        }

        if ($search = $request->validated('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('iso_code', 'like', "%{$search}%")
                    ->orWhere('iso3_code', 'like', "%{$search}%")
                    ->orWhereHas('translations', fn ($t) => $t->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = (int) $request->validated('per_page', 20);
        $paginator = $query->paginate($perPage);

        $etag = md5(json_encode($paginator->pluck('id')));
        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304, ['ETag' => $etag]);
        }

        return response()->json([
            'success' => true,
            'message' => __('Countries list retrieved.'),
            'data' => CountryResource::collection($paginator->items()),
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ], 200, ['ETag' => $etag]);
    }

    public function show(string $id): JsonResponse
    {
        $country = $this->repository->findOrFail(new CountryId($id));

        return response()->json([
            'success' => true,
            'data' => new CountryResource($country),
        ]);
    }

    public function store(CreateCountryRequest $request): JsonResponse
    {
        $country = Country::create(
            id: CountryId::generate(),
            iso2: new CountryIso2($request->validated('iso2')),
            iso3: new CountryIso3($request->validated('iso3')),
            phoneCode: new PhoneCode($request->validated('phone_code')),
            flagUrl: $request->validated('flag_url'),
            translations: [
                'ar' => $request->validated('name_ar'),
                'en' => $request->validated('name_en'),
            ]
        );

        $this->repository->save($country);

        return response()->json([
            'success' => true,
            'message' => __('Country created successfully.'),
            'data' => new CountryResource($country),
        ], 201);
    }

    public function activate(string $id): JsonResponse
    {
        $country = $this->repository->findOrFail(new CountryId($id));
        $country->activate();
        $this->repository->save($country);

        return response()->json([
            'success' => true,
            'message' => __('Country activated successfully.'),
            'data' => new CountryResource($country),
        ]);
    }

    public function deactivate(string $id): JsonResponse
    {
        $country = $this->repository->findOrFail(new CountryId($id));
        $country->deactivate();
        $this->repository->save($country);

        return response()->json([
            'success' => true,
            'message' => __('Country deactivated successfully per ADR-005.'),
            'data' => new CountryResource($country),
        ]);
    }
}
