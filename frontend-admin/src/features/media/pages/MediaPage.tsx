import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import { Dialog, ConfirmDialog } from '@/ui/dialog/Dialog';
import { ImagePreview } from '@/ui/media/VideoPlayer';
import { mediaService, type MediaItem } from '../api/media.service';
import { Upload, Copy, Eye, Trash2, HardDrive } from 'lucide-react';
import { toast } from 'sonner';

export default function MediaPage(): React.JSX.Element {
  const { t } = useTranslation('media');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const [selectedFile, setSelectedFile] = useState<MediaItem | null>(null);
  const [deleteFile, setDeleteFile] = useState<MediaItem | null>(null);

  const { data: mediaFiles, isLoading, refetch } = useQuery({
    queryKey: ['media'],
    queryFn: () => mediaService.getMedia(),
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => mediaService.uploadFile(file),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['media'] });
      toast.success(t('upload_success'));
    },
    onError: () => toast.error(t('upload_error')),
  });

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) uploadMutation.mutate(file);
  };

  const handleCopyLink = (url?: string) => {
    if (url) {
      navigator.clipboard.writeText(url);
      toast.success(t('link_copied'));
    }
  };

  const columns: ColumnDef<MediaItem>[] = [
    {
      accessorKey: 'filename',
      header: t('col_filename'),
      cell: ({ row }) => (
        <div>
          <span className="font-semibold text-slate-900 dark:text-white block">{row.original.filename ?? row.original.id}</span>
          <span className="font-mono text-[10px] text-slate-400">{row.original.id}</span>
        </div>
      ),
    },
    {
      accessorKey: 'mime_type',
      header: t('col_mime'),
      cell: ({ row }) => <Badge variant="info">{row.original.mime_type ?? 'video/mp4'}</Badge>,
    },
    {
      accessorKey: 'size_bytes',
      header: t('col_size'),
      cell: ({ row }) => (
        <span className="font-mono">
          {t('size_mb', { size: (row.original.size_bytes / (1024 * 1024)).toFixed(2) })}
        </span>
      ),
    },
    {
      accessorKey: 'status',
      header: t('col_storage'),
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'ready' ? 'success' : 'warning'}>
          {row.original.status ?? t('status_ready')}
        </Badge>
      ),
    },
    {
      id: 'actions',
      header: tc('actions'),
      cell: ({ row }) => (
        <div className="flex items-center gap-1.5">
          <Button size="sm" variant="ghost" onClick={() => setSelectedFile(row.original)}>
            <Eye className="w-3.5 h-3.5 text-brand-600" />
          </Button>
          <Button size="sm" variant="ghost" onClick={() => handleCopyLink(row.original.url)}>
            <Copy className="w-3.5 h-3.5 text-slate-600" />
          </Button>
          <Button size="sm" variant="ghost" onClick={() => setDeleteFile(row.original)}>
            <Trash2 className="w-3.5 h-3.5 text-rose-600" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
      actions={
        <label className="cursor-pointer inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold px-4 py-2 rounded-xl transition-colors">
          <Upload className="w-4 h-4" />
          <span>{t('upload_button')}</span>
          <input type="file" onChange={handleFileChange} className="hidden" />
        </label>
      }
    >
      <DataTable<MediaItem>
        columns={columns}
        data={mediaFiles ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage={t('empty')}
      />

      {selectedFile && (
        <Dialog isOpen={Boolean(selectedFile)} onClose={() => setSelectedFile(null)} title={t('preview_title', { name: selectedFile.filename })}>
          <div className="space-y-4">
            <ImagePreview src={selectedFile.url ?? 'https://via.placeholder.com/600x400'} />
            <div className="flex justify-end">
              <Button size="sm" variant="secondary" onClick={() => setSelectedFile(null)}>{t('close_preview')}</Button>
            </div>
          </div>
        </Dialog>
      )}

      {deleteFile && (
        <ConfirmDialog
          isOpen={Boolean(deleteFile)}
          onClose={() => setDeleteFile(null)}
          title={t('delete_title')}
          description={t('delete_body', { name: deleteFile.filename })}
          confirmLabel={t('delete_confirm')}
          onConfirm={() => {
            toast.success(t('delete_success'));
            setDeleteFile(null);
          }}
        />
      )}
    </PageLayout>
  );
}
