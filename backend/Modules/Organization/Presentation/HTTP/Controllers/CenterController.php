<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\HTTP\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Modules\Organization\Application\UseCases\CreateCenterUseCase;
use Modules\Organization\Application\UseCases\DeleteCenterUseCase;
use Modules\Organization\Application\UseCases\UpdateCenterUseCase;
use Modules\Organization\Infrastructure\Database\Models\CenterModel;
use Modules\Organization\Presentation\HTTP\Requests\SaveCenterRequest;
use Modules\Organization\Presentation\HTTP\Resources\CenterResource;

/**
 * Centre administration — ADR-016 D7.
 *
 * Every write goes through a use case; this translates domain refusals into
 * status codes and adds no rule of its own.
 */
final class CenterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CenterModel::query();

        if ($countryId = $request->query('country_id')) {
            $query->where('country_id', $countryId);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('name')->paginate(min((int) $request->query('per_page', 20), 100));

        return response()->json([
            'success' => true,
            'data' => CenterResource::collection($paginator->items()),
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CenterResource(CenterModel::query()->findOrFail($id)),
        ]);
    }

    public function store(SaveCenterRequest $request, CreateCenterUseCase $createCenter): JsonResponse
    {
        try {
            $center = $createCenter->execute(
                name: $request->validated('name'),
                countryId: $request->validated('country_id'),
                city: $request->validated('city'),
                address: $request->validated('address'),
                latitude: $request->validated('latitude'),
                longitude: $request->validated('longitude'),
            );
        } catch (DomainException $e) {
            return $this->refusal('CENTER_NAME_TAKEN', $e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->refusal('INVALID_CENTER', $e->getMessage(), 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Centre created.'),
            'data' => new CenterResource(CenterModel::query()->findOrFail($center->id->value)),
        ], 201);
    }

    public function update(SaveCenterRequest $request, string $id, UpdateCenterUseCase $updateCenter): JsonResponse
    {
        try {
            $updateCenter->execute(
                centerId: $id,
                name: $request->validated('name'),
                city: $request->validated('city'),
                address: $request->validated('address'),
                latitude: $request->validated('latitude'),
                longitude: $request->validated('longitude'),
            );
        } catch (DomainException $e) {
            return $this->refusal('CENTER_NAME_TAKEN', $e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->refusal('INVALID_CENTER', $e->getMessage(), 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Centre updated.'),
            'data' => new CenterResource(CenterModel::query()->findOrFail($id)),
        ]);
    }

    public function destroy(string $id, DeleteCenterUseCase $deleteCenter): JsonResponse
    {
        try {
            $deleteCenter->execute($id);
        } catch (DomainException $e) {
            // 409 rather than 422: the request is well formed and the centre
            // is real. What refuses it is the state of the world — circles
            // still hang off it — which is a conflict, not a bad field.
            return $this->refusal('CENTER_HAS_CIRCLES', $e->getMessage(), 409);
        }

        return response()->json([
            'success' => true,
            'message' => __('Centre deleted.'),
        ]);
    }

    private function refusal(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
