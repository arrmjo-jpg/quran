import React, { useState } from 'react';
import { Dialog } from '@/ui/dialog/Dialog';
import Badge from '@/ui/Badge';
import { VideoPlayer } from '@/ui/media/VideoPlayer';
import type { ContestantProfile } from '../types';
import { User, FileText, Video, Award, TrendingUp, Scale, History } from 'lucide-react';

export interface Contestant360DrawerProps {
  contestant: ContestantProfile | null;
  isOpen:     boolean;
  onClose:    () => void;
}

type TabType = 'profile' | 'application' | 'video' | 'evaluations' | 'results' | 'appeals' | 'timeline';

export function Contestant360Drawer({ contestant, isOpen, onClose }: Contestant360DrawerProps): React.JSX.Element | null {
  const [activeTab, setActiveTab] = useState<TabType>('profile');

  if (!contestant) return null;

  const tabs = [
    { id: 'profile',     label: 'الملف الشخصي', icon: User },
    { id: 'application', label: 'الطلب الحالي', icon: FileText },
    { id: 'video',       label: 'الفيديو المعالج', icon: Video },
    { id: 'evaluations', label: 'التقييمات',     icon: Award },
    { id: 'results',     label: 'النتائج والتأهل', icon: TrendingUp },
    { id: 'appeals',     label: 'الاعتراضات',   icon: Scale },
    { id: 'timeline',    label: 'سجل النشاطات',  icon: History },
  ] as const;

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title={`الملف الشخصي 360° — ${contestant.full_name}`}>
      <div className="space-y-4 text-xs text-right">
        {/* Tabs Bar */}
        <div className="flex border-b border-slate-200 dark:border-slate-800 overflow-x-auto pb-1 gap-1">
          {tabs.map((t) => {
            const Icon = t.icon;
            const isActive = activeTab === t.id;
            return (
              <button
                key={t.id}
                onClick={() => setActiveTab(t.id as TabType)}
                className={`flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold shrink-0 transition-colors ${
                  isActive
                    ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/60 dark:text-brand-300'
                    : 'text-slate-500 hover:text-slate-900 dark:hover:text-slate-200'
                }`}
              >
                <Icon className="w-3.5 h-3.5" />
                <span>{t.label}</span>
              </button>
            );
          })}
        </div>

        {/* Tab Content */}
        <div className="min-h-[240px] pt-2">
          {activeTab === 'profile' && (
            <div className="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl space-y-3 border border-slate-100 dark:border-slate-800">
              <div className="flex items-center justify-between">
                <span className="font-bold text-sm text-slate-900 dark:text-white">
                  {contestant.full_name}
                </span>
                <Badge variant="info">{contestant.profile_completeness ? `${contestant.profile_completeness.completeness_percent}%` : '—'}</Badge>
              </div>
              <div className="grid grid-cols-2 gap-2 text-slate-600 dark:text-slate-300">
                <p>تاريخ الميلاد: <span className="font-mono text-slate-900 dark:text-white">{contestant.date_of_birth ?? '—'}</span></p>
                <p>رقم الهاتف: <span className="font-mono text-slate-900 dark:text-white">{contestant.phone_number ?? '—'}</span></p>
                <p>تاريخ التسجيل: <span className="font-mono">{contestant.created_at ?? '—'}</span></p>
                <p>حالة الحساب: <Badge variant="success">نشط ومتحقق</Badge></p>
              </div>
            </div>
          )}

          {activeTab === 'application' && (
            <div className="space-y-3">
              {contestant.applications?.map((app) => (
                <div key={app.id} className="p-4 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="font-mono font-bold text-slate-900 dark:text-white">طلب رقم: {app.id}</span>
                    <Badge variant={app.status === 'ready_for_judging' ? 'success' : 'warning'}>{app.status}</Badge>
                  </div>
                  <p className="text-slate-500">الموسم: {app.season_id} | المرحلة: {app.stage_id}</p>
                </div>
              ))}
            </div>
          )}

          {activeTab === 'video' && (
            <div className="space-y-3">
              {contestant.applications && contestant.applications[0]?.video_hls_url ? (
                <>
                  <h4 className="font-semibold text-slate-900 dark:text-white">معاينة فيديو التلاوة (HLS Stream)</h4>
                  <VideoPlayer url={contestant.applications[0].video_hls_url} />
                </>
              ) : (
                <p className="text-slate-400 text-center py-8">لا يوجد فيديو تلاوة مرفوع حالياً.</p>
              )}
            </div>
          )}

          {activeTab === 'evaluations' && (
            <div className="space-y-2">
              <h4 className="font-semibold text-slate-900 dark:text-white">تقييمات لجنة التحكيم</h4>
              <p className="text-slate-500">درجات التجويد والحفظ وحسن الصوت المعتمدة من الحكام.</p>
            </div>
          )}

          {activeTab === 'results' && (
            <div className="space-y-2 bg-emerald-50/50 dark:bg-emerald-950/20 p-4 rounded-xl border border-emerald-200 dark:border-emerald-900/50">
              <h4 className="font-bold text-emerald-900 dark:text-emerald-300">النتيجة النهائية للمرحلة</h4>
              <p className="text-emerald-700 dark:text-emerald-400">متوسط الدرجة: 94.5 / 100 — <Badge variant="success">متأهل للمرحلة التالية</Badge></p>
            </div>
          )}

          {activeTab === 'appeals' && (
            <div className="space-y-2">
              {contestant.appeals?.map((appeal) => (
                <div key={appeal.id} className="p-3 bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 rounded-xl">
                  <div className="flex items-center justify-between mb-1">
                    <span className="font-semibold text-amber-900 dark:text-amber-300">اعتراض</span>
                    <Badge variant={appeal.status === 'accepted' ? 'success' : 'danger'}>{appeal.status}</Badge>
                  </div>
                  <p className="text-slate-600 dark:text-slate-400">{appeal.reason}</p>
                </div>
              )) ?? <p className="text-slate-400 text-center py-8">لا توجد اعتراضات مقدمة.</p>}
            </div>
          )}

          {activeTab === 'timeline' && (
            <div className="space-y-3 relative pr-4 border-r-2 border-slate-200 dark:border-slate-800">
              <div className="relative">
                <span className="w-2.5 h-2.5 rounded-full bg-brand-600 absolute -right-[21px] top-1" />
                <p className="font-semibold text-slate-900 dark:text-white">تم تقديم طلب الاشتراك</p>
                <p className="text-slate-400 text-[10px]">منذ يومين</p>
              </div>
              <div className="relative">
                <span className="w-2.5 h-2.5 rounded-full bg-emerald-600 absolute -right-[21px] top-1" />
                <p className="font-semibold text-slate-900 dark:text-white">تم فحص الفيديو وتحويله للتحكيم</p>
                <p className="text-slate-400 text-[10px]">منذ يوم واحد</p>
              </div>
            </div>
          )}
        </div>
      </div>
    </Dialog>
  );
}
