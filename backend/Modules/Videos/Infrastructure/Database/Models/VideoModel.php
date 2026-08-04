<?php

declare(strict_types=1);

namespace Modules\Videos\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $application_id
 * @property string|null $raw_media_asset_id
 * @property string|null $media_asset_id
 * @property int|null $duration_seconds
 * @property string|null $resolution
 * @property string|null $format
 * @property string $status
 * @property string|null $hls_master_playlist_path
 * @property string|null $thumbnail_path
 * @property array<array-key, mixed>|null $variants
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereApplicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereDurationSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereHlsMasterPlaylistPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereMediaAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereRawMediaAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereResolution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereThumbnailPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel whereVariants($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class VideoModel extends Model
{
    use SoftDeletes;

    protected $table = 'videos';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'application_id',
        'raw_media_asset_id',
        'status',
        'duration_seconds',
        'hls_master_playlist_path',
        'thumbnail_path',
        'variants',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'variants' => 'array',
    ];
}
