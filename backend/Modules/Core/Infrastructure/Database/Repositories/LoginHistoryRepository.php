<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Database\Repositories;

use Modules\Core\Domain\ReadModels\LoginAttempt;
use Modules\Core\Domain\Repositories\LoginHistoryRepositoryContract;
use Modules\Core\Infrastructure\Database\Models\AuditLogModel;

/**
 * Login history over audit_logs — ADR-018 D2.
 */
final class LoginHistoryRepository implements LoginHistoryRepositoryContract
{
    private const DEFAULT_PER_PAGE = 25;

    private const MAX_PER_PAGE = 100;

    /**
     * BY ROUTE NAME, NOT BY PATH. The public and admin login endpoints sit on
     * different prefixes and share stable names, so a path filter would either
     * miss one surface or need to know both spellings. A route can be remounted
     * without its name changing; that is the property being relied on.
     *
     * @var array<int, string>
     */
    private const LOGIN_ROUTES = ['auth.login', 'admin.auth.login'];

    /**
     * HTTP status to outcome.
     *
     * Named rather than numeric because the reader of a security screen is
     * asking "what happened", and 403 does not say "the password was right and
     * the account is switched off" to anyone who has not read the controller.
     * Anything unlisted degrades to 'failed' rather than being dropped: an
     * unrecognised status is still an attempt that did not succeed, and hiding
     * it would be the one failure mode this screen cannot afford.
     *
     * @var array<int, string>
     */
    private const OUTCOMES = [
        200 => 'success',
        401 => 'invalid_credentials',
        403 => 'account_inactive',
        422 => 'invalid_request',
        429 => 'rate_limited',
    ];

    public function paginate(array $criteria): array
    {
        $query = AuditLogModel::query()->whereIn('route_name', self::LOGIN_ROUTES);

        if (! empty($criteria['user_id'])) {
            $query->where('actor_id', $criteria['user_id']);
        }

        if (! empty($criteria['ip'])) {
            $query->where('ip', $criteria['ip']);
        }

        // Filtered by outcome, which is what the reader sees, so the mapping is
        // inverted here rather than making them supply a status code.
        if (! empty($criteria['outcome'])) {
            $statuses = array_keys(self::OUTCOMES, $criteria['outcome'], true);

            if ($criteria['outcome'] === 'failed') {
                // 'failed' is the catch-all, so it means "not one of the named
                // ones" rather than any particular code.
                $query->whereNotIn('response_status', array_keys(self::OUTCOMES));
            } else {
                // An unknown outcome must match nothing, not everything.
                $query->whereIn('response_status', $statuses ?: [-1]);
            }
        }

        if (! empty($criteria['from'])) {
            $query->where('created_at', '>=', $criteria['from']);
        }

        if (! empty($criteria['to'])) {
            $query->where('created_at', '<=', $criteria['to']);
        }

        $perPage = min(max((int) ($criteria['per_page'] ?? self::DEFAULT_PER_PAGE), 1), self::MAX_PER_PAGE);

        $paginator = $query
            ->orderByDesc('created_at')
            // Tie-broken by id, as the activity feed is: several attempts can
            // share a second, and without this a page boundary can drop or
            // repeat a row.
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: max((int) ($criteria['page'] ?? 1), 1));

        return [
            'items' => array_map(
                fn (AuditLogModel $row): LoginAttempt => $this->toReadModel($row),
                $paginator->items()
            ),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function toReadModel(AuditLogModel $row): LoginAttempt
    {
        return new LoginAttempt(
            id: (string) $row->id,
            userId: $row->actor_id === null ? null : (string) $row->actor_id,
            userType: $row->actor_type,
            outcome: self::OUTCOMES[(int) $row->response_status] ?? 'failed',
            status: (int) $row->response_status,
            ip: $row->ip,
            userAgent: $row->user_agent,
            deviceId: $row->device_id,
            correlationId: $row->correlation_id === null ? null : (string) $row->correlation_id,
            attemptedAt: $row->created_at?->toIso8601String(),
        );
    }
}
