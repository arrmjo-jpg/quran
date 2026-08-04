<?php

declare(strict_types=1);

namespace Modules\Contestants\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $country_id
 * @property string $full_name
 * @property Carbon $date_of_birth
 * @property string $gender
 * @property string|null $national_id
 * @property string $phone_number
 * @property string|null $photo_media_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel whereFullName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel whereNationalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel wherePhoneNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel wherePhotoMediaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContestantModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class ContestantModel extends Model
{
    use SoftDeletes;

    protected $table = 'contestants';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'country_id',
        'full_name',
        'date_of_birth',
        'gender',
        'national_id',
        'phone_number',
        'photo_media_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
    ];
}
