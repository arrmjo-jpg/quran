<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for the contestant_memberships table.
 *
 * NO SoftDeletes trait, matching the table: a membership ends by acquiring
 * `left_at`, and there is no second way to make one go away. Adding the trait
 * later would put two conditions behind the word "active" and quietly
 * invalidate the unique index, which knows only about `left_at`.
 *
 * @property string $id
 * @property string $contestant_id
 * @property string $circle_id
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CircleModel|null $circle
 */
final class ContestantMembershipModel extends Model
{
    protected $table = 'contestant_memberships';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contestant_id',
        'circle_id',
        'joined_at',
        'left_at',
        'reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    /**
     * The circle attended. Declared so a roster or a contestant's history can
     * be listed in one eager load rather than a query per row.
     */
    public function circle(): BelongsTo
    {
        return $this->belongsTo(CircleModel::class, 'circle_id');
    }
}
