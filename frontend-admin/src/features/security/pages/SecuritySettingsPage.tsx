import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { KeyRound, Laptop, ShieldCheck, ShieldOff, Trash2 } from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { Card, CardHeader } from '@/ui/Card';
import Button from '@/ui/Button';
import Badge from '@/ui/Badge';
import { Input } from '@/ui/input/Input';
import { Dialog } from '@/ui/dialog/Dialog';
import Spinner from '@/ui/Spinner';
import { useAuth } from '@/core/auth/AuthContext';
import { deviceId } from '@/core/api/http';
import {
  useDisableMfa,
  useRegenerateRecoveryCodes,
  useRevokeDevice,
  useTrustThisDevice,
  useTrustedDevices,
} from '../hooks/useSecurity';
import type { TrustedDevice } from '../types';

/**
 * The account's own security settings — ADR-018 D4, D5.
 *
 * One screen for the three things that were previously scattered or missing:
 * MFA (a modal reachable from nowhere in particular), trusted devices (no UI
 * at all), and the ability to turn MFA off (no endpoint at all).
 *
 * This is a SELF-SERVICE screen: it acts on the signed-in account only. It
 * needs no permission, because holding an account is the authorisation — the
 * `security.view` grant (D3) gates the login history, which is about everyone.
 */
