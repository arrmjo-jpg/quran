import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { ColumnDef } from '@tanstack/react-table';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import { useCountries } from '../hooks/useCountries';
import { CountryFormDialog } from '../components/CountryFormDialog';
import type { Country } from '../types';
import { Plus, Globe } from 'lucide-react';

export default function CountriesPage(): React.JSX.Element {
  const { t } = useTranslation('countries');
  const { t: tc } = useTranslation('common');
  const [isFormOpen, setIsFormOpen] = useState(false);
  const { data: countries, isLoading, refetch } = useCountries();

  const columns: ColumnDef<Country>[] = [
    {
      accessorKey: 'iso2',
      header: 'ISO2',
      cell: ({ row }) => <span className="font-bold text-slate-900 dark:text-white">{row.original.iso2}</span>,
    },
    {
      accessorKey: 'iso3',
      header: 'ISO3',
      cell: ({ row }) => <span className="font-mono text-slate-500">{row.original.iso3}</span>,
    },
    {
      accessorKey: 'phone_code',
      header: t('col_phone_code'),
      cell: ({ row }) => <span className="font-mono dir-ltr">{row.original.phone_code}</span>,
    },
    {
      accessorKey: 'name_ar',
      header: t('col_name_ar'),
      cell: ({ row }) => row.original.name_ar ?? '—',
    },
    {
      accessorKey: 'name_en',
      header: t('col_name_en'),
      cell: ({ row }) => row.original.name_en ?? '—',
    },
    {
      accessorKey: 'is_active',
      header: tc('status'),
      cell: ({ row }) => (
        <Badge variant={row.original.is_active ? 'success' : 'neutral'}>
          {row.original.is_active ? t('status_active') : t('status_inactive')}
        </Badge>
      ),
    },
  ];

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
      actions={
        <PermissionWrapper permission="countries.create">
          <Button onClick={() => setIsFormOpen(true)}>
            <Plus className="w-4 h-4" />
            <span>{t('create_button')}</span>
          </Button>
        </PermissionWrapper>
      }
    >
      <DataTable<Country>
        columns={columns}
        data={countries ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage={t('empty')}
      />

      <CountryFormDialog isOpen={isFormOpen} onClose={() => setIsFormOpen(false)} />
    </PageLayout>
  );
}
