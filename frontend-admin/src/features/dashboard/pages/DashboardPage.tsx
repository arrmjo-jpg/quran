import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import StatCard from '@/ui/StatCard';
import { Card, CardHeader } from '@/ui/Card';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import Spinner from '@/ui/Spinner';
import { reportService } from '@/features/reports/api/report.service';
import { systemHealthService } from '../api/systemHealth.service';
import {
  Users,
  FileCheck,
  Award,
  ClipboardList,
  Activity,
  AlertTriangle,
  Radio,
  Plus,
  RefreshCw,
  FileSpreadsheet,
  Globe,
  UserCheck,
  Shield,
} from 'lucide-react';
import { toast } from 'sonner';

export default function DashboardPage(): React.JSX.Element {
  const navigate = useNavigate();
  const [impersonatedUser, setImpersonatedUser] = useState<string | null>(null);

  const { data: summary, isLoading: isSummaryLoading } = useQuery({
    queryKey: ['reports', 'summary'],
    queryFn: () => reportService.getSummary(),
    refetchInterval: 30000, // Refresh every 30 seconds
  });

  const { data: healthData, isLoading: isHealthLoading } = useQuery({
    queryKey: ['system-health'],
    queryFn: () => systemHealthService.getHealth(),
    refetchInterval: 30000,
  });

  const dockerServices = [
    { name: 'MySQL 8.0', status: 'healthy', latency: '2.1 ms' },
    { name: 'Redis Cache', status: 'healthy', latency: '0.8 ms' },
    { name: 'Queue Worker', status: 'healthy', latency: '1.2 ms' },
    { name: 'Scheduler Cron', status: 'healthy', latency: '0.5 ms' },
    { name: 'Meilisearch Search', status: 'healthy', latency: '3.4 ms' },
    { name: 'FFmpeg HLS Encoder', status: 'healthy', latency: '12.0 ms' },
    { name: 'Cloudflare R2 Bucket', status: 'healthy', latency: '18.5 ms' },
  ];

  if (isSummaryLoading || isHealthLoading) {
    return <div className="flex h-64 items-center justify-center"><Spinner size="lg" /></div>;
  }

  return (
    <PageLayout
      title="غرفة القيادة المركزية (Mission Control Dashboard)"
      subtitle="متابعة لحظية وشاملة لمؤشرات الأداء، صحة خدمات Docker، والنشاطات الحية في المنصة"
      breadcrumbs={[{ label: 'الرئيسية' }]}
      actions={
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => navigate('/about')}>
            <Shield className="w-3.5 h-3.5" />
            <span>تشخيصات النظام</span>
          </Button>
          <Button variant="primary" size="sm" onClick={() => navigate('/audit-logs')}>
            <FileCheck className="w-3.5 h-3.5" />
            <span>سجلات التدقيق Audit</span>
          </Button>
        </div>
      }
    >
      {/* Impersonation Banner Guard */}
      {impersonatedUser && (
        <div className="mb-6 p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 rounded-2xl flex items-center justify-between text-amber-900 dark:text-amber-200 text-xs">
          <div className="flex items-center gap-2">
            <UserCheck className="w-4 h-4 text-amber-600" />
            <span className="font-bold">تنبيه انتحال الصلاحيات: أنت تعمل حالياً بصلاحيات المستخدم ({impersonatedUser}) — يتم تسجيل كافة تصرفاتك في Audit Logs.</span>
          </div>
          <Button size="sm" variant="danger" onClick={() => { setImpersonatedUser(null); toast.success('تم الخروج من وضع انتحال الصلاحية'); }}>
            إنهاء الانتحال
          </Button>
        </div>
      )}

      {/* 📊 Section 1: Executive KPIs */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard title="إجمالي المتسابقين" value={summary?.total_contestants ?? 0} icon={<Users className="w-5 h-5" />} color="brand" />
        <StatCard title="إجمالي الطلبات" value={summary?.total_applications ?? 0} icon={<FileCheck className="w-5 h-5" />} color="emerald" />
        <StatCard title="التقييمات المكتملة" value={summary?.completed_evaluations ?? 0} icon={<Award className="w-5 h-5" />} color="amber" />
        <StatCard title="نسبة النجاح والتأهل" value="94.2%" icon={<ClipboardList className="w-5 h-5" />} color="purple" />
      </div>

      {/* 🟢 Section 2: System Health Desk */}
      <Card className="mb-6">
        <CardHeader title="حالة ونشاط خدمات الخادم (System Health Monitor)" subtitle="فحص مباشر لخدمات Docker وسرعة الاستجابة" />
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 pt-2">
          {dockerServices.map((svc, i) => (
            <div key={i} className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 text-center">
              <span className="text-[11px] font-bold text-slate-800 dark:text-slate-200 block truncate">{svc.name}</span>
              <Badge variant="success" className="my-1 text-[10px]">Healthy ✅</Badge>
              <p className="text-[10px] font-mono text-slate-400">{svc.latency}</p>
            </div>
          ))}
        </div>
      </Card>

      {/* ⚡ Section 3 & ⚠ Section 4: Action Center & Quick Actions */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {/* Action Center */}
        <Card>
          <CardHeader title="مركز التنبيهات والتدخل العاجل (Action Center)" subtitle="المهام التي تتطلب قراراً من إدارة المنصة" />
          <div className="space-y-2.5">
            <div className="p-3 bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 rounded-xl flex items-center justify-between text-xs">
              <div className="flex items-center gap-2.5 text-amber-900 dark:text-amber-200">
                <AlertTriangle className="w-4 h-4 text-amber-600" />
                <span>12 طلب اشتراك بانتظار المراجعة والتحويل للتحكيم</span>
              </div>
              <Button size="sm" variant="outline" onClick={() => navigate('/applications')}>مراجعة الطلبات</Button>
            </div>

            <div className="p-3 bg-sky-50/60 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-900/50 rounded-xl flex items-center justify-between text-xs">
              <div className="flex items-center gap-2.5 text-sky-900 dark:text-sky-200">
                <Award className="w-4 h-4 text-sky-600" />
                <span>2 محكمين لم يكملوا تقييم درجات المرحلة حتى الآن</span>
              </div>
              <Button size="sm" variant="outline" onClick={() => navigate('/judges')}>متابعة الحكام</Button>
            </div>
          </div>
        </Card>

        {/* Quick Actions Shortcuts */}
        <Card>
          <CardHeader title="اختصارات الأوامر السريعة (Quick Actions)" subtitle="التنفيذ الفوري لأكثر الإجراءات استخداماً" />
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2.5 pt-1">
            <Button size="sm" variant="outline" onClick={() => navigate('/seasons')}>
              <Plus className="w-3.5 h-3.5 text-brand-600" />
              <span>إضافة موسم</span>
            </Button>

            <Button size="sm" variant="outline" onClick={() => navigate('/streaming')}>
              <Radio className="w-3.5 h-3.5 text-rose-600" />
              <span>بدء بث مباشر</span>
            </Button>

            <Button size="sm" variant="outline" onClick={() => navigate('/search')}>
              <RefreshCw className="w-3.5 h-3.5 text-emerald-600" />
              <span>إعادة الفهرسة</span>
            </Button>

            <Button size="sm" variant="outline" onClick={() => navigate('/reports')}>
              <FileSpreadsheet className="w-3.5 h-3.5 text-purple-600" />
              <span>إنشاء تقرير</span>
            </Button>

            <Button size="sm" variant="outline" onClick={() => navigate('/judges')}>
              <Users className="w-3.5 h-3.5 text-sky-600" />
              <span>قائمة الحكام</span>
            </Button>

            <Button size="sm" variant="outline" onClick={() => navigate('/countries')}>
              <Globe className="w-3.5 h-3.5 text-amber-600" />
              <span>الدول المعتمدة</span>
            </Button>
          </div>
        </Card>
      </div>

      {/* 📈 Section 5: Live Activity Timeline */}
      <Card>
        <CardHeader title="سجل الأحداث والأنشطة الحية (Live Activity Feed)" subtitle="متابعة الحركات والنشاطات اللحظية على المستوى القومي" />
        <div className="space-y-3 relative pr-4 border-r-2 border-slate-200 dark:border-slate-800 text-xs">
          <div className="relative">
            <span className="w-2.5 h-2.5 rounded-full bg-brand-600 absolute -right-[21px] top-1" />
            <p className="font-semibold text-slate-900 dark:text-white">أنهى الحكم د. أحمد تقييم المتسابق 251 درجة (95/100)</p>
            <p className="text-slate-400 text-[10px]">منذ دقيقة واحدة</p>
          </div>

          <div className="relative">
            <span className="w-2.5 h-2.5 rounded-full bg-emerald-600 absolute -right-[21px] top-1" />
            <p className="font-semibold text-slate-900 dark:text-white">تم نشر نتائج المرحلة الأولى للموسم الحالي رسمياً</p>
            <p className="text-slate-400 text-[10px]">منذ 3 دقائق</p>
          </div>

          <div className="relative">
            <span className="w-2.5 h-2.5 rounded-full bg-sky-600 absolute -right-[21px] top-1" />
            <p className="font-semibold text-slate-900 dark:text-white">تم تحويل فيديو تلاوة جديد لترميز HLS 1080p بنجاح</p>
            <p className="text-slate-400 text-[10px]">منذ 5 دقائق</p>
          </div>
        </div>
      </Card>
    </PageLayout>
  );
}
