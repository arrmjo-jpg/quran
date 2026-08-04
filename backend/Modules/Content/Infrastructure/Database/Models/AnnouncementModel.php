<?php

declare(strict_types=1);

namespace Modules\Content\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $slug
 * @property string $target_surface
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel whereIsPublished($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel whereTargetSurface($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class AnnouncementModel extends Model
{
    use SoftDeletes;

    protected $table = 'announcements';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'slug',
        'target_surface',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];
}
