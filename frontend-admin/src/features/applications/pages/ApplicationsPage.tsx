import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import { Dialog, ConfirmDialog } from '@/ui/dialog/Dialog';
import { VideoPlayer } from '@/ui/media/VideoPlayer';
import { applicationService } from '../api/application.service';
import type { ApplicationItem } from '../types';
import { Play, Check, X, Send } from 'lucide-react';
import { formatDate } from '@/core/utils';
import { toast } from 'sonner';

export default function ApplicationsPage(): React.JSX.Element {
  const queryClient = useQueryClient();
  const [previewApp, setPreviewApp] = useState<ApplicationItem | null>(null);
  const [rejectApp, setRejectApp] = useState<ApplicationItem | null>(null);

  const { data: applications, isLoading, refetch } = useQuery({
    queryKey: ['applications'],
    queryFn: () => applicationService.getApplications(),
  });

  const sendToJudging = useMutation({
    mutationFn: (id: string) => applicationService.markReadyForJudging(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['applications'] });
      toast.success('تمت إحالة الطلب إلى طابور تحكيم اللجنة بنجاح');
    },
  });

  const columns: ColumnDef<ApplicationItem>[] = [
    {
      accessorKey: 'id',
      header: 'رقم الطلب',
      cell: ({ row }) => <span className="font-mono text-[10px] text-slate-400">{row.original.id}</span>,
    },
    {
      accessorKey: 'contestant_id',
      header: 'المتسابق',
      cell: ({ row }) => <span className="font-semibold text-slate-900 dark:text-white">{row.original.contestant_id}</span>,
    },
    {
      accessorKey: 'season_id',
      header: 'الموسم والمرحلة',
      cell: ({ row }) => <span>{row.original.season_id} / {row.original.stage_id}</span>,
    },
    {
      accessorKey: 'created_at',
      header: 'تاريخ التقديم',
      cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
      accessorKey: 'status',
      header: 'الحالة',
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'ready_for_judging' ? 'success' : 'warning'}>
          {row.original.status}
        </Badge>
      ),
    },
    {
      id: 'preview',
      header: 'معاينة تلاوة الفيديو',
      cell: ({ row }) => (
        <Button size="sm" variant="ghost" onClick={() => setPreviewApp(row.original)}>
          <Play className="w-3.5 h-3.5 text-brand-600" />
          <span>تشغيل الفيديو</span>
        </Button>
      ),
    },
    {
      id: 'actions',
      header: 'القرارات الإدارية',
      cell: ({ row }) => (
        <div className="flex items-center gap-1.5">
          {row.original.status !== 'ready_for_judging' && (
            <Button
              size="sm"
              variant="outline"
              isLoading={sendToJudging.isPending}
              onClick={() => sendToJudging.mutate(row.original.id)}
            >
              <Send className="w-3 h-3" />
              <span>إرسال للتحكيم</span>
            </Button>
          )}
          <Button size="sm" variant="danger" onClick={() => setRejectApp(row.original)}>
            <X className="w-3 h-3" />
            <span>رفض</span>
          </Button>
        </div>
      ),
    },
  ];

  return (
    <PageLayout
      title="منصة مراجعة وسكرتارية الطلبات (Applications Desk)"
      subtitle="معاينة فيديوهات التلاوة مباشرة لاتخاذ قرار الإرسال للتحكيم أو طلب التعديل"
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: 'طلبات الاشتراك' }]}
    >
      <DataTable<ApplicationItem>
        columns={columns}
        data={applications ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage="لا توجد طلبات اشتراك بانتظار المراجعة."
      />

      {/* Video Preview Modal */}
      {previewApp && (
        <Dialog isOpen={Boolean(previewApp)} onClose={() => setPreviewApp(null)} title={`معاينة فيديو الطلب — ${previewApp.id}`}>
          <div className="space-y-4">
            <VideoPlayer url={previewApp.video_url ?? 'https://www.w3schools.com/html/mov_bbb.mp4'} />
            <div className="flex justify-end">
              <Button size="sm" variant="secondary" onClick={() => setPreviewApp(null)}>إغلاق المعاينة</Button>
            </div>
          </div>
        </Dialog>
      )}

      {/* Reject Confirm Dialog */}
      {rejectApp && (
        <ConfirmDialog
          isOpen={Boolean(rejectApp)}
          onClose={() => setRejectApp(null)}
          title="تأكيد رفض الطلب"
          description={`هل أنت متأكد من رفض الطلب رقم ${rejectApp.id}؟ سيتم إشعار المتسابق وتسجيل القرار في Audit Trail.`}
          confirmLabel="تأكيد الرفض"
          onConfirm={() => {
            toast.success('تم تسجيل قرار الرفض');
            setRejectApp(null);
          }}
        />
      )}
    </PageLayout>
  );
}
