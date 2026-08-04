<?php

declare(strict_types=1);

namespace Modules\Search\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $index_name
 * @property string $action
 * @property string|null $entity_id
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndexingLogModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndexingLogModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndexingLogModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndexingLogModel whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndexingLogModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndexingLogModel whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndexingLogModel whereError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndexingLogModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndexingLogModel whereIndexName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndexingLogModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|IndexingLogModel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class IndexingLogModel extends Model
{
    protected $table = 'indexing_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'index_name',
        'action',
        'entity_id',
        'status',
        'error',
    ];
}
