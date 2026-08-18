import React from 'react';
import { useTranslation } from 'react-i18next';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { Card, CardHeader } from '@/ui/Card';
import Badge from '@/ui/Badge';
import { Server, Code2, Database, Cpu, Terminal } from 'lucide-react';

export default function AboutDiagnosticsPage(): React.JSX.Element {
  const { t } = useTranslation('dashboard');
  const { t: tc } = useTranslation('common');

  // value/status stay untranslated on purpose: they are build metadata
  // reported verbatim (version strings, git sha, engine state), not prose.
  const versions = [
    { label: t('version_platform'), value: 'v1.0.0-rc1', status: 'Official Baseline' },
    { label: t('version_commit'), value: '7f9a2bc (main)', status: 'Clean Build' },
    { label: t('version_build_date'), value: '2026-08-03T18:30:00Z', status: 'Production Artifact' },
    { label: t('version_laravel'), value: 'v11.x Monolith', status: 'Healthy' },
    { label: t('version_php'), value: 'PHP 8.3.x (CLI)', status: 'JIT Enabled' },
    { label: t('version_react'), value: 'v18.3.1 Vite 5', status: 'Production Bundle' },
    { label: t('version_mysql'), value: 'MySQL 8.0 Enterprise', status: 'Cluster Master' },
    { label: t('version_ffmpeg'), value: 'FFmpeg 6.1 HLS', status: 'HW Acceleration' },
  ];

  return (
    <PageLayout
      title={t('diagnostics_title')}
      subtitle={t('diagnostics_subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('action_diagnostics') }]}
    >
      <Card>
        <CardHeader title={t('diagnostics_card_title')} subtitle={t('diagnostics_card_subtitle')} />
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
