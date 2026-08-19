<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('You have been invited to the Quran Competitions platform') }}</title>
</head>
<body style="font-family: system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif; line-height: 1.6; color: #0f172a;">
    <p>{{ __('Hello :name,', ['name' => $name]) }}</p>

    <p>{{ __('An account has been created for you on the Quran Competitions administration platform.') }}</p>

    {{--
        Nobody has set a password on this account, and nobody can: the link
        below is the only way to choose one, and it reaches this mailbox alone.
    --}}
    <p>{{ __('Choose your own password to activate it. Nobody else can set it for you.') }}</p>

    <p>
        <a href="{{ $acceptUrl }}"
           style="display: inline-block; background: #0369a1; color: #ffffff; padding: 12px 20px; border-radius: 8px; text-decoration: none;">
            {{ __('Activate my account') }}
        </a>
    </p>

    <p style="color: #475569; font-size: 14px;">
        {{ __('This link expires in :days days. If it stops working, ask an administrator to send a new invitation.', ['days' => $expiresInDays]) }}
    </p>

    <p style="color: #475569; font-size: 14px;">
        {{ __('If you were not expecting this, you can ignore this message — the account cannot be used until someone activates it.') }}
    </p>
</body>
</html>
