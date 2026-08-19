<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent model for the invitations table.
 *
 * No SoftDeletes: an invitation is a fact about a moment, and the only
 * lifecycle it has is "open" then "accepted". Withdrawing one is Epic 12's
 * concern and will need its own column rather than a hidden deleted_at, so
 * that "revoked" and "never existed" stay distinguishable.
 *
 * @property string $id
 * @property string $user_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property string|null $invited_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class InvitationModel extends Model
{
    protected $table = 'invitations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'token_hash',
        'expires_at',
        'accepted_at',
        'invited_by_user_id',
    ];

    /**
     * token_hash is deliberately hidden. It is not the secret — the plaintext
     * is, and that is never stored — but a digest in a serialised response is
     * still an offline target nobody needs to be handed.
     */
    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];
}
