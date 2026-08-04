<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class PublicSettingsController extends Controller
{
    public function languages(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['code' => 'ar', 'name' => 'العربية', 'direction' => 'rtl', 'is_default' => true],
                ['code' => 'en', 'name' => 'English', 'direction' => 'ltr', 'is_default' => false],
            ],
        ]);
    }

    public function settings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'platform_name' => 'Quran Competition Platform',
                'supported_locales' => ['ar', 'en'],
                'default_locale' => 'ar',
                'max_video_size_mb' => 500,
                'allowed_video_mimes' => ['video/mp4', 'video/quicktime'],
            ],
        ]);
    }
}
