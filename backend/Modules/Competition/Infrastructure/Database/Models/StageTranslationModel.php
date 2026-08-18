<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $stage_id
 * @property string $locale
 * @property string $name
 * @property string|null $public_name
 * @property string|null $description
 *
 * @mixin \Eloquent
 */
final class StageTranslationModel extends Model
{
    protected $table = 'stage_translations';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'stage_id',
        'locale',
        'name',
        'public_name',
        'description',
    ];
}
