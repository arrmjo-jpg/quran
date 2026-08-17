import React from 'react';
import { useTranslation } from 'react-i18next';
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
  const { t } = useTranslation('videos');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();

  const { data: videos, isLoading, refetch } = useQuery({
    queryKey: ['videos'],
    queryFn: () => videoService.getVideos(),
  });

  const reprocessMutation = useMutation({
    mutationFn: (id: string) => videoService.reprocessVideo(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['videos'] });
      toast.success(t('reprocess_success'));
    },
  });

  const columns: ColumnDef<VideoItem>[] = [
    {
      accessorKey: 'id',
      header: t('col_id'),
      cell: ({ row }) => <span className="font-mono text-[10px] text-slate-400">{row.original.id}</span>,
    },
    {
      accessorKey: 'application_id',
      header: t('col_application'),
      cell: ({ row }) => <span className="font-semibold text-slate-900 dark:text-white">{row.original.application_id}</span>,
    },
    {
      accessorKey: 'media_id',
      header: t('col_media'),
      cell: ({ row }) => <span className="font-mono text-slate-500">{row.original.media_id}</span>,
    },
    {
      accessorKey: 'status',
      header: t('col_status'),
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'ready' ? 'success' : 'warning'}>
          {row.original.status === 'ready' ? t('status_ready') : row.original.status}
        </Badge>
      ),
    },
    {
      id: 'hls_link',
      header: t('col_hls'),
      cell: ({ row }) => (
        <span className="font-mono text-[10px] text-brand-600 dark:text-brand-400 truncate max-w-[200px] block">
          {row.original.hls_url ?? 'https://cdn.quran.test/hls/sample.m3u8'}
        </span>
      ),
    },
    {
      id: 'actions',
      header: t('col_actions'),
      cell: ({ row }) => (
        <Button
          size="sm"
          variant="outline"
          isLoading={reprocessMutation.isPending}
          onClick={() => reprocessMutation.mutate(row.original.id)}
        >
          <RefreshCw className="w-3.5 h-3.5" />
          <span>{t('reprocess_button')}</span>
        </Button>
      ),
    },
  ];

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
    >
      <DataTable<VideoItem>
        columns={columns}
        data={videos ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage={t('empty')}
      />
    </PageLayout>
  );
}
