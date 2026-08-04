<?php

declare(strict_types=1);

namespace Modules\Content\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $slug
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaticPageModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaticPageModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaticPageModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaticPageModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaticPageModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaticPageModel whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaticPageModel whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaticPageModel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class StaticPageModel extends Model
{
    protected $table = 'static_pages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
