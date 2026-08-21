import React from 'react';
import { Link, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Building2, EyeOff, Globe2, IdCard, User, Users } from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { EmptyState } from '@/ui/empty-state/EmptyState';
import Badge from '@/ui/Badge';
import Spinner from '@/ui/Spinner';
import { useContestantIdentity } from '../hooks/useContestants';
import type { IdentityMembership } from '../types';

/**
 * Identity 360 — one contestant and everything that links to them
 * (ADR-016 D16, D19, D20, D23).
 *
 * A PAGE, NOT A DIALOG, AND THAT IS THE POINT OF D23. The drawer this
 * replaces had no URL, so the relationship an operator was looking at could
 * not be linked to and `User → Contestant → back` could not be walked at
 * all. A graph you can see but not traverse is a diagram.
 *
 * It replaces the drawer rather than joining it. Two surfaces rendering the
 * same graph are two places that have to be kept in step, and this codebase
 * already carries that argument three times over — `is_active` derived once
 * rather than recomputed by three clients, `status` derived once rather than
 * combined from three columns, `UserStatus` extracted the moment a second
 * caller appeared.
 *
 * There is no national ID here and no profile branch, and neither is an
 * oversight: this shows what identifies, explains or links, never what
 * describes.
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
    <section className="space-y-2.5">
      <h3 className="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
        <Icon className="w-4 h-4 text-brand-600" />
        <span>{title}</span>
      </h3>
      <div className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
        {children}
      </div>
    </section>
  );
}

function Field({ label, value }: { label: string; value: React.ReactNode }): React.JSX.Element {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-[11px] text-slate-500">{label}</span>
      <span className="text-slate-900 dark:text-white">{value}</span>
    </div>
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
    <div className="rounded-xl border border-slate-200 dark:border-slate-800 p-3 space-y-1.5">
      <div className="flex items-center justify-between gap-2">
        <span className="font-semibold text-slate-900 dark:text-white">
          {/* Null when the circle was deleted after this period ended.
              Saying so beats a blank, which reads as membership in nothing. */}
          {membership.circle_name ?? t('circle_deleted')}
        </span>
        <Badge variant={membership.is_active ? 'success' : 'neutral'}>
          {membership.is_active ? t('membership_active') : t('membership_ended')}
        </Badge>
      </div>

      {membership.center_name !== null && (
        <p className="flex items-center gap-1.5 text-slate-500">
          <Building2 className="w-3.5 h-3.5" />
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

export default function ContestantIdentityPage(): React.JSX.Element {
  const { t } = useTranslation('contestants');
  const { t: tc } = useTranslation('common');
  const { id } = useParams<{ id: string }>();

  const { data, isLoading, isError, refetch } = useContestantIdentity(id ?? '');

  const crumbs = [
    { label: tc('home'), href: '/' },
    { label: t('title'), href: '/contestants' },
    { label: data?.contestant.full_name ?? t('identity_title') },
  ];

  if (isLoading) {
    return (
      <PageLayout title={t('identity_title')} breadcrumbs={crumbs}>
        <div className="flex justify-center py-20">
          <Spinner size="lg" />
        </div>
      </PageLayout>
    );
  }

  if (isError || data === undefined) {
    return (
      <PageLayout title={t('identity_title')} breadcrumbs={crumbs}>
        <ErrorState onRetry={() => void refetch()} />
      </PageLayout>
    );
  }

  const { contestant, user, country, memberships, withheld } = data;
  const membershipsWithheld = withheld.includes('memberships');

  return (
    <PageLayout
      title={contestant.full_name}
      subtitle={t('identity_subtitle')}
      breadcrumbs={crumbs}
    >
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 text-xs">
        <Section icon={IdCard} title={t('section_contestant')}>
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-base font-bold text-slate-900 dark:text-white">
                {contestant.full_name}
              </span>
              <div className="flex items-center gap-1.5">
                {contestant.is_deleted && <Badge variant="neutral">{t('badge_deleted')}</Badge>}
                <Badge variant={contestant.profile_completeness.is_complete ? 'success' : 'warning'}>
                  {`${contestant.profile_completeness.completeness_percent}%`}
                </Badge>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <Field label={t('field_date_of_birth')} value={contestant.date_of_birth} />
              <Field label={t('field_gender')} value={t(`gender_${contestant.gender}`)} />
              <Field label={t('field_phone')} value={contestant.phone_number} />
            </div>
          </div>
        </Section>

        <Section icon={User} title={t('section_account')}>
          {user === null ? (
            /* contestants.user_id is NOT NULL and RESTRICT, so this is a
               broken link rather than an absent one — worth naming. */
            <p className="text-rose-600 dark:text-rose-400">{t('account_missing')}</p>
          ) : (
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="font-semibold text-slate-900 dark:text-white">{user.name}</span>
                <Badge variant={STATUS_VARIANT[user.status] ?? 'neutral'}>
                  {t(`account_status_${user.status}`)}
                </Badge>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <Field label={t('field_account_type')} value={t(`type_${user.type}`)} />
              </div>

              {/* The link D18 admitted `id` for. It only became followable in
                  Story 3 — before /users/:id existed this was an id printed
                  as text. */}
              <Link
                to={`/users/${user.id}`}
                className="inline-block text-brand-600 hover:underline font-semibold"
              >
                {t('open_account')}
              </Link>

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
            <p className="flex items-center gap-2 text-slate-500">
              <EyeOff className="w-4 h-4 shrink-0" />
              <span>{t('memberships_withheld')}</span>
            </p>
          ) : memberships.length === 0 ? (
            <EmptyState title={t('memberships_empty')} />
          ) : (
            <div className="space-y-2">
              {memberships.map((membership) => (
                <MembershipRow key={membership.id} membership={membership} t={t} />
              ))}
            </div>
          )}
        </Section>
      </div>
    </PageLayout>
  );
}
