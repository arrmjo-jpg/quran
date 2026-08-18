<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $participation_type_id
 * @property string $locale
 * @property string $name
 *
 * @mixin \Eloquent
 */
final class ParticipationTypeTranslationModel extends Model
{
    protected $table = 'participation_type_translations';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'participation_type_id',
        'locale',
        'name',
    ];
}
