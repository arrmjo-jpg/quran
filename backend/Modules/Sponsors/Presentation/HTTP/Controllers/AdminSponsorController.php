<?php

declare(strict_types=1);

namespace Modules\Sponsors\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Sponsors\Infrastructure\Database\Models\SponsorModel;
use Modules\Sponsors\Presentation\HTTP\Resources\SponsorResource;

final class AdminSponsorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SponsorModel::query()->orderBy('display_order')->orderBy('name');

        if ($tier = $request->query('tier')) {
            $query->where('tier', $tier);
        }

        $sponsors = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => SponsorResource::collection($sponsors),
            'meta' => [
                'total' => $sponsors->total(),
                'per_page' => $sponsors->perPage(),
                'current_page' => $sponsors->currentPage(),
                'last_page' => $sponsors->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'string', 'in:headline,gold,silver,partner'],
            'logo_media_id' => ['nullable', 'string', 'uuid'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $sponsor = SponsorModel::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $validated['name'],
            'tier' => $validated['tier'],
            'logo_media_id' => $validated['logo_media_id'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
            'display_order' => $validated['display_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sponsor created successfully.',
            'data' => new SponsorResource($sponsor),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $sponsor = SponsorModel::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new SponsorResource($sponsor),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $sponsor = SponsorModel::query()->findOrFail($id);
        $sponsor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sponsor deleted.',
        ]);
    }
}
