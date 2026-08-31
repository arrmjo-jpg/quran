<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;

uses()->group('core', 'architecture', 'identity', 'permissions');

/*
|--------------------------------------------------------------------------
| Admin routes ↔ permission catalogue — ADR-015 §4.3
|--------------------------------------------------------------------------
|
| The binding the ADR asks for, in both directions. Neither half is
| redundant:
|
|   - A route reachable without a permission check is a hole. It would
|     be guarded only by EnsureUserIsAdmin, which asks whether the
|     account may reach the admin surface at all — not whether it may
|     perform this action. That is exactly the collapse the whole epic
|     exists to undo, and it is the sort of thing that arrives quietly
|     when someone adds a route next to twenty that already have one.
|
|   - A check naming a permission the catalogue does not define is a
|     door nobody can ever open: Gate has no such ability registered,
|     so the answer is always "denied", and no role can be edited to
|     fix it because the permission cannot be granted.
*/

/** @return array<int, Illuminate\Routing\Route> */
function adminApiRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        static function ($route): bool {
            $name = $route->getName();

            return $name !== null
                && str_starts_with($name, 'admin.')
                && in_array('admin', $route->gatherMiddleware(), true);
        }
    ));
}

/** The permission a route's `can:` middleware names, if it has one. */
function routePermission(Illuminate\Routing\Route $route): ?string
{
    foreach ($route->gatherMiddleware() as $middleware) {
        if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
            return substr($middleware, 4);
        }
    }

    return null;
}

/**
 * Routes that act on the caller's own account, and nothing else.
 *
 * These carry no permission check on purpose. Every administrator must
 * be able to read their own profile, end their own session, set up
 * their own MFA and revoke their own devices, whatever role they hold —
 * gating those behind users.update would mean a role could be edited
 * into one that cannot log out or secure its own account, and the
 * account most likely to need that is the one whose credentials have
 * just been compromised.
 *
 * Enumerated rather than matched by an `admin.auth.*` prefix: the point
 * is that each of these was looked at and found to be self-service. A
 * prefix rule would silently absorb a future admin.auth route that
 * acts on somebody else.
 *
 * @var array<int, string>
 */
const SELF_SERVICE_ROUTES = [
    'admin.auth.me',
    // Editing one's own name, locale and profile. Self-service for the same
    // reason reading it is: an administrator must be able to correct their own
    // details whatever role they hold, and gating this behind users.update
    // would mean a role could be edited into one that cannot fix its own
    // biography.
    'admin.auth.update_profile',
    'admin.auth.logout',
    'admin.auth.mfa.setup',
    'admin.auth.mfa.verify',
    'admin.auth.mfa.recovery',
    // ADR-018 D4. Self-service for the same reason setup and verify are: an
    // account manages its OWN second factor. Both re-check the password
    // server-side, which is the control that matters here -- a permission
    // would say which ROLE may turn MFA off, and the answer is "the account
    // whose MFA it is", which no role can express.
    'admin.auth.mfa.disable',
    'admin.auth.mfa.recovery_codes.regenerate',
    'admin.auth.login.mfa_challenge',
    'admin.auth.sessions.list',
    'admin.auth.sessions.revoke_other',
    'admin.auth.sessions.revoke',
    'admin.auth.devices.trust',
    'admin.auth.devices.list',
    'admin.auth.devices.revoke',
    // ADR-020 D4/D9. An account's own notification preferences. Self-service
    // for the reason MFA is: a permission names which ROLE may act, and the
    // answer here is "the account whose preferences these are". The controller
    // reads the account from the token and never from the payload, so there is
    // no other account these routes can reach.
    'admin.notifications.preferences.index',
    'admin.notifications.preferences.update',
];

test('every admin route checks a permission, except self-service', function (): void {
    $unguarded = [];

    foreach (adminApiRoutes() as $route) {
        $name = $route->getName();

        if (in_array($name, SELF_SERVICE_ROUTES, true)) {
            continue;
        }

        if (routePermission($route) === null) {
            $unguarded[] = $name;
        }
    }

    expect($unguarded)->toBeEmpty(
        'Admin routes with no permission check: '.implode(', ', $unguarded)
    );
});

test('the self-service exemption list has not gone stale', function (): void {
    // An entry naming a route that no longer exists is an exemption
    // nobody is watching, and the next route to take that name inherits
    // a hole.
    $existing = array_map(static fn ($r): ?string => $r->getName(), adminApiRoutes());
    $orphans = array_values(array_diff(SELF_SERVICE_ROUTES, $existing));

    expect($orphans)->toBeEmpty(
        'Self-service exemptions for routes that no longer exist: '.implode(', ', $orphans)
    );
});

test('every permission an admin route checks exists in the catalogue', function (): void {
    $unknown = [];

    foreach (adminApiRoutes() as $route) {
        $permission = routePermission($route);

        if ($permission !== null && ! PermissionCatalog::has($permission)) {
            $unknown[] = "{$route->getName()} -> {$permission}";
        }
    }

    expect($unknown)->toBeEmpty(
        'Routes checking permissions the catalogue does not define: '.implode(', ', $unknown)
    );
});

test('the admin surface is guarded before any permission is consulted', function (): void {
    // Order matters and is not obvious: `can:` resolves the user from the
    // request, so a route carrying it without auth+admin ahead of it
    // would answer "denied" for a guest instead of "unauthenticated",
    // and would let a contestant's permissions decide an admin route.
    foreach (adminApiRoutes() as $route) {
        $middleware = $route->gatherMiddleware();

        $authIndex = array_search('auth:sanctum', $middleware, true);
        $adminIndex = array_search('admin', $middleware, true);
        $canIndex = null;

        foreach ($middleware as $i => $m) {
            if (is_string($m) && str_starts_with($m, 'can:')) {
                $canIndex = $i;
                break;
            }
        }

        expect($authIndex)->not->toBeFalse("{$route->getName()} is not behind auth:sanctum");
        expect($adminIndex)->not->toBeFalse("{$route->getName()} is not behind the admin gate");

        if ($canIndex !== null) {
            expect($authIndex)->toBeLessThan($canIndex, "{$route->getName()} checks a permission before authenticating");
            expect($adminIndex)->toBeLessThan($canIndex, "{$route->getName()} checks a permission before the admin gate");
        }
    }
});
