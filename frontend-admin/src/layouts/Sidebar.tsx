import React from 'react';
import { NavLink } from 'react-router-dom';
import {
  LayoutDashboard,
  Calendar,
  Globe,
  Users,
  Award,
  FileCheck,
  ClipboardList,
  FolderKanban,
  Video,
  Radio,
  Sparkles,
  Megaphone,
  FileText,
  Bell,
  Search,
  BookOpen,
} from 'lucide-react';
import { cn } from '@/core/utils';

const navItems = [
  { path: '/',             label: 'لوحة التحكم',   icon: LayoutDashboard },
  { path: '/seasons',      label: 'المواسم',       icon: Calendar },
  { path: '/contestants',  label: 'المتسابقون',    icon: Users },
  { path: '/judges',       label: 'الحكام',         icon: Award },
  { path: '/applications', label: 'طلبات الاشتراك', icon: FileCheck },
  { path: '/evaluations',  label: 'التقييمات',     icon: ClipboardList },
  { path: '/media',        label: 'مكتبة الوسائط',  icon: FolderKanban },
  { path: '/videos',       label: 'الفيديوهات',    icon: Video },
  { path: '/streaming',    label: 'البث المباشر',   icon: Radio },
  { path: '/sponsors',     label: 'الرعاة',         icon: Sparkles },
  { path: '/content',      label: 'الإعلانات والصفحات', icon: Megaphone },
  { path: '/reports',      label: 'التقارير والتصدير', icon: FileText },
  { path: '/notifications',label: 'الإشعارات',      icon: Bell },
  { path: '/countries',    label: 'الدول المعتمدة', icon: Globe },
  { path: '/search',       label: 'البحث والفهرسة', icon: Search },
];

export default function Sidebar(): React.JSX.Element {
  return (
    <aside className="w-64 border-l border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col h-screen sticky top-0 shrink-0">
      {/* Brand Header */}
      <div className="h-16 flex items-center gap-3 px-5 border-b border-slate-100 dark:border-slate-800 shrink-0">
        <div className="w-9 h-9 bg-brand-600 flex items-center justify-center text-white font-bold shadow-sm shrink-0">
          <BookOpen className="w-5 h-5" />
        </div>
        <div>
          <h1 className="text-sm font-bold text-slate-900 dark:text-white leading-tight">مسابقات القرآن</h1>
          <p className="text-[11px] text-slate-500 dark:text-slate-400 font-medium">لوحة الإدارة المركزية</p>
        </div>
      </div>

      {/* Nav List */}
      <nav className="flex-1 overflow-y-auto p-3 space-y-1">
        {navItems.map((item) => (
          <NavLink
            key={item.path}
            to={item.path}
            end={item.path === '/'}
            className={({ isActive }) =>
              cn(
                'flex items-center gap-3 px-3.5 py-2 text-sm font-semibold transition-all',
                isActive
                  ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/80 dark:text-brand-300 border-r-4 border-brand-600'
                  : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/80 hover:text-slate-950 dark:hover:text-white'
              )
            }
          >
            <item.icon className="w-5 h-5 shrink-0 text-brand-600 dark:text-brand-400" />
            <span>{item.label}</span>
          </NavLink>
        ))}
      </nav>
    </aside>
  );
}
