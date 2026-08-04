<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Content\Infrastructure\Database\Models\AnnouncementModel;
use Modules\Content\Presentation\HTTP\Resources\AnnouncementResource;

/**
 * AdminContentController
 *
 * Administrative management of announcements, static pages, and FAQs.
 */
final class AdminContentController extends Controller
{
    // ── ANNOUNCEMENTS ────────────────────────────────────────────────────────

    public function indexAnnouncements(Request $request): JsonResponse
    {
        $query = AnnouncementModel::query()->orderByDesc('created_at');

        if ($target = $request->query('target_surface')) {
            $query->where('target_surface', $target);
        }

        $announcements = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => AnnouncementResource::collection($announcements),
            'meta' => [
                'total' => $announcements->total(),
                'per_page' => $announcements->perPage(),
                'current_page' => $announcements->currentPage(),
                'last_page' => $announcements->lastPage(),
            ],
        ]);
    }

    public function storeAnnouncement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'unique:announcements,slug'],
            'target_surface' => ['required', 'string', 'in:all,contestants,public'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $isPublished = (bool) ($validated['is_published'] ?? false);

        $announcement = AnnouncementModel::query()->create([
            'id' => (string) Str::uuid(),
            'slug' => $validated['slug'],
            'target_surface' => $validated['target_surface'],
            'is_published' => $isPublished,
            'published_at' => $isPublished ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Announcement created.',
            'data' => new AnnouncementResource($announcement),
        ], 201);
    }

    public function toggleAnnouncementPublish(string $id): JsonResponse
    {
        $announcement = AnnouncementModel::query()->findOrFail($id);

        $newPublished = ! $announcement->is_published;
        $announcement->update([
            'is_published' => $newPublished,
            'published_at' => $newPublished ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $newPublished ? 'Announcement published.' : 'Announcement unpublished.',
            'data' => new AnnouncementResource($announcement->fresh()),
        ]);
    }

    public function destroyAnnouncement(string $id): JsonResponse
    {
        $announcement = AnnouncementModel::query()->findOrFail($id);
        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Announcement deleted.',
        ]);
    }
}
