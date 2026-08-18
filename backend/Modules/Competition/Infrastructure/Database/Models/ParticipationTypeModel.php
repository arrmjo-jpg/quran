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
 * @property-read Collection<int, ParticipationTypeTranslationModel> $translations
 *
 * @mixin \Eloquent
 */
final class ParticipationTypeModel extends Model
{
    protected $table = 'participation_types';

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
        return $this->hasMany(ParticipationTypeTranslationModel::class, 'participation_type_id', 'id');
    }
}
