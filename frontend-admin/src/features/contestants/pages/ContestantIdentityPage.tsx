import React, { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  AlertTriangle,
  Building2,
  EyeOff,
  Globe2,
  IdCard,
  Link2,
  Pencil,
  Trash2,
  User,
  UserCircle2,
  Users,
} from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { EmptyState } from '@/ui/empty-state/EmptyState';
import { ConfirmDialog } from '@/ui/dialog/Dialog';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import Spinner from '@/ui/Spinner';
import { useContestantIdentity, useDeleteContestant } from '../hooks/useContestants';
import { ContestantFormDialog } from '../components/ContestantFormDialog';
import type { IdentityMembership, ResolvedPhoto } from '../types';

/**
 * The contestant's screen — profile and relations, one route, two tabs
 * (ADR-016 D19, D20, D23, D24).
 *
 * ONE ROUTE, BECAUSE D23 ALREADY SETTLED THAT. A second page for "the
 * profile" would put the same person behind two URLs one story after a
 * drawer was removed for putting them behind none. Tabs give the separation
 * without the duplication.
 *
 * The split is by what the data IS, not by where it comes from. Profile
 * holds the person's own record — including their country, which is a fact
 * about them rather than a link that leads anywhere. Relations holds what
 * they are attached to: the account, and the circles they have belonged to.
 * Nothing appears twice.
 *
 * There is no national ID on either tab, and no applications or appeals.
 */
type Tab = 'profile' | 'relations';

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

/**
 * The photograph, or an honest placeholder.
 *
 * `url` can be null for an asset that really exists — a private disk issues
 * presigned URLs on demand and has no permanent address — so "we have a
 * photo" and "we can show it" are separate questions and this answers both
 * rather than rendering a broken image.
 */
