import React from 'react';
import { Link, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { EyeOff, IdCard, KeyRound, ShieldCheck, User } from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { ErrorState } from '@/ui/error-state/ErrorState';
import Badge from '@/ui/Badge';
import Spinner from '@/ui/Spinner';
import { useUser } from '../hooks/useUsers';
import type { UserStatus } from '../types';

/**
 * One account, and what links to it — ADR-016 D22, D23.
 *
 * BUILT MOSTLY OUT OF THINGS THAT ALREADY EXISTED. `GET /admin/users/{id}`
 * has been serving the account with its effective permission set since Epic
 * 1, and `useUser(id)` has been sitting in this feature called from nowhere.
 * What was missing was a route — which is also why D18's `id`-as-a-link was
 * an id printed as text until now, and why the identity graph could be seen
 * but not walked.
 *
 * The contestant branch is the reverse of Identity 360 and carries the three
 * fields D22 admits. A reader without contestants.view is told the branch was
 * WITHHELD, never handed a null — because null is what an account with no
 * contestant returns, and showing that to somebody not permitted to know
 * would be the panel asserting something untrue about a person.
 */
const STATUS_VARIANT: Record<UserStatus, 'success' | 'warning' | 'danger' | 'neutral'> = {
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
      <span className="text-slate-900 dark:text-white break-all">{value}</span>
    </div>
  );
}

export default function UserDetailPage(): React.JSX.Element {
  const { t } = useTranslation('users');
  const { t: tc } = useTranslation('common');
  const { id } = useParams<{ id: string }>();

  const { data, isLoading, isError, refetch } = useUser(id ?? null);

  const crumbs = [
    { label: tc('home'), href: '/' },
    { label: t('page_title'), href: '/users' },
    { label: data?.name ?? t('detail_title') },
  ];

  if (isLoading) {
    return (
      <PageLayout title={t('detail_title')} breadcrumbs={crumbs}>
        <div className="flex justify-center py-20">
          <Spinner size="lg" />
        </div>
      </PageLayout>
    );
  }

  if (isError || data === undefined) {
    return (
      <PageLayout title={t('detail_title')} breadcrumbs={crumbs}>
        <ErrorState onRetry={() => void refetch()} />
      </PageLayout>
    );
  }

  const contestantWithheld = data.withheld.includes('contestant');

  return (
    <PageLayout title={data.name} subtitle={t('detail_subtitle')} breadcrumbs={crumbs}>
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 text-xs">
        <Section icon={User} title={t('section_account')}>
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-base font-bold text-slate-900 dark:text-white">{data.name}</span>
              <Badge variant={STATUS_VARIANT[data.status]}>{t(`status_${data.status}`)}</Badge>
            </div>

            <div className="grid grid-cols-2 gap-3">
              {/* Present here and deliberately absent from a contestant
                  context — D18 keeps it out there and points at this screen. */}
              <Field label={t('field_email')} value={data.email} />
              <Field label={t('column_type')} value={t(`type_${data.type}`)} />
              <Field label={t('field_locale')} value={data.preferred_locale} />
              <Field
                label={t('field_mfa')}
                value={t(data.mfa_enabled ? 'mfa_on' : 'mfa_off')}
              />
            </div>
          </div>
        </Section>

        <Section icon={IdCard} title={t('section_contestant')}>
          {contestantWithheld ? (
            /* D20's distinction. Not the same as "this person is not a
               contestant", and the screen must not say the one when the
               other is true. */
            <p className="flex items-center gap-2 text-slate-500">
              <EyeOff className="w-4 h-4 shrink-0" />
              <span>{t('contestant_withheld')}</span>
            </p>
          ) : data.contestant === null ? (
            <p className="text-slate-400">{t('contestant_none')}</p>
          ) : (
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <span className="font-semibold text-slate-900 dark:text-white">
                  {data.contestant.full_name}
                </span>
                {data.contestant.is_deleted && (
                  <Badge variant="neutral">{t('contestant_deleted')}</Badge>
                )}
              </div>

              {/* Two columns nothing keeps in step. D22 admits full_name
                  because the divergence is itself information. */}
              {data.contestant.full_name !== data.name && (
                <p className="text-amber-700 dark:text-amber-400">{t('name_divergence')}</p>
              )}

              <Link
                to={`/contestants/${data.contestant.id}`}
                className="inline-block text-brand-600 hover:underline font-semibold"
              >
                {t('open_identity')}
              </Link>
            </div>
          )}
        </Section>

        <Section icon={ShieldCheck} title={t('column_roles')}>
          {data.roles.length === 0 ? (
            <p className="text-slate-400">{t('no_roles')}</p>
          ) : (
            <div className="flex flex-wrap gap-1.5">
              {data.roles.map((role) => (
                <Badge key={role} variant="neutral">
                  <span className="font-mono text-[11px]">{role}</span>
                </Badge>
              ))}
            </div>
          )}
        </Section>

        <Section icon={KeyRound} title={t('section_permissions')}>
          {data.permissions.length === 0 ? (
            <p className="text-slate-400">{t('no_permissions')}</p>
          ) : (
            <>
              {/* The whole reason the detail endpoint differs from the list:
                  resolving these costs a query per account, which is right
                  for one person and wrong for a page of twenty. */}
              <p className="mb-2 text-slate-500">
                {t('permissions_count', { count: data.permissions.length })}
              </p>
              <div className="flex flex-wrap gap-1.5 max-h-64 overflow-y-auto">
                {data.permissions.map((permission) => (
                  <span
                    key={permission}
                    className="font-mono text-[10px] rounded-md bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-slate-600 dark:text-slate-300"
                  >
                    {permission}
                  </span>
                ))}
              </div>
            </>
          )}
        </Section>
      </div>
    </PageLayout>
  );
}
