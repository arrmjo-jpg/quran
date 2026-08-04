<?php

declare(strict_types=1);

namespace Modules\Reports\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string|null $media_asset_id
 * @property string $status
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportModel whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportModel whereMediaAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportModel whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExportModel whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class ExportModel extends Model
{
    protected $table = 'exports';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'type',
        'media_asset_id',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
