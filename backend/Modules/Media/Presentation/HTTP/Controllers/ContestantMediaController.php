<?php

declare(strict_types=1);

namespace Modules\Media\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Media\Infrastructure\Database\Models\MediaAssetModel;
use Modules\Media\Presentation\HTTP\Requests\UploadMediaRequest;
use Modules\Media\Presentation\HTTP\Resources\MediaAssetResource;

/**
 * ContestantMediaController
 *
 * Handles media uploads from authenticated Contestants.
 * Contestants may only:
 *   POST /api/v1/contestant/media/upload — upload their own video/photo
 *   GET  /api/v1/contestant/media/{id}  — view their own uploaded asset
 *
 * Contestants CANNOT list all assets, delete, or access other contestants' media.
 */
final class ContestantMediaController extends Controller
{
    /**
     * POST /api/v1/contestant/media/upload
     *
     * Contestant uploads a file (video or image) to the platform.
     * Stored in the 'contestants' collection with uploader_id set.
     */
    public function upload(UploadMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $disk = config('media.default_disk', 'public');

        // SHA-256 deduplication
        $hash = hash_file('sha256', $file->getRealPath());
        $existing = MediaAssetModel::query()
            ->where('hash_sha256', $hash)
            ->where('uploader_id', $request->user()->id)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'File already uploaded.',
                'data' => new MediaAssetResource($existing),
                'meta' => ['deduplicated' => true],
            ]);
        }

        $extension = $file->getClientOriginalExtension();
        $fileName = $file->getClientOriginalName();
        $filePath = sprintf(
            'contestants/%s/%s/%s.%s',
            $request->user()->id,
            now()->format('Y/m'),
            Str::uuid(),
            $extension
        );

        $file->storeAs(dirname($filePath), basename($filePath), ['disk' => $disk]);

        $model = MediaAssetModel::query()->create([
            'id' => (string) Str::uuid(),
            'uploader_id' => $request->user()->id,
            'disk' => $disk,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'hash_sha256' => $hash,
            'collection' => 'contestants',
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
     * GET /api/v1/contestant/media/{id}
     *
     * Contestant can only view their own asset.
     * Returns 403 if the asset belongs to another user.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $model = MediaAssetModel::query()->findOrFail($id);

        if ($model->uploader_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Access denied.'],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new MediaAssetResource($model),
        ]);
    }
}
