<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Security;

use Illuminate\Support\Carbon;
use Modules\Core\Infrastructure\Database\Models\TrustedDeviceModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Symfony\Component\Uid\Uuid;

/**
 * Trusted devices — ADR-018 D1, D5, D6.
 *
 * WHAT CHANGED AND WHY. This was a Cache-backed store whose grants did nothing:
 * a trust_token was minted, written to Cache and returned, and no code path
 * ever verified it. D5 gives it a purpose — a trusted device skips the MFA
 * challenge for 30 days — which makes the token a credential, and credentials
 * do not live in a store that may be evicted, nor come back in every list
 * response.
 *
 * THE TOKEN IS THE CREDENTIAL (D6), not the fingerprint. The old
 * generateFingerprint() hashed `ip | user_agent | device_id` into the device's
 * identity, so trust broke whenever the address changed — on any mobile
 * network, constantly. That was invisible while trust did nothing; under D5 it
 * would have pushed users toward turning MFA off. `ip` and `user_agent` are
 * kept to answer "which device is this?" on screen and are consulted by
 * nothing.
 */
final class DeviceTrustService
{
    public const TRUST_TTL_DAYS = 30;

    /**
     * Returns the row plus the plaintext token, which is the ONLY time it is
     * ever available. Only its hash is stored.
     *
     * @return array<string, mixed>
     */
    public function trustDevice(UserModel $user, string $ip, string $userAgent, ?string $deviceId = null): array
    {
        $plaintext = strtoupper(bin2hex(random_bytes(16)));

        // Re-trusting the same browser rotates its grant rather than
        // accumulating a second one, so the list stays a list of devices and
        // not of button presses. Only possible when the client identifies
        // itself; without X-Device-ID there is nothing to match on.
        if ($deviceId !== null && $deviceId !== '') {
            TrustedDeviceModel::query()
                ->where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->delete();
        }

        $device = TrustedDeviceModel::query()->create([
            'id' => (string) Uuid::v7(),
            'user_id' => (string) $user->id,
            'token_hash' => $this->hash($plaintext),
            'device_id' => $deviceId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'trusted_at' => now(),
            'expires_at' => now()->addDays(self::TRUST_TTL_DAYS),
        ]);

        return $this->present($device) + ['trust_token' => $plaintext];
    }

    /**
     * Live grants only. An expired row is not a device the user is trusting,
     * and showing it would invite them to "revoke" something that already
     * stopped working.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDevices(UserModel $user): array
    {
        return TrustedDeviceModel::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->orderByDesc('trusted_at')
            ->get()
            ->map(fn (TrustedDeviceModel $d): array => $this->present($d))
            ->all();
    }

    /**
     * Scoped to the owner: an id alone must not revoke another account's
     * device. Returns whether anything was actually removed.
     */
    public function revokeDevice(UserModel $user, string $id): bool
    {
        return TrustedDeviceModel::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Drop every grant this account holds — ADR-018 D4.
     *
     * Called when MFA is turned off: the grants existed only to skip a
     * challenge that will no longer be issued, so leaving them would leave
     * credentials that buy nothing and outlive the decision that made them.
     *
     * Lives here rather than in the controller because a controller that
     * queries TrustedDeviceModel is a controller reaching past its own
     * application layer -- which the architecture guard fails, and did.
     */
    public function revokeAll(UserModel $user): int
    {
        return TrustedDeviceModel::query()->where('user_id', $user->id)->delete();
    }

    /**
     * The login path — ADR-018 D5.
     *
     * Verifies a plaintext token against this account's live grants. Compared
     * by hash lookup rather than by iterating and comparing strings, so the
     * database index does the work and no comparison happens in PHP over
     * attacker-supplied input.
     *
     * `last_used_at` is written, `expires_at` is NOT extended: trust runs on a
     * fixed 30-day horizon, which is the mitigation D5 relies on.
     */
    public function verifyTrust(UserModel $user, ?string $plaintext): bool
    {
        if ($plaintext === null || $plaintext === '') {
            return false;
        }

        $device = TrustedDeviceModel::query()
            ->where('user_id', $user->id)
            ->where('token_hash', $this->hash($plaintext))
            ->where('expires_at', '>', now())
            ->first();

        if ($device === null) {
            return false;
        }

        $device->forceFill(['last_used_at' => now()])->save();

        return true;
    }

    private function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * The public shape of a device. `token_hash` is absent by construction —
     * the resource cannot leak what it never receives.
     *
     * @return array<string, mixed>
     */
    private function present(TrustedDeviceModel $device): array
    {
        return [
            'id' => $device->id,
            'user_id' => $device->user_id,
            'device_id' => $device->device_id,
            'ip' => $device->ip,
            'user_agent' => $device->user_agent,
            'trusted_at' => $this->iso($device->trusted_at),
            'expires_at' => $this->iso($device->expires_at),
            'last_used_at' => $this->iso($device->last_used_at),
        ];
    }

    private function iso(?Carbon $at): ?string
    {
        return $at?->toIso8601String();
    }
}
