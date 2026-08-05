<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property int $display_order
 * @property bool $is_active
 * @property-read Collection<int, TajweedLevelTranslationModel> $translations
 *
 * @mixin \Eloquent
 */
final class TajweedLevelModel extends Model
{
    protected $table = 'tajweed_levels';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(TajweedLevelTranslationModel::class, 'tajweed_level_id', 'id');
    }
}
