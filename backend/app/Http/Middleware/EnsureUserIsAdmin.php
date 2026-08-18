<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->type !== 'admin') {
            // Thrown directly (not abort(403, ...)) so it matches the
            // AccessDeniedHttpException render() handler in bootstrap/app.php
            // and returns the same JSON envelope as every other 403 in the API.
            throw new AccessDeniedHttpException('This action requires admin privileges.');
        }

        /** @var Response */
        return $next($request);
    }
}
