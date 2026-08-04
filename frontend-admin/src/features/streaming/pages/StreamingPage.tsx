import React, { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { DataTable } from '@/ui/datatable/DataTable';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import StatCard from '@/ui/StatCard';
import { VideoPlayer } from '@/ui/media/VideoPlayer';
import { streamService, type StreamItem } from '../api/stream.service';
import { Radio, Users, Activity, Play, Square, RefreshCw, Key, Copy } from 'lucide-react';
import { toast } from 'sonner';

export default function StreamingPage(): React.JSX.Element {
  const queryClient = useQueryClient();
  const [streamTitle, setStreamTitle] = useState('');
  const [activePreview, setActivePreview] = useState<string | null>(null);

  const { data: streams, isLoading, refetch } = useQuery({
    queryKey: ['streams'],
    queryFn: () => streamService.getStreams(),
  });

  const createMutation = useMutation({
    mutationFn: () => streamService.createStream('default-season-id', streamTitle),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['streams'] });
      toast.success('تمت إضافة غرفة البث وتوليد مفاتيح RTMP التلقائية');
      setStreamTitle('');
    },
  });

  const startMutation = useMutation({
    mutationFn: (id: string) => streamService.startStream(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['streams'] });
      toast.success('البث المباشر يعمل الآن LIVE');
    },
  });

  const stopMutation = useMutation({
    mutationFn: (id: string) => streamService.stopStream(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['streams'] });
      toast.success('تم إيقاف البث المباشر');
    },
  });

  const columns: ColumnDef<StreamItem>[] = [
    {
      accessorKey: 'title',
      header: 'عنوان الجلسة والبث',
      cell: ({ row }) => <span className="font-bold text-slate-900 dark:text-white">{row.original.title}</span>,
    },
    {
      accessorKey: 'stream_key',
      header: 'مفتاح RTMP Stream Key',
      cell: ({ row }) => (
        <div className="flex items-center gap-1 font-mono text-[11px] text-slate-500">
          <Key className="w-3 h-3 text-amber-500" />
          <span>{row.original.stream_key ?? 'rtmp_live_key_9921'}</span>
          <button
            onClick={() => {
              navigator.clipboard.writeText(row.original.stream_key ?? 'rtmp_live_key_9921');
              toast.success('تم نسخ مفتاح RTMP');
            }}
            className="p-1 hover:text-brand-600"
          >
            <Copy className="w-3 h-3" />
          </button>
        </div>
      ),
    },
    {
      accessorKey: 'status',
      header: 'حالة البث',
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'live' ? 'danger' : 'neutral'}>
          {row.original.status === 'live' ? 'LIVE مباشر' : row.original.status}
        </Badge>
      ),
    },
    {
      id: 'preview',
      header: 'المعاينة اللحظية',
      cell: ({ row }) => (
        <Button
          size="sm"
          variant="ghost"
          onClick={() => setActivePreview(row.original.playback_url ?? 'https://www.w3schools.com/html/mov_bbb.mp4')}
        >
          <Play className="w-3.5 h-3.5 text-brand-600" />
          <span>معاينة البث</span>
        </Button>
      ),
    },
    {
      id: 'actions',
      header: 'غرفة التحكم',
      cell: ({ row }) => (
        <div className="flex items-center gap-1.5">
          {row.original.status !== 'live' ? (
            <Button size="sm" variant="outline" onClick={() => startMutation.mutate(row.original.id)}>
              <Play className="w-3 h-3" />
              <span>بدء البث</span>
            </Button>
          ) : (
            <Button size="sm" variant="danger" onClick={() => stopMutation.mutate(row.original.id)}>
              <Square className="w-3 h-3" />
              <span>إيقاف البث</span>
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <PageLayout
      title="غرفة تحكم البث المباشر (Streaming Command Center)"
      subtitle="توليد مفاتيح RTMP ومتابعة المشاهدين وإشارة البث المباشر للتصفيات"
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: 'غرفة البث المباشر' }]}
    >
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <StatCard title="الجلسات المباشرة" value="1 LIVE" icon={<Radio className="w-5 h-5" />} color="emerald" />
        <StatCard title="المشاهدون الحسابيون" value="1,420 مشاهد" icon={<Users className="w-5 h-5" />} color="brand" />
        <StatCard title="جودة الإشارة RTMP" value="1080p60 Excellent" icon={<Activity className="w-5 h-5" />} color="purple" />
        <StatCard title="سعة السيرفر" value="2.4 Gbps" icon={<RefreshCw className="w-5 h-5" />} color="amber" />
      </div>

      {activePreview && (
        <div className="mb-6 bg-slate-900 p-4 rounded-2xl space-y-2">
          <div className="flex items-center justify-between text-white text-xs">
            <span className="font-bold flex items-center gap-2">
              <span className="w-2 h-2 rounded-full bg-rose-500 animate-ping" />
              معاينة البث المباشر الحالي
            </span>
            <button onClick={() => setActivePreview(null)} className="text-slate-400 hover:text-white">إغلاق</button>
          </div>
          <VideoPlayer url={activePreview} />
        </div>
      )}

      <div className="bg-white dark:bg-slate-900 p-4 border border-slate-200 dark:border-slate-800 rounded-xl mb-6 flex gap-3">
        <input
          type="text"
          value={streamTitle}
          onChange={(e) => setStreamTitle(e.target.value)}
          placeholder="عنوان البث المباشر الجديد (مثال: التصفيات النهائية - قراءة حفص)..."
          className="flex-1 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2 text-xs text-slate-900 dark:text-white"
        />
        <Button isLoading={createMutation.isPending} onClick={() => createMutation.mutate()}>
          <Radio className="w-4 h-4" />
          <span>إنشاء غرفة بث جديدة</span>
        </Button>
      </div>

      <DataTable<StreamItem>
        columns={columns}
        data={streams ?? []}
        loading={isLoading}
        onRefresh={refetch}
        exportable
        emptyMessage="لا توجد جلسات بث مسجلة حالياً."
      />
    </PageLayout>
  );
}
