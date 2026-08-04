<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $slug
 * @property int $year
 * @property Carbon $registration_start
 * @property Carbon $registration_end
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $status
 * @property bool $is_active
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SeasonTranslationModel> $translations
 * @property-read int|null $translations_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereRegistrationEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereRegistrationStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class SeasonModel extends Model
{
    use SoftDeletes;

    protected $table = 'seasons';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'slug',
        'year',
        'registration_start',
        'registration_end',
        'start_date',
        'end_date',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'registration_start' => 'datetime',
        'registration_end' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(SeasonTranslationModel::class, 'season_id', 'id');
    }
}
