<?php

declare(strict_types=1);

namespace Modules\Evaluations\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $application_id
 * @property string $contestant_id
 * @property string $reason
 * @property string $status
 * @property string|null $admin_response
 * @property string|null $resolved_by_user_id
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel whereAdminResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel whereApplicationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel whereContestantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel whereResolvedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel whereResolvedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AppealModel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class AppealModel extends Model
{
    protected $table = 'appeals';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'application_id', 'contestant_id', 'reason', 'status', 'admin_response', 'resolved_by_user_id', 'resolved_at'];

    protected $casts = ['resolved_at' => 'datetime'];
}
