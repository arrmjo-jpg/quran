<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\HTTP\Controllers;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Modules\Organization\Application\UseCases\CreateCircleUseCase;
use Modules\Organization\Application\UseCases\DeleteCircleUseCase;
use Modules\Organization\Application\UseCases\UpdateCircleUseCase;
use Modules\Organization\Infrastructure\Database\Models\CircleModel;
use Modules\Organization\Presentation\HTTP\Requests\SaveCircleRequest;
use Modules\Organization\Presentation\HTTP\Resources\CircleResource;

/**
 * Circle administration — ADR-016 D6.
 *
 * Every write goes through a use case; this translates domain refusals into
 * status codes and adds no rule of its own.
 */
final class CircleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Eager loaded because CircleResource renders the centre inline: a
        // circle has no location of its own, so a list without it is a list of
        // names nobody can place.
        $query = CircleModel::query()->with('center');

        if ($centerId = $request->query('center_id')) {
            $query->where('center_id', $centerId);
        }

        if ($supervisorId = $request->query('supervisor_user_id')) {
            $query->where('supervisor_user_id', $supervisorId);
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $paginator = $query->orderBy('name')->paginate(min((int) $request->query('per_page', 20), 100));

        return response()->json([
            'success' => true,
            'data' => CircleResource::collection($paginator->items()),
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
            'data' => new CircleResource(CircleModel::query()->with('center')->findOrFail($id)),
        ]);
    }

    public function store(SaveCircleRequest $request, CreateCircleUseCase $createCircle): JsonResponse
    {
        try {
            $circle = $createCircle->execute(
                name: $request->validated('name'),
                centerId: $request->validated('center_id'),
                supervisorUserId: $request->validated('supervisor_user_id'),
            );
        } catch (DomainException $e) {
            return $this->refusal('CIRCLE_NAME_TAKEN', $e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->refusal('INVALID_CIRCLE', $e->getMessage(), 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Circle created.'),
            'data' => new CircleResource(CircleModel::query()->with('center')->findOrFail($circle->id->value)),
        ], 201);
    }

    public function update(SaveCircleRequest $request, string $id, UpdateCircleUseCase $updateCircle): JsonResponse
    {
        try {
            $updateCircle->execute(
                circleId: $id,
                name: $request->validated('name'),
                supervisorUserId: $request->validated('supervisor_user_id'),
            );
        } catch (DomainException $e) {
            return $this->refusal('CIRCLE_NAME_TAKEN', $e->getMessage(), 409);
        } catch (InvalidArgumentException $e) {
            return $this->refusal('INVALID_CIRCLE', $e->getMessage(), 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Circle updated.'),
            'data' => new CircleResource(CircleModel::query()->with('center')->findOrFail($id)),
        ]);
    }

    public function destroy(string $id, DeleteCircleUseCase $deleteCircle): JsonResponse
    {
        $deleteCircle->execute($id);

        return response()->json([
            'success' => true,
            'message' => __('Circle deleted.'),
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
