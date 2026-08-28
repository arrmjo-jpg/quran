<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Presentation\HTTP\Controllers\AuthController;
use Modules\Core\Presentation\HTTP\Controllers\HealthCheckController;
use Modules\Core\Presentation\HTTP\Controllers\InvitationController;
use Modules\Core\Presentation\HTTP\Controllers\PublicSettingsController;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login')->name('auth.login');
    Route::post('phone/send-otp', [AuthController::class, 'sendPhoneOtp'])->name('auth.phone.send_otp');
    Route::post('phone/verify-otp', [AuthController::class, 'verifyPhoneOtp'])->name('auth.phone.verify_otp');
});

/*
 * Public by necessity: the invitee has no account to sign into yet, and the
 * token is the only credential. Throttled like a login for the same reason.
 */
Route::post('invitations/accept', [InvitationController::class, 'accept'])
    ->middleware('throttle:invitation-accept')
    ->name('invitations.accept');

Route::prefix('admin/auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login')->name('admin.auth.login');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [AuthController::class, 'me'])->name('auth.me');
    Route::patch('me', [AuthController::class, 'updateProfile'])->name('auth.update_profile');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
});

// Every route below requires the authenticated user to be type==='admin' —
// auth:sanctum alone only proves *who* the caller is, not that they're
// allowed on the admin surface. loginMfaChallenge is included: at that
// point the caller already holds a real (if ability-restricted) token
// for their own account, so $user->type is trustworthy there too.
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin/auth')->group(function (): void {
    Route::get('me', [AuthController::class, 'me'])->name('admin.auth.me');

    // The same handler as `PATCH /me`, on the prefix the admin panel already
    // reads from. Without it the panel would read its own profile at
    // /admin/auth/me and write it at /me — two prefixes for one account's own
    // data, which reads as an oversight rather than a decision.
    Route::patch('me', [AuthController::class, 'updateProfile'])->name('admin.auth.update_profile');

    Route::post('logout', [AuthController::class, 'logout'])->name('admin.auth.logout');

    // MFA & Session Revocation Routes
    Route::post('mfa/setup', [AuthController::class, 'mfaSetup'])->name('admin.auth.mfa.setup');
    Route::post('mfa/verify', [AuthController::class, 'mfaVerify'])->middleware('throttle:mfa-verify')->name('admin.auth.mfa.verify');
    Route::post('mfa/recovery', [AuthController::class, 'mfaRecovery'])->middleware('throttle:recovery-code')->name('admin.auth.mfa.recovery');
    Route::post('login/mfa-challenge', [AuthController::class, 'loginMfaChallenge'])->name('admin.auth.login.mfa_challenge');

    // ADR-018 D4 -- what makes "MFA is optional" true. Before these, enabling
    // MFA was permanent and a user who spent all eight recovery codes had no
    // way to get more. Both re-check the password: they are the two operations
    // that lower an account's own protection, so an unlocked laptop must not
    // be enough. Throttled like the other credential-checking routes.
    Route::post('mfa/disable', [AuthController::class, 'mfaDisable'])
        ->middleware('throttle:mfa-verify')
        ->name('admin.auth.mfa.disable');
    Route::post('mfa/recovery-codes', [AuthController::class, 'mfaRegenerateRecoveryCodes'])
        ->middleware('throttle:recovery-code')
        ->name('admin.auth.mfa.recovery_codes.regenerate');

    Route::get('sessions', [AuthController::class, 'listSessions'])->name('admin.auth.sessions.list');
    Route::delete('sessions/other', [AuthController::class, 'revokeOtherSessions'])->name('admin.auth.sessions.revoke_other');
    Route::delete('sessions/{id}', [AuthController::class, 'revokeSession'])->name('admin.auth.sessions.revoke');

    // Device Trust Routes
    Route::post('devices/trust', [AuthController::class, 'trustDevice'])->name('admin.auth.devices.trust');
    Route::get('devices', [AuthController::class, 'listTrustedDevices'])->name('admin.auth.devices.list');
    Route::delete('devices/{id}', [AuthController::class, 'revokeTrustedDevice'])->name('admin.auth.devices.revoke');
});

Route::get('languages', [PublicSettingsController::class, 'languages'])->name('settings.languages');
Route::get('settings/public', [PublicSettingsController::class, 'settings'])->name('settings.public');
Route::get('health', HealthCheckController::class)->name('health');
