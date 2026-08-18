<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A season is a permanent historical record — never soft-deleted, only
 * archived (status='archived' + archived_at/archived_by_user_id/
 * archive_reason). See the Season Architecture v2 design spec.
 *
 * @property string $id
 * @property string $slug
 * @property int $year
 * @property Carbon $registration_start
 * @property Carbon $registration_end
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $status
 * @property bool $is_active
 * @property int|null $min_age
 * @property int|null $max_age
 * @property string|null $participation_type_id
 * @property string|null $tajweed_level_id
 * @property Carbon|null $frozen_at
 * @property Carbon|null $archived_at
 * @property string|null $archived_by_user_id
 * @property string|null $archive_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SeasonTranslationModel> $translations
 * @property-read int|null $translations_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeasonModel whereCreatedAt($value)
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
 *
 * @mixin \Eloquent
 */
final class SeasonModel extends Model
{
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
        'min_age',
        'max_age',
        'participation_type_id',
        'tajweed_level_id',
        'frozen_at',
        'archived_at',
        'archived_by_user_id',
        'archive_reason',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'registration_start' => 'datetime',
        'registration_end' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'min_age' => 'integer',
        'max_age' => 'integer',
        'frozen_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(SeasonTranslationModel::class, 'season_id', 'id');
    }
}
