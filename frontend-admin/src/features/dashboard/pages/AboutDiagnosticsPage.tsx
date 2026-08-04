import React from 'react';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { Card, CardHeader } from '@/ui/Card';
import Badge from '@/ui/Badge';
import { Server, Code2, Database, Cpu, Terminal } from 'lucide-react';

export default function AboutDiagnosticsPage(): React.JSX.Element {
  const versions = [
    { label: 'إصدار المنصة (Platform Release)', value: 'v1.0.0-rc1', status: 'Official Baseline' },
    { label: 'رمز التحديث (Git Commit)', value: '7f9a2bc (main)', status: 'Clean Build' },
    { label: 'تاريخ التجميع (Build Date)', value: '2026-08-03T18:30:00Z', status: 'Production Artifact' },
    { label: 'إصدار Laravel Backend', value: 'v11.x Monolith', status: 'Healthy' },
    { label: 'إصدار PHP', value: 'PHP 8.3.x (CLI)', status: 'JIT Enabled' },
    { label: 'إصدار React Admin', value: 'v18.3.1 Vite 5', status: 'Production Bundle' },
    { label: 'إصدار محرك البيانات (MySQL)', value: 'MySQL 8.0 Enterprise', status: 'Cluster Master' },
    { label: 'إصدار محول الميديا (FFmpeg)', value: 'FFmpeg 6.1 HLS', status: 'HW Acceleration' },
  ];

  return (
    <PageLayout
      title="تشخيصات ومعلومات النظام (System About & Diagnostics)"
      subtitle="معلومات الإصدارات والحزم والبرمجيات المعتمدة للفريق التشغيلي والدعم الفني"
      breadcrumbs={[{ label: 'الرئيسية', href: '/' }, { label: 'تشخيصات النظام' }]}
    >
      <Card>
        <CardHeader title="حزمة التشخيص البرمجي" subtitle="بيانات الإصدارات المباشرة من الخادم" />
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
          {versions.map((v, i) => (
            <div key={i} className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 flex items-center justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-800 dark:text-slate-200">{v.label}</p>
                <p className="font-mono text-sm text-brand-600 dark:text-brand-400 font-bold mt-0.5">{v.value}</p>
              </div>
              <Badge variant="success">{v.status}</Badge>
            </div>
          ))}
        </div>
      </Card>
    </PageLayout>
  );
}