export default function SecuritySettingsPage(): React.JSX.Element {
  const { t } = useTranslation('security');
  const { t: tc } = useTranslation('common');
  const { user } = useAuth();

  const { data: devices, isLoading } = useTrustedDevices();
  const trustDevice = useTrustThisDevice();
  const revokeDevice = useRevokeDevice();
  const disableMfa = useDisableMfa();
  const regenerate = useRegenerateRecoveryCodes();

  const [passwordPrompt, setPasswordPrompt] = useState<'disable' | 'regenerate' | null>(null);
  const [password, setPassword] = useState('');
  const [freshCodes, setFreshCodes] = useState<string[] | null>(null);

  const thisBrowser = deviceId();
  const mfaEnabled = Boolean(user?.mfa_enabled);

  const closePrompt = (): void => {
    setPasswordPrompt(null);
    setPassword('');
  };

  const submitPrompt = async (): Promise<void> => {
    if (passwordPrompt === 'disable') {
      await disableMfa.mutateAsync(password);
      closePrompt();
      return;
    }

    if (passwordPrompt === 'regenerate') {
      const codes = await regenerate.mutateAsync(password);
      setFreshCodes(codes);
      closePrompt();
    }
  };

  const promptError = disableMfa.isError || regenerate.isError;

  return (
    <PageLayout
      title={t('settings_title')}
      subtitle={t('settings_subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('settings_title') }]}
    >
      <div className="grid gap-6 lg:grid-cols-2">
        {/* ── Multi-factor authentication ─────────────────────────────── */}
        <Card>
          <CardHeader title={t('mfa_title')} subtitle={t('mfa_subtitle')} />

          <div className="p-4 pt-0 space-y-4">
            <div className="flex items-center gap-2">
              {mfaEnabled ? (
                <Badge variant="success">{t('mfa_on')}</Badge>
              ) : (
                <Badge variant="neutral">{t('mfa_off')}</Badge>
              )}
            </div>

            {mfaEnabled ? (
              <div className="flex flex-wrap gap-2">
                <Button variant="outline" onClick={() => setPasswordPrompt('regenerate')}>
                  <KeyRound className="h-4 w-4" />
                  {t('mfa_regenerate')}
                </Button>

                {/* Turning it off is the destructive one, and is styled as
                    such: it lowers this account's own protection. */}
                <Button variant="danger" onClick={() => setPasswordPrompt('disable')}>
                  <ShieldOff className="h-4 w-4" />
                  {t('mfa_disable')}
                </Button>
              </div>
            ) : (
              <p className="text-sm text-slate-500">{t('mfa_enable_hint')}</p>
            )}

            {/* ADR-018 D4: nothing here compels MFA. Said on the screen so a
                reader does not mistake its absence for an oversight. */}
            <p className="text-[11px] text-slate-400">{t('mfa_optional_note')}</p>
          </div>
        </Card>

        {/* ── Trusted devices ─────────────────────────────────────────── */}
        <Card>
          <CardHeader
            title={t('devices_title')}
            subtitle={t('devices_subtitle')}
            action={
              <Button
                variant="secondary"
                disabled={trustDevice.isPending}
                onClick={() => void trustDevice.mutateAsync()}
              >
                <ShieldCheck className="h-4 w-4" />
                {t('devices_trust_this')}
              </Button>
            }
          />

          <div className="p-4 pt-0">
            {/* The cost is stated where the button is, not buried in an ADR:
                a trusted device skips the second factor for 30 days. */}
            <p className="mb-3 text-[11px] text-amber-700 dark:text-amber-500">
              {t('devices_tradeoff')}
            </p>

            {isLoading ? (
              <div className="flex justify-center py-6">
                <Spinner />
              </div>
            ) : (devices?.length ?? 0) === 0 ? (
              <p className="py-6 text-center text-sm text-slate-500">{t('devices_empty')}</p>
            ) : (
              <ul className="divide-y divide-slate-200 dark:divide-slate-800">
                {devices?.map((device: TrustedDevice) => {
                  const isThisBrowser = device.device_id === thisBrowser;

                  return (
                    <li key={device.id} className="flex items-center justify-between gap-3 py-3">
                      <div className="flex min-w-0 items-start gap-2">
                        <Laptop className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                        <div className="min-w-0">
                          <div className="flex items-center gap-2">
                            <span className="text-sm">{device.ip ?? '—'}</span>
                            {isThisBrowser && (
                              <Badge variant="info">{t('devices_this_browser')}</Badge>
                            )}
                          </div>
                          <p className="truncate text-[11px] text-slate-500" title={device.user_agent ?? ''}>
                            {device.user_agent ?? '—'}
                          </p>
                          <p className="text-[11px] text-slate-400">
                            {t('devices_expires', {
                              at: new Date(device.expires_at).toLocaleDateString(),
                            })}
                            {device.last_used_at
                              ? ` · ${t('devices_last_used', {
                                  at: new Date(device.last_used_at).toLocaleDateString(),
                                })}`
                              : ` · ${t('devices_never_used')}`}
                          </p>
                        </div>
                      </div>

                      <Button
                        variant="ghost"
                        disabled={revokeDevice.isPending}
                        onClick={() =>
                          void revokeDevice.mutateAsync({
                            id: device.id,
                            wasThisBrowser: isThisBrowser,
                          })
                        }
                      >
                        <Trash2 className="h-4 w-4 text-rose-600" />
                      </Button>
                    </li>
                  );
                })}
              </ul>
            )}
          </div>
        </Card>
      </div>

      {/* ── Password confirmation ─────────────────────────────────────── */}
      <Dialog
        isOpen={passwordPrompt !== null}
        onClose={closePrompt}
        title={passwordPrompt === 'disable' ? t('confirm_disable_title') : t('confirm_regenerate_title')}
      >
        <div className="space-y-4">
          <p className="text-sm text-slate-600 dark:text-slate-300">
            {passwordPrompt === 'disable' ? t('confirm_disable_body') : t('confirm_regenerate_body')}
          </p>

          <Input
            type="password"
            label={t('confirm_password')}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
          />

          {promptError && <p className="text-sm text-rose-600">{t('confirm_password_wrong')}</p>}

          <div className="flex justify-end gap-2">
            <Button variant="secondary" onClick={closePrompt}>
              {tc('cancel')}
            </Button>
            <Button
              variant={passwordPrompt === 'disable' ? 'danger' : 'primary'}
              disabled={password.length === 0 || disableMfa.isPending || regenerate.isPending}
              onClick={() => void submitPrompt()}
            >
              {tc('confirm')}
            </Button>
          </div>
        </div>
      </Dialog>

      {/* ── The new recovery codes, shown once ─────────────────────────── */}
      <Dialog
        isOpen={freshCodes !== null}
        onClose={() => setFreshCodes(null)}
        title={t('codes_title')}
      >
        <div className="space-y-4">
          {/* Shown once because only their hashes are stored -- the same
              reason the trust token is returned once. */}
          <p className="text-sm text-slate-600 dark:text-slate-300">{t('codes_body')}</p>

          <ul className="grid grid-cols-2 gap-2">
            {freshCodes?.map((code) => (
              <li
                key={code}
                className="rounded bg-slate-100 px-2 py-1.5 text-center font-mono text-sm dark:bg-slate-800"
              >
                {code}
              </li>
            ))}
          </ul>

          <div className="flex justify-end">
            <Button onClick={() => setFreshCodes(null)}>{tc('close')}</Button>
          </div>
        </div>
      </Dialog>
    </PageLayout>
  );
}
