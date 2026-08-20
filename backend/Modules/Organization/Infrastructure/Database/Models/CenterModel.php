<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for the centers table.
 *
 * @property string $id
 * @property string $name
 * @property string $country_id
 * @property string $city
 * @property string $address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class CenterModel extends Model
{
    use SoftDeletes;

    protected $table = 'centers';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'country_id',
        'city',
        'address',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        // Cast to float, not string. Eloquent hands decimal columns back as
        // strings by default, and a Coordinates value object built from
        // "31.9539" would fail its own float type declaration.
        'latitude' => 'float',
        'longitude' => 'float',
    ];
}
