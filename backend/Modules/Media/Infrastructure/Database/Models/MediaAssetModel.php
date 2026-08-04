<?php

declare(strict_types=1);

namespace Modules\Media\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $uploader_id
 * @property string $disk
 * @property string $file_path
 * @property string $file_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $hash_sha256
 * @property string $collection
 * @property array<array-key, mixed>|null $custom_properties
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereCollection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereCustomProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereHashSha256($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel whereUploaderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAssetModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class MediaAssetModel extends Model
{
    use SoftDeletes;

    protected $table = 'media_assets';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'uploader_id',
        'disk',
        'file_path',
        'file_name',
        'mime_type',
        'size_bytes',
        'hash_sha256',
        'collection',
        'custom_properties',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'custom_properties' => 'array',
    ];
}
