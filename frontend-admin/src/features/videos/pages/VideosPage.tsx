import React from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import { videoService, type VideoItem } from '../api/video.service';
import { RefreshCw, Play, FileCode, CheckCircle2 } from 'lucide-react';
import { toast } from 'sonner';

export default function VideosPage(): React.JSX.Element {
  const queryClient = useQueryClient();

  const { data: videos, isLoading, refetch } = useQuery({
    queryKey: ['videos'],
    queryFn: () => videoService.getVideos(),
  });

  const reprocessMutation = useMutation({
    mutationFn: (id: string) => videoService.reprocessVideo(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['videos'] });
      toast.success('تم إرسال مهمة إعادة معالجة FFmpeg HLS في الخلفية بنجاح');
    },
  });

  const columns: ColumnDef<VideoItem>[] = [
    {
      accessorKey: 'id',
      header: 'معرف الفيديو المعالج',
      cell: ({ row }) => <span className="font-mono text-[10px] text-slate-400">{row.original.id}</span>,
    },
    {
      accessorKey: 'application_id',
      header: 'رقم الطلب',
      cell: ({ row }) => <span className="font-semibold text-slate-900 dark:text-white">{row.original.application_id}</span>,
    },
    {
      accessorKey: 'media_id',
      header: 'معرف ملف R2',
      cell: ({ row }) => <span className="font-mono text-slate-500">{row.original.media_id}</span>,
    },
    {
      accessorKey: 'status',
      header: 'حالة معالجة FFmpeg HLS',
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'ready' ? 'success' : 'warning'}>
          {row.original.status === 'ready' ? 'جاهز (Ready HLS 1080p)' : row.original.status}
        </Badge>
      ),
    },
    {
      id: 'hls_link',
      header: 'رابط Playlist m3u8',
      cell: ({ row }) => (
        <span className="font-mono text-[10px] text-brand-600 dark:text-brand-400 truncate max-w-[200px] block">
          {row.original.hls_url ?? 'https://cdn.quran.test/hls/sample.m3u8'}
        </span>
      ),
    },
    {
      id: 'actions',
      header: 'إعادة المعالجة',
      cell: ({ row }) => (
        <Button
          size="sm"
          variant="outline"
          isLoading={reprocessMutation.isPending}
          onClick={() => reprocessMutation.mutate(row.original.id)}
        >
          <RefreshCw className="w-3.5 h-3.5" />
          <span>إعادة المعالجة FFmpeg</span>
        </Button>
      ),
    },
  ];

  return (
    <PageLayout
      title="مركز معالجة وتحويل الفيديوهات (Video Processing Center)"
      subtitle="تتبع مهام تحويل مقاطع التلاوة لترميز HLS 1080p وتقسيم الشرائح م3u8 عبر FFmpeg Workers"
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: 'الفيديوهات' }]}
    >
      <DataTable<VideoItem>
        columns={columns}
        data={videos ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage="لا توجد فيديوهات قيد المعالجة حالياً."
      />
    </PageLayout>
  );
}
