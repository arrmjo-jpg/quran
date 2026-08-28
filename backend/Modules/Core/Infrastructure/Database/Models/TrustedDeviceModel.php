<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for the trusted_devices table — ADR-018 D1.
 *
 * NO SoftDeletes. Revoking trust must actually remove the grant: a soft-deleted
 * credential that a careless query could still match is worse than no
 * revocation at all, and there is no history to preserve here — audit_logs
 * already records that the revoke call happened.
 *
 * `token_hash` is never exposed. The plaintext exists only in the response to
 * the request that created it (D5), so it is not in $fillable's way here but is
 * deliberately absent from every resource.
 *
 * @property string $id
 * @property string $user_id
 * @property string $token_hash
 * @property string|null $device_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $trusted_at
 * @property Carbon $expires_at
 * @property Carbon|null $last_used_at
 */
final class TrustedDeviceModel extends Model
{
    protected $table = 'trusted_devices';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'token_hash',
        'device_id',
        'ip',
        'user_agent',
        'trusted_at',
        'expires_at',
        'last_used_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'trusted_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Hidden as well as unexposed by the resource. Two independent guards,
     * because this is the one field whose leak would hand over the credential.
     *
     * @var array<int, string>
     */
    protected $hidden = ['token_hash'];
}
