import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Search, Calendar, Users, Award, FileCheck, FolderKanban, Video, Radio, FileText, Settings, Shield } from 'lucide-react';
import { requiredPermissionFor } from '@/core/navigation/routeAccess';
import { useAuth } from '@/core/auth/AuthContext';

/**
 * Longer, searchable descriptions of the same routes the sidebar lists tersely.
 * Kept separate from the sidebar labels on purpose: one is a nav label, the
 * other is what an operator would type to find the page.
 */
const commands = [
  { labelKey: 'command_dashboard',    path: '/',            icon: Search },
  { labelKey: 'command_seasons',      path: '/seasons',     icon: Calendar },
  { labelKey: 'command_contestants',  path: '/contestants', icon: Users },
  { labelKey: 'command_judges',       path: '/judges',      icon: Award },
  { labelKey: 'command_applications', path: '/applications',icon: FileCheck },
  { labelKey: 'command_evaluations',  path: '/evaluations', icon: Award },
  { labelKey: 'command_media',        path: '/media',       icon: FolderKanban },
  { labelKey: 'command_videos',       path: '/videos',      icon: Video },
  { labelKey: 'command_streaming',    path: '/streaming',   icon: Radio },
  { labelKey: 'command_reports',      path: '/reports',     icon: FileText },
  { labelKey: 'command_audit_logs',   path: '/audit-logs',  icon: Shield },
  { labelKey: 'command_about',        path: '/about',       icon: Settings },
];

export function CommandPalette(): React.JSX.Element | null {
  const { t } = useTranslation('navigation');
  const { user } = useAuth();
  const navigate = useNavigate();
  const [isOpen, setIsOpen] = useState(false);
  const [query, setQuery] = useState('');

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setIsOpen((prev) => !prev);
      }
      if (e.key === 'Escape') setIsOpen(false);
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  if (!isOpen) return null;

  /**
   * Permission first, then text — ADR-019 D7.
   *
   * The comment that stood here said "the operator searches what they can
   * see", and only a text match followed it: every destination was offered to
   * everyone. The route guard then refused on arrival, which is the worst
   * order to discover it in — after the operator has decided to act.
   *
   * `requiredPermissionFor` is the same function the sidebar passes to
   * PermissionWrapper, so the two surfaces cannot drift into disagreeing
   * about who may see what. A path with no rule is open by design (the
   * dashboard, the about page) and stays visible.
   *
   * NOT A SECURITY BOUNDARY: the server refuses unauthorized requests
   * whatever this renders. It keeps what is offered matching what is
   * permitted.
   */
  const permitted = user?.permissions ?? [];

  const filteredCommands = commands
    .filter((cmd) => {
      const required = requiredPermissionFor(cmd.path);

      return required === undefined || permitted.includes(required);
    })
    .map((cmd) => ({ ...cmd, label: t(cmd.labelKey) }))
    .filter((cmd) => cmd.label.toLowerCase().includes(query.toLowerCase()));

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center pt-20 p-4 bg-slate-950/60 backdrop-blur-xs animate-in fade-in duration-150">
      <div className="w-full max-w-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden text-start">
        <div className="p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
          <Search className="w-4 h-4 text-slate-400" />
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={t('command_placeholder')}
            className="w-full bg-transparent text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none"
            autoFocus
          />
        </div>

        <div className="max-h-80 overflow-y-auto p-2 space-y-1">
          {filteredCommands.length === 0 ? (
            <p className="py-6 text-xs text-center text-slate-400">{t('command_no_results')}</p>
          ) : (
            filteredCommands.map((cmd, idx) => {
              const Icon = cmd.icon;
              return (
                <button
                  key={idx}
                  onClick={() => {
                    navigate(cmd.path);
                    setIsOpen(false);
                  }}
                  className="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-brand-50 dark:hover:bg-brand-950/50 hover:text-brand-600 dark:hover:text-brand-400 transition-colors"
                >
                  <Icon className="w-4 h-4 text-slate-400" />
                  <span>{cmd.label}</span>
                </button>
              );
            })
          )}
        </div>
      </div>
    </div>
  );
}
