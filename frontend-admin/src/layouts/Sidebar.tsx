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
  ShieldCheck,
  UserCog,
  Building2,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/core/utils';
import { PermissionWrapper } from '@/ui/permission-wrapper/PermissionWrapper';
import { requiredPermissionFor } from '@/core/navigation/routeAccess';

interface NavItem {
  path: string;
  labelKey: string;
  icon: React.ComponentType<{ className?: string }>;
}

/** Labels are translation keys, resolved at render so they follow the language. */
const navItems: NavItem[] = [
  { path: '/',              labelKey: 'dashboard',     icon: LayoutDashboard },
  { path: '/seasons',       labelKey: 'seasons',       icon: Calendar },
  { path: '/contestants',   labelKey: 'contestants',   icon: Users },
  { path: '/judges',        labelKey: 'judges',        icon: Award },
  { path: '/applications',  labelKey: 'applications',  icon: FileCheck },
  { path: '/evaluations',   labelKey: 'evaluations',   icon: ClipboardList },
  { path: '/media',         labelKey: 'media',         icon: FolderKanban },
  { path: '/videos',        labelKey: 'videos',        icon: Video },
  { path: '/streaming',     labelKey: 'streaming',     icon: Radio },
  { path: '/sponsors',      labelKey: 'sponsors',      icon: Sparkles },
  { path: '/content',       labelKey: 'content',       icon: Megaphone },
  { path: '/reports',       labelKey: 'reports',       icon: FileText },
  { path: '/notifications', labelKey: 'notifications', icon: Bell },
  { path: '/countries',     labelKey: 'countries',     icon: Globe },
  { path: '/centers',       labelKey: 'centers',       icon: Building2 },
  { path: '/users',         labelKey: 'users',         icon: UserCog },
  { path: '/roles',         labelKey: 'roles',         icon: ShieldCheck },
  { path: '/search',        labelKey: 'search',        icon: Search },
];

export default function Sidebar(): React.JSX.Element {
  const { t } = useTranslation('navigation');

  return (
    <aside className="w-64 border-l border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col h-screen sticky top-0 shrink-0">
      {/* Brand Header */}
      <div className="h-16 flex items-center gap-3 px-5 border-b border-slate-100 dark:border-slate-800 shrink-0">
        <div className="w-9 h-9 bg-brand-600 flex items-center justify-center text-white font-bold shadow-sm shrink-0">
          <BookOpen className="w-5 h-5" />
        </div>
        <div>
          <h1 className="text-sm font-bold leading-tight text-slate-900 dark:text-white">{t('brand_name')}</h1>
          <p className="text-[11px] text-slate-500 dark:text-slate-400 font-medium">{t('brand_subtitle')}</p>
        </div>
      </div>

      {/* Nav List */}
      <nav className="flex-1 overflow-y-auto p-3 space-y-1">
        {navItems.map((item) => {
          const link = (
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
              <span>{t(item.labelKey)}</span>
            </NavLink>
          );

          // The same map the router enforces, so a hidden link and a
          // reachable page cannot drift apart.
          const permission = requiredPermissionFor(item.path);

          if (permission === undefined) return link;

          return (
            <PermissionWrapper key={item.path} permission={permission}>
              {link}
            </PermissionWrapper>
          );
        })}
      </nav>
    </aside>
  );
}