function Photo({ photo, alt }: { photo: ResolvedPhoto | null; alt: string }): React.JSX.Element {
  const src = photo?.thumb ?? photo?.url ?? null;

  if (src === null || photo?.is_image !== true) {
    return (
      <div className="w-28 h-28 shrink-0 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
        <UserCircle2 className="w-12 h-12" />
      </div>
    );
  }

  return (
    <img
      src={src}
      alt={alt}
      className="w-28 h-28 shrink-0 rounded-2xl object-cover border border-slate-200 dark:border-slate-800"
    />
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
  const navigate = useNavigate();

  const [tab, setTab] = useState<Tab>('profile');
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);

  const { data, isLoading, isError, refetch } = useContestantIdentity(id ?? '');
  const deleteContestant = useDeleteContestant();

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
  const completeness = contestant.profile_completeness;

  const tabs: { id: Tab; icon: typeof User }[] = [
    { id: 'profile', icon: IdCard },
    { id: 'relations', icon: Link2 },
  ];

  return (
    <PageLayout
      title={contestant.full_name}
      subtitle={t('identity_subtitle')}
      breadcrumbs={crumbs}
      actions={
        // No restore here, and its absence is deliberate. This endpoint reads
        // through findOrFail, which the soft-delete scope filters, so a
        // deleted contestant 404s and `is_deleted` is ALWAYS false on this
        // page. A restore button would be a branch that can never render —
        // Story 4 shipped one before this audit found it could not.
        //
        // Restoring is done from the list with `show deleted` ticked, which is
        // the only surface that can hold a deleted row. D17 decides who sees
        // what here: data_entry holds update and neither of the destructive
        // pair.
        <div className="flex flex-wrap items-center gap-2">
          <PermissionWrapper permission="contestants.update">
            <Button variant="outline" onClick={() => setIsFormOpen(true)}>
              <Pencil className="w-4 h-4" />
              <span>{tc('edit')}</span>
            </Button>
          </PermissionWrapper>

          <PermissionWrapper permission="contestants.delete">
            <Button variant="danger" onClick={() => setIsDeleteOpen(true)}>
              <Trash2 className="w-4 h-4" />
              <span>{tc('delete')}</span>
            </Button>
          </PermissionWrapper>
        </div>
      }
    >
      <div className="space-y-5 text-xs">
        <div className="flex gap-1 border-b border-slate-200 dark:border-slate-800 pb-1">
          {tabs.map(({ id: tabId, icon: Icon }) => (
            <button
              key={tabId}
              onClick={() => setTab(tabId)}
              className={`flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors ${
                tab === tabId
                  ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/60 dark:text-brand-300'
                  : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200'
              }`}
            >
              <Icon className="w-3.5 h-3.5" />
              <span>{t(`tab_${tabId}`)}</span>
            </button>
          ))}
        </div>

        {tab === 'profile' && (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <Section icon={IdCard} title={t('section_contestant')}>
              <div className="flex gap-4">
                <Photo photo={contestant.photo} alt={contestant.full_name} />

                <div className="flex-1 min-w-0 space-y-3">
                  {/* No deleted badge: see the note on `actions` — this
                      page cannot be reached for a deleted contestant. */}
                  <span className="text-base font-bold text-slate-900 dark:text-white">
                    {contestant.full_name}
                  </span>

                  <div className="grid grid-cols-2 gap-3">
                    {/* Calculated by the server from the date of birth. A
                        component doing the arithmetic would be a component
                        holding a business rule. */}
                    <Field label={t('field_age')} value={t('age_years', { count: contestant.age })} />
                    <Field label={t('field_date_of_birth')} value={contestant.date_of_birth} />
                    <Field label={t('field_gender')} value={t(`gender_${contestant.gender}`)} />
                    <Field label={t('field_phone')} value={contestant.phone_number} />
                  </div>
                </div>
              </div>
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

            <Section icon={AlertTriangle} title={t('section_completeness')}>
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-slate-600 dark:text-slate-300">
                    {t('completeness_label')}
                  </span>
                  <Badge variant={completeness.is_complete ? 'success' : 'warning'}>
                    {`${completeness.completeness_percent}%`}
                  </Badge>
                </div>

                {completeness.is_complete ? (
                  <p className="text-emerald-700 dark:text-emerald-400">
                    {t('completeness_complete')}
                  </p>
                ) : (
                  <div className="space-y-1.5">
                    <p className="text-slate-500">{t('completeness_missing_label')}</p>
                    <ul className="space-y-1">
                      {completeness.missing_fields.map((field) => (
                        <li key={field} className="flex items-center gap-1.5 text-amber-700 dark:text-amber-400">
                          <AlertTriangle className="w-3 h-3 shrink-0" />
                          {/* Named, not shown as a raw column. The server
                              returns field keys; the panel says what they
                              mean to the person reading. */}
                          <span>{t(`missing_field_${field}`)}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            </Section>
          </div>
        )}

        {tab === 'relations' && (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
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

            <Section icon={Users} title={t('section_memberships')}>
              {membershipsWithheld ? (
                /* D20: not the same as "none", and the screen must not say
                   the one when the other is true. */
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
        )}
      </div>

      <ContestantFormDialog
        isOpen={isFormOpen}
        onClose={() => setIsFormOpen(false)}
        editing={contestant}
      />

      <ConfirmDialog
        isOpen={isDeleteOpen}
        onClose={() => setIsDeleteOpen(false)}
        title={t('delete_title')}
        description={t('delete_message', { name: contestant.full_name })}
        confirmLabel={tc('delete')}
        isLoading={deleteContestant.isPending}
        onConfirm={() => {
          deleteContestant.mutate(contestant.id, {
            onSuccess: () => {
              setIsDeleteOpen(false);
              // Back to the list. Staying would leave the operator on a
              // record the default list no longer contains, with only a
              // restore button and no way back to what they were doing.
              void navigate('/contestants');
            },
          });
        }}
      />
    </PageLayout>
  );
}
