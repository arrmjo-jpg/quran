<?php

declare(strict_types=1);

namespace Modules\Content\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $display_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FaqModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FaqModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FaqModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FaqModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FaqModel whereDisplayOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FaqModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FaqModel whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FaqModel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class FaqModel extends Model
{
    protected $table = 'faqs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
