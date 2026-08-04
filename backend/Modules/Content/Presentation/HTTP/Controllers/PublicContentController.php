<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Content\Infrastructure\Database\Models\AnnouncementModel;
use Modules\Content\Presentation\HTTP\Resources\AnnouncementResource;

/**
 * PublicContentController
 *
 * Public access to published announcements, static pages, and FAQs.
 */
final class PublicContentController extends Controller
{
    public function announcements(Request $request): JsonResponse
    {
        $target = $request->query('target', 'all');

        $announcements = AnnouncementModel::query()
            ->where('is_published', true)
            ->whereIn('target_surface', ['all', $target])
            ->orderByDesc('published_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => AnnouncementResource::collection($announcements),
        ]);
    }
}
