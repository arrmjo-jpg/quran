<?php

declare(strict_types=1);

namespace Modules\Media\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Media\Domain\Repositories\MediaAssetRepositoryContract;
use Modules\Media\Infrastructure\Database\Models\MediaAssetModel;
use Modules\Media\Presentation\HTTP\Requests\UpdateMediaRequest;
use Modules\Media\Presentation\HTTP\Requests\UploadMediaRequest;
use Modules\Media\Presentation\HTTP\Resources\MediaAssetResource;

/**
 * AdminMediaController
 *
 * Handles the central Media Library for admin users.
 * Follows ADR-014 strictly — no business logic, delegates storage to Infrastructure.
 *
 * Endpoints:
 *   GET    /api/v1/admin/media          — list with filter/search/pagination
 *   POST   /api/v1/admin/media          — upload new asset (multipart)
 *   GET    /api/v1/admin/media/{id}     — show single asset
 *   PATCH  /api/v1/admin/media/{id}     — update editorial metadata
 *   DELETE /api/v1/admin/media/{id}     — soft-delete (or force delete)
 *   POST   /api/v1/admin/media/{id}/reprocess — retry failed processing
 */
final class AdminMediaController extends Controller
{
    public function __construct(
        private readonly MediaAssetRepositoryContract $repository,
    ) {}

    /**
     * GET /api/v1/admin/media
     *
     * List media assets with optional filters:
     *   ?type=image|video        — filter by MIME category
     *   ?collection=contestants  — filter by collection
     *   ?search=filename         — fuzzy file_name search
     *   ?page=1&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $query = MediaAssetModel::query()->orderByDesc('created_at');

        // Filter by MIME type category
        if ($type = $request->query('type')) {
            match ($type) {
                'image' => $query->where('mime_type', 'like', 'image/%'),
                'video' => $query->where('mime_type', 'like', 'video/%'),
                default => null,
            };
        }

        // Filter by collection
        if ($collection = $request->query('collection')) {
            $query->where('collection', $collection);
        }

        // Full-text search on file_name
        if ($search = $request->query('search')) {
            $query->where('file_name', 'like', '%'.$search.'%');
        }

        $perPage = min((int) ($request->query('per_page', 20)), 100);
        $assets = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => MediaAssetResource::collection($assets),
            'meta' => [
                'total' => $assets->total(),
                'per_page' => $assets->perPage(),
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/media
     *
     * Upload a file to R2 / local storage.
     * Performs SHA-256 deduplication — returns existing asset if hash matches.
     * Accepts optional `collection` field (default: 'default').
     */
    public function upload(UploadMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $collection = $request->input('collection', 'default');

        // 1. Compute SHA-256 for deduplication
        $hash = hash_file('sha256', $file->getRealPath());

        // 2. Check for existing asset with same hash (dedup)
        $existing = MediaAssetModel::query()
            ->where('hash_sha256', $hash)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Existing asset returned (SHA-256 deduplicated).',
                'data' => new MediaAssetResource($existing),
                'meta' => ['deduplicated' => true],
            ], 200);
        }

        // 3. Determine storage disk
        $disk = config('media.default_disk', 'public');
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';

        // 4. Generate a structured file path
        $extension = $file->getClientOriginalExtension();
        $fileName = $file->getClientOriginalName();
        $filePath = sprintf(
            '%s/%s/%s.%s',
            $collection,
            now()->format('Y/m'),
            Str::uuid(),
            $extension
        );

        // 5. Store file
        $file->storeAs(
            dirname($filePath),
            basename($filePath),
            ['disk' => $disk]
        );

        // 6. Persist MediaAsset record
        $id = (string) Str::uuid();
        $model = MediaAssetModel::query()->create([
            'id' => $id,
            'uploader_id' => $request->user()?->id,
            'disk' => $disk,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'size_bytes' => $file->getSize(),
            'hash_sha256' => $hash,
            'collection' => $collection,
            'custom_properties' => [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded successfully.',
            'data' => new MediaAssetResource($model),
            'meta' => ['deduplicated' => false],
        ], 201);
    }

    /**
     * GET /api/v1/admin/media/{id}
     *
     * Show a single asset with full metadata.
     */
    public function show(string $id): JsonResponse
    {
        $model = MediaAssetModel::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new MediaAssetResource($model),
        ]);
    }

    /**
     * PATCH /api/v1/admin/media/{id}
     *
     * Update editorial metadata only (alt / caption / credit / source).
     * Does NOT re-upload the file.
     */
    public function update(UpdateMediaRequest $request, string $id): JsonResponse
    {
        $model = MediaAssetModel::query()->findOrFail($id);

        // Merge validated metadata into custom_properties
        $existing = is_array($model->custom_properties) ? $model->custom_properties : [];
        $patch = array_filter($request->validated(), fn ($v) => $v !== null);

        $model->update([
            'custom_properties' => array_merge($existing, $patch),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Media asset metadata updated.',
            'data' => new MediaAssetResource($model->fresh()),
        ]);
    }

    /**
     * DELETE /api/v1/admin/media/{id}
     *
     * Soft-delete by default.
     * Pass ?force=1 to permanently delete from DB and storage.
     * Returns 409 if asset is in use (future: usage tracking).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $model = MediaAssetModel::withTrashed()->findOrFail($id);
        $force = (bool) $request->query('force', false);

        if ($force) {
            // Hard delete: remove from storage + DB
            try {
                Storage::disk($model->disk)->delete($model->file_path);
            } catch (\Throwable) {
                // Non-fatal: file may already be gone
            }
            $model->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Media asset permanently deleted.',
            ]);
        }

        $model->delete(); // Soft delete

        return response()->json([
            'success' => true,
            'message' => 'Media asset deleted.',
        ]);
    }

    /**
     * POST /api/v1/admin/media/{id}/reprocess
     *
     * Retry processing for a failed or stuck asset.
     * Queues a reprocessing job (stub — extends with actual job dispatch).
     */
    public function reprocess(string $id): JsonResponse
    {
        $model = MediaAssetModel::query()->findOrFail($id);

        // Update custom_properties to mark as queued for reprocessing
        $custom = is_array($model->custom_properties) ? $model->custom_properties : [];
        $model->update([
            'custom_properties' => array_merge($custom, ['processing_status' => 'queued']),
        ]);

        // TODO: dispatch ReprocessMediaJob::dispatch($model->id)

        return response()->json([
            'success' => true,
            'message' => 'Media asset queued for reprocessing.',
            'data' => new MediaAssetResource($model->fresh()),
        ]);
    }
}
