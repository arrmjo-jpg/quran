import React, { useState } from 'react';
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
      header: 'مفتاح الهاتف',
      cell: ({ row }) => <span className="font-mono dir-ltr">{row.original.phone_code}</span>,
    },
    {
      accessorKey: 'name_ar',
      header: 'الاسم بالعربية',
      cell: ({ row }) => row.original.name_ar ?? '—',
    },
    {
      accessorKey: 'name_en',
      header: 'الاسم بالإنجليزية',
      cell: ({ row }) => row.original.name_en ?? '—',
    },
    {
      accessorKey: 'is_active',
      header: 'الحالة',
      cell: ({ row }) => (
        <Badge variant={row.original.is_active ? 'success' : 'neutral'}>
          {row.original.is_active ? 'نشط' : 'معطل'}
        </Badge>
      ),
    },
  ];

  return (
    <PageLayout
      title="إدارة الدول المعتمدة"
      subtitle="إدارة رموز الدول ISO ومفاتيح الاتصال الهاتفي للمشاركين"
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: 'الدول المعتمدة' }]}
      actions={
        <PermissionWrapper role="admin">
          <Button onClick={() => setIsFormOpen(true)}>
            <Plus className="w-4 h-4" />
            <span>إضافة دولة جديدة</span>
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
        emptyMessage="لا توجد دول مسجلة حتى الآن."
      />

      <CountryFormDialog isOpen={isFormOpen} onClose={() => setIsFormOpen(false)} />
    </PageLayout>
  );
}
