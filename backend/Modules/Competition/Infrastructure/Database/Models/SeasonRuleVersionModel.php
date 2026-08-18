<?php

declare(strict_types=1);

namespace Modules\Competition\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable rule-version snapshot row — never updated after creation.
 *
 * @property string $id
 * @property string $season_id
 * @property int $version
 * @property array<string, mixed> $snapshot_json
 * @property string|null $created_by_user_id
 *
 * @mixin \Eloquent
 */
final class SeasonRuleVersionModel extends Model
{
    protected $table = 'season_rule_versions';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'season_id',
        'version',
        'snapshot_json',
        'created_by_user_id',
    ];

    protected $casts = [
        'version' => 'integer',
        'snapshot_json' => 'array',
    ];
}
