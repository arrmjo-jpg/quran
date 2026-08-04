<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Security;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Infrastructure\Database\Models\UserModel;

final class DeviceTrustService
{
    private const TRUST_TTL_DAYS = 30;

    public function generateFingerprint(string $ip, string $userAgent, ?string $deviceId = null): string
    {
        $raw = sprintf('%s|%s|%s', $ip, $userAgent, $deviceId ?? 'unknown');

        return hash('sha256', $raw);
    }

    public function trustDevice(UserModel $user, string $ip, string $userAgent, ?string $deviceId = null): array
    {
        $fingerprint = $this->generateFingerprint($ip, $userAgent, $deviceId);
        $trustToken = strtoupper(bin2hex(random_bytes(16)));
        $cacheKey = "device_trust:{$user->id}:{$fingerprint}";

        $deviceData = [
            'id' => $fingerprint,
            'user_id' => $user->id,
            'trust_token' => $trustToken,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'trusted_at' => now()->toISOString(),
            'expires_at' => now()->addDays(self::TRUST_TTL_DAYS)->toISOString(),
        ];

        Cache::put($cacheKey, $deviceData, now()->addDays(self::TRUST_TTL_DAYS));

        // Track active trusted fingerprints for user
        $userKey = "user_devices:{$user->id}";
        $userDevices = Cache::get($userKey, []);
        $userDevices[$fingerprint] = $deviceData;
        Cache::put($userKey, $userDevices, now()->addDays(self::TRUST_TTL_DAYS));

        return $deviceData;
    }

    public function listDevices(UserModel $user): array
    {
        $userKey = "user_devices:{$user->id}";
        $devices = Cache::get($userKey, []);

        return array_values($devices);
    }

    public function revokeDevice(UserModel $user, string $fingerprint): void
    {
        $cacheKey = "device_trust:{$user->id}:{$fingerprint}";
        Cache::forget($cacheKey);

        $userKey = "user_devices:{$user->id}";
        $userDevices = Cache::get($userKey, []);
        unset($userDevices[$fingerprint]);
        Cache::put($userKey, $userDevices, now()->addDays(self::TRUST_TTL_DAYS));
    }
}
