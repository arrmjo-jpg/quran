<?php

declare(strict_types=1);

namespace Modules\Judges\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $full_name
 * @property string|null $title
 * @property string $specialization
 * @property string|null $bio
 * @property string|null $photo_media_id
 * @property bool $is_active
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel wherePhotoMediaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel whereSpecialization($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JudgeModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class JudgeModel extends Model
{
    use SoftDeletes;

    protected $table = 'judges';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'full_name',
        'title',
        'specialization',
        'bio',
        'photo_media_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
