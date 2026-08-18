import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Button from '@/ui/Button';
import { useContestants } from '../hooks/useContestants';
import { Contestant360Drawer } from '../components/Contestant360Drawer';
import type { ContestantProfile } from '../types';
import { Eye } from 'lucide-react';

export default function ContestantsPage(): React.JSX.Element {
  const [query, setQuery] = useState('');
  const [selectedContestant, setSelectedContestant] = useState<ContestantProfile | null>(null);

  const { data: contestants, isLoading, refetch } = useContestants({ query });

  const columns: ColumnDef<ContestantProfile>[] = [
    {
      accessorKey: 'id',
      header: 'معرف المتسابق',
      cell: ({ row }) => <span className="font-mono text-[10px] text-slate-400">{row.original.id}</span>,
    },
    {
      accessorKey: 'full_name',
      header: 'الاسم الكامل',
      cell: ({ row }) => <span className="font-semibold text-slate-900 dark:text-white">{row.original.full_name}</span>,
    },
    {
      accessorKey: 'phone_number',
      header: 'رقم الهاتف',
      cell: ({ row }) => row.original.phone_number ?? '—',
    },
    {
      id: 'actions',
      header: 'عرض الملف 360°',
      cell: ({ row }) => (
        <Button size="sm" variant="ghost" onClick={() => setSelectedContestant(row.original)}>
          <Eye className="w-4 h-4 text-brand-600" />
          <span>الملف الكامل</span>
        </Button>
      ),
    },
  ];

  return (
    <PageLayout
      title="إدارة وشاشات المتسابقين (360° Profile Hub)"
      subtitle="البحث المتخصص باسم المتسابق، رقم الطلب، الهاتف، أو الدولة ومعاينة الملف الكامل"
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: 'المتسابقون' }]}
    >
      <DataTable<ContestantProfile>
        columns={columns}
        data={contestants ?? []}
        loading={isLoading}
        onSearchChange={(q) => setQuery(q)}
        onRefresh={refetch}
        exportable
        emptyMessage="لم يتم العثور على أي متسابق مطابق للبحث."
      />

      <Contestant360Drawer
        contestant={selectedContestant}
        isOpen={Boolean(selectedContestant)}
        onClose={() => setSelectedContestant(null)}
      />
    </PageLayout>
  );
}
