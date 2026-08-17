<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_system
 *
 * @mixin \Eloquent
 */
final class RoleModel extends Model
{
    protected $table = 'roles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'guard_name',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];
}
