<?php

declare(strict_types=1);

namespace Modules\Sponsors\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Sponsors\Infrastructure\Database\Models\SponsorModel;
use Modules\Sponsors\Presentation\HTTP\Resources\SponsorResource;

final class PublicSponsorController extends Controller
{
    public function index(): JsonResponse
    {
        $sponsors = SponsorModel::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => SponsorResource::collection($sponsors),
        ]);
    }
}
