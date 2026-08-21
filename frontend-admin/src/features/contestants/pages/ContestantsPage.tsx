import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { ColumnDef } from '@tanstack/react-table';
import { Eye, Pencil, Plus, RotateCcw, Trash2 } from 'lucide-react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { ConfirmDialog, Dialog } from '@/ui/dialog/Dialog';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import { Select } from '@/ui/input/Input';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import Spinner from '@/ui/Spinner';
import { useAllCountries } from '@/features/seasons/hooks/useLookups';
import {
  useContestant360,
  useContestants,
  useDeleteContestant,
  useRestoreContestant,
} from '../hooks/useContestants';
import { Contestant360Drawer } from '../components/Contestant360Drawer';
import { ContestantFormDialog } from '../components/ContestantFormDialog';
import type { ContestantListItem } from '../types';

const PER_PAGE = 20;

/** Below this a record is incomplete enough to be worth flagging in the list. */
const COMPLETENESS_WARNING_THRESHOLD = 100;

export default function ContestantsPage(): React.JSX.Element {
  const { t } = useTranslation('contestants');
  const { t: tc } = useTranslation('common');

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [countryId, setCountryId] = useState('');
  const [gender, setGender] = useState<'' | 'male' | 'female'>('');
  const [showDeleted, setShowDeleted] = useState(false);

  const [viewingId, setViewingId] = useState<string | null>(null);
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editing, setEditing] = useState<ContestantListItem | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ContestantListItem | null>(null);

  const { data, isLoading, isError, refetch } = useContestants({
    page,
    per_page: PER_PAGE,
    search: search || undefined,
    country_id: countryId || undefined,
    gender: gender || undefined,
    with_deleted: showDeleted || undefined,
  });

  const countries = useAllCountries();
  const deleteContestant = useDeleteContestant();
  const restoreContestant = useRestoreContestant();

  /**
   * The drawer reads the detail endpoint rather than the row it was opened
   * from. The list resource carries no national_id — deliberately, so paging
   * the table does not hand out every identity document the platform holds —
   * so a drawer fed from a row would be a drawer missing half the record.
   */
  const viewing = useContestant360(viewingId ?? '');

  const openCreate = (): void => {
    setEditing(null);
    setIsFormOpen(true);
  };

  const openEdit = (contestant: ContestantListItem): void => {
    setEditing(contestant);
    setIsFormOpen(true);
  };

  /** A filtered or reordered result set is a different list; page four of the old one would be empty. */
  const resetToFirstPage = (): void => setPage(1);

  const columns: ColumnDef<ContestantListItem>[] = [
    {
      accessorKey: 'full_name',
      header: t('col_full_name'),
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          <span className="font-semibold text-slate-900 dark:text-white">
            {row.original.full_name}
          </span>
          {row.original.is_deleted && <Badge variant="neutral">{t('badge_deleted')}</Badge>}
        </div>
      ),
    },
    {
      accessorKey: 'gender',
      header: t('col_gender'),
      cell: ({ row }) => t(`gender_${row.original.gender}`),
    },
    {
      accessorKey: 'date_of_birth',
      header: t('col_date_of_birth'),
      cell: ({ row }) => (
        <span className="font-mono text-[11px]">{row.original.date_of_birth}</span>
      ),
    },
    {
      accessorKey: 'phone_number',
      header: t('col_phone'),
      cell: ({ row }) => <span className="font-mono text-[11px]">{row.original.phone_number}</span>,
    },
    {
      id: 'completeness',
      header: t('col_completeness'),
      cell: ({ row }) => {
        const completeness = row.original.profile_completeness;

        return (
          <Badge
            variant={
              completeness.completeness_percent >= COMPLETENESS_WARNING_THRESHOLD
                ? 'success'
                : 'warning'
            }
          >
            {`${completeness.completeness_percent}%`}
          </Badge>
        );
      },
    },
    {
      id: 'actions',
      header: t('col_actions'),
      cell: ({ row }) => {
        const contestant = row.original;

        // A deleted record offers exactly one action: bring it back. Editing
        // a row that is gone would be offering work the server refuses.
        if (contestant.is_deleted) {
          return (
            <PermissionWrapper permission="contestants.restore">
              <Button
                size="sm"
                variant="outline"
                isLoading={
                  restoreContestant.isPending && restoreContestant.variables === contestant.id
                }
                onClick={() => restoreContestant.mutate(contestant.id)}
              >
                <RotateCcw className="w-3.5 h-3.5" />
                <span>{t('action_restore')}</span>
              </Button>
            </PermissionWrapper>
          );
        }

        return (
          <div className="flex flex-wrap items-center gap-2">
            <Button size="sm" variant="ghost" onClick={() => setViewingId(contestant.id)}>
              <Eye className="w-4 h-4 text-brand-600" />
              <span>{t('view_full_profile')}</span>
            </Button>

            <PermissionWrapper permission="contestants.update">
              <Button size="sm" variant="outline" onClick={() => openEdit(contestant)}>
                <Pencil className="w-3.5 h-3.5" />
                <span>{tc('edit')}</span>
              </Button>
            </PermissionWrapper>

            <PermissionWrapper permission="contestants.delete">
              <Button size="sm" variant="danger" onClick={() => setDeleteTarget(contestant)}>
                <Trash2 className="w-3.5 h-3.5" />
                <span>{tc('delete')}</span>
              </Button>
            </PermissionWrapper>
          </div>
        );
      },
    },
  ];

  if (isError) {
    return (
      <PageLayout title={t('title')} subtitle={t('subtitle')}>
        <ErrorState onRetry={() => void refetch()} />
      </PageLayout>
    );
  }

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
      actions={
        <div className="flex flex-wrap items-center gap-3">
          <div className="w-44">
            <Select
              value={countryId}
              onChange={(e) => {
                setCountryId(e.target.value);
                resetToFirstPage();
              }}
              options={[
                { value: '', label: t('filter_all_countries') },
                ...(countries.data ?? []).map((country) => ({
                  value: country.id,
                  label: country.name,
                })),
              ]}
            />
          </div>

          <div className="w-36">
            <Select
              value={gender}
              onChange={(e) => {
                setGender(e.target.value as '' | 'male' | 'female');
                resetToFirstPage();
              }}
              options={[
                { value: '', label: t('filter_all_genders') },
                { value: 'male', label: t('gender_male') },
                { value: 'female', label: t('gender_female') },
              ]}
            />
          </div>

          <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300 cursor-pointer">
            <input
              type="checkbox"
              checked={showDeleted}
              onChange={(e) => {
                setShowDeleted(e.target.checked);
                resetToFirstPage();
              }}
              className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
            />
            <span>{t('show_deleted')}</span>
          </label>

          <PermissionWrapper permission="contestants.create">
            <Button onClick={openCreate}>
              <Plus className="w-4 h-4" />
              <span>{t('action_create')}</span>
            </Button>
          </PermissionWrapper>
        </div>
      }
    >
      <DataTable<ContestantListItem>
        columns={columns}
        data={data?.contestants ?? []}
        loading={isLoading}
        searchable
        onSearchChange={(query) => {
          setSearch(query);
          resetToFirstPage();
        }}
        pagination={{ pageIndex: page - 1, pageSize: PER_PAGE, total: data?.total ?? 0 }}
        onPageChange={(index) => setPage(index + 1)}
        onRefresh={() => void refetch()}
        emptyMessage={t('empty')}
      />

      <ContestantFormDialog
        isOpen={isFormOpen}
        onClose={() => setIsFormOpen(false)}
        editing={editing}
      />

      {/*
        The drawer renders nothing without a contestant, so the fetch it
        depends on would otherwise be an invisible pause between clicking
        the row and the panel appearing. These two cover that gap; neither
        touches the drawer, whose contents belong to Identity 360.
      */}
      <Dialog
        isOpen={viewingId !== null && viewing.data === undefined}
        onClose={() => setViewingId(null)}
        title={t('view_full_profile')}
      >
        {viewing.isError ? (
          <ErrorState onRetry={() => void viewing.refetch()} />
        ) : (
          <div className="flex justify-center py-10">
            <Spinner />
          </div>
        )}
      </Dialog>

      <Contestant360Drawer
        contestant={viewing.data ?? null}
        isOpen={viewingId !== null && viewing.data !== undefined}
        onClose={() => setViewingId(null)}
      />

      <ConfirmDialog
        isOpen={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        title={t('delete_title')}
        description={t('delete_message', { name: deleteTarget?.full_name ?? '' })}
        confirmLabel={tc('delete')}
        isLoading={deleteContestant.isPending}
        onConfirm={() => {
          if (deleteTarget === null) return;
          deleteContestant.mutate(deleteTarget.id, { onSuccess: () => setDeleteTarget(null) });
        }}
      />
    </PageLayout>
  );
}
