<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for the circles table.
 *
 * @property string $id
 * @property string $name
 * @property string $center_id
 * @property string|null $supervisor_user_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CenterModel|null $center
 */
final class CircleModel extends Model
{
    use SoftDeletes;

    protected $table = 'circles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'center_id',
        'supervisor_user_id',
    ];

    /**
     * The centre a circle reads its location through (Q3).
     *
     * Declared here rather than resolved by a second query in the resource,
     * so that listing circles with their centres is one eager load instead of
     * one query per row.
     */
    public function center(): BelongsTo
    {
        return $this->belongsTo(CenterModel::class, 'center_id');
    }
}
