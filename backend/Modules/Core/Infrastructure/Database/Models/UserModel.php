<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * UserModel
 *
 * Eloquent authenticatable model for the users table.
 *
 * @property string $id
 * @property string $email
 * @property string $name
 * @property string $type
 * @property string|null $password_hash
 * @property string $preferred_locale
 * @property bool $is_active
 * @property bool $mfa_enabled
 * @property string|null $mfa_secret
 * @property array<array-key, mixed>|null $mfa_recovery_codes
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel whereMfaEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel whereMfaRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel whereMfaSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel wherePasswordHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel wherePreferredLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class UserModel extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $table = 'users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'name',
        'type',
        'password_hash',
        'is_active',
        'preferred_locale',
        'mfa_enabled',
        'mfa_secret',
        'mfa_recovery_codes',
    ];

    protected $hidden = [
        'password_hash',
        'mfa_secret',
        'mfa_recovery_codes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'mfa_enabled' => 'boolean',
        'mfa_recovery_codes' => 'array',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }
}
