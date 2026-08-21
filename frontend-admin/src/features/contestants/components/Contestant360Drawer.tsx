import React from 'react';
import { useTranslation } from 'react-i18next';
import { Building2, EyeOff, Globe2, User, Users } from 'lucide-react';
import { Dialog } from '@/ui/dialog/Dialog';
import Badge from '@/ui/Badge';
import type { ContestantIdentity, IdentityMembership } from '../types';

export interface Contestant360DrawerProps {
  identity: ContestantIdentity | null;
  isOpen:   boolean;
  onClose:  () => void;
}

/**
 * Identity 360 — one contestant and everything that links to them
 * (ADR-016 D16, D19, D20).
 *
 * FIVE TABS WERE DELETED HERE, and their absence is the point. This drawer
 * used to render applications, a video player, evaluations, a result reading
 * "Average score: 94.5 / 100 — qualified" and a two-entry timeline, none of
 * which any endpoint has ever returned. They were hard-coded strings in a
 * translation file.
 *
 * That was ignorable while everything around them was equally hollow. Beside
 * a true account status and a true circle history it would not be: an
 * operator with three accurate panels in front of them has every reason to
 * believe the fourth. The tabs come back when there is an endpoint behind
 * them (D21).
 *
 * What is left is a relationship view. It shows what identifies, explains or
 * links — never what describes — which is why there is no national ID here
 * and no profile branch at all.
 */
const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
  active: 'success',
  pending_activation: 'warning',
  deactivated: 'danger',
  deleted: 'neutral',
};

function Section({
  icon: Icon,
  title,
  children,
}: {
  icon: typeof User;
  title: string;
  children: React.ReactNode;
}): React.JSX.Element {
  return (
    <section className="space-y-2">
      <h4 className="flex items-center gap-1.5 text-xs font-bold text-slate-900 dark:text-white">
        <Icon className="w-3.5 h-3.5 text-brand-600" />
        <span>{title}</span>
      </h4>
      {children}
    </section>
  );
}

function Field({ label, value }: { label: string; value: React.ReactNode }): React.JSX.Element {
  return (
    <p className="text-slate-600 dark:text-slate-300">
      {label}: <span className="text-slate-900 dark:text-white">{value}</span>
    </p>
  );
}

function MembershipRow({
  membership,
  t,
}: {
  membership: IdentityMembership;
  t: (key: string, options?: Record<string, unknown>) => string;
}): React.JSX.Element {
  const formatDate = (iso: string | null): string =>
    iso === null ? '—' : new Date(iso).toLocaleDateString();

  return (
    <div className="p-3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1.5">
      <div className="flex items-center justify-between gap-2">
        <span className="font-semibold text-slate-900 dark:text-white">
          {/* Null when the circle was deleted after this period ended. Saying
              so beats an empty cell, which reads as a membership in nothing. */}
          {membership.circle_name ?? t('circle_deleted')}
        </span>
        <Badge variant={membership.is_active ? 'success' : 'neutral'}>
          {membership.is_active ? t('membership_active') : t('membership_ended')}
        </Badge>
      </div>

      {membership.center_name !== null && (
        <p className="flex items-center gap-1.5 text-slate-500">
          <Building2 className="w-3 h-3" />
          <span>
            {membership.center_city === null
              ? membership.center_name
              : `${membership.center_name} — ${membership.center_city}`}
          </span>
        </p>
      )}

      <p className="text-slate-500">
        {t('membership_period', {
          from: formatDate(membership.joined_at),
          to: membership.left_at === null ? t('membership_ongoing') : formatDate(membership.left_at),
        })}
      </p>

      {membership.reason !== null && (
        <p className="text-slate-500">{t('membership_reason', { reason: membership.reason })}</p>
      )}
    </div>
  );
}

export function Contestant360Drawer({
  identity,
  isOpen,
  onClose,
}: Contestant360DrawerProps): React.JSX.Element | null {
  const { t } = useTranslation('contestants');

  if (identity === null) return null;

  const { contestant, user, country, memberships, withheld } = identity;
  const membershipsWithheld = withheld.includes('memberships');

  return (
    <Dialog
      isOpen={isOpen}
      onClose={onClose}
      title={t('drawer_title', { name: contestant.full_name })}
      className="max-w-2xl"
    >
      <div className="space-y-5 text-xs text-start max-h-[70vh] overflow-y-auto">
        <Section icon={User} title={t('section_contestant')}>
          <div className="bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-100 dark:border-slate-800 space-y-1">
            <div className="flex items-center justify-between">
              <span className="font-bold text-sm text-slate-900 dark:text-white">
                {contestant.full_name}
              </span>
              <Badge variant={contestant.profile_completeness.is_complete ? 'success' : 'warning'}>
                {`${contestant.profile_completeness.completeness_percent}%`}
              </Badge>
            </div>
            <Field label={t('field_date_of_birth')} value={contestant.date_of_birth} />
            <Field label={t('field_gender')} value={t(`gender_${contestant.gender}`)} />
            <Field label={t('field_phone')} value={contestant.phone_number} />
          </div>
        </Section>

        <Section icon={User} title={t('section_account')}>
          {user === null ? (
            /* contestants.user_id is NOT NULL and RESTRICT, so this is a
               broken link rather than an absent one — worth naming. */
            <p className="text-rose-600 dark:text-rose-400">{t('account_missing')}</p>
          ) : (
            <div className="bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-100 dark:border-slate-800 space-y-1">
              <div className="flex items-center justify-between">
                <span className="font-semibold text-slate-900 dark:text-white">{user.name}</span>
                <Badge variant={STATUS_VARIANT[user.status] ?? 'neutral'}>
                  {t(`account_status_${user.status}`)}
                </Badge>
              </div>
              <Field label={t('field_account_type')} value={t(`type_${user.type}`)} />
              {/* Shown, not linked: the panel has no /users/{id} route, and a
                  link that 404s is worse than an id somebody can search for. */}
              <p className="font-mono text-[10px] text-slate-400 break-all">{user.id}</p>
              {user.name !== contestant.full_name && (
                <p className="text-amber-700 dark:text-amber-400">{t('name_divergence')}</p>
              )}
            </div>
          )}
        </Section>

        <Section icon={Globe2} title={t('section_country')}>
          {country === null ? (
            <p className="text-slate-400">{t('country_missing')}</p>
          ) : (
            <p className="text-slate-900 dark:text-white">
              {country.name} <span className="font-mono text-slate-400">({country.iso2})</span>
            </p>
          )}
        </Section>

        <Section icon={Users} title={t('section_memberships')}>
          {membershipsWithheld ? (
            /* D20: not the same as "none", and the screen must not say the
               one when the other is true. */
            <p className="flex items-center gap-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 px-3 py-2.5 text-slate-500">
              <EyeOff className="w-3.5 h-3.5 shrink-0" />
              <span>{t('memberships_withheld')}</span>
            </p>
          ) : memberships.length === 0 ? (
            <p className="text-slate-400">{t('memberships_empty')}</p>
          ) : (
            <div className="space-y-2">
              {memberships.map((membership) => (
                <MembershipRow key={membership.id} membership={membership} t={t} />
              ))}
            </div>
          )}
        </Section>
      </div>
    </Dialog>
  );
}
