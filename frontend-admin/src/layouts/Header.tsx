import React from 'react';
import { useTranslation } from 'react-i18next';
import { Search } from 'lucide-react';
import { CommandPalette } from '@/ui/command-palette/CommandPalette';
import UserMenu from '@/ui/user-menu/UserMenu';

/**
 * The admin shell's header — ADR-019 D1, D4.
 *
 * Everything belonging to the signed-in account moved into UserMenu: the
 * language switcher, the theme toggle, the profile link and sign out. The
 * theme state went with the control rather than staying here, so this file no
 * longer holds state for something it does not render.
 *
 * The language switcher is GONE from here rather than duplicated (D4). Two
 * controls for one setting is how they drift, and the login page never had
 * one, so nothing is taken from a signed-out visitor.
 */
export default function Header(): React.JSX.Element {
  const { t } = useTranslation('navigation');

  return (
    <header className="h-16 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 flex items-center justify-between sticky top-0 z-10">
      <CommandPalette />
      <div className="flex items-center gap-3">
        <span className="text-xs px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 font-medium">
          Docker Live API: :8080
        </span>
        <button
          onClick={() => window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true }))}
          className="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-slate-200 px-3 py-1.5 rounded-xl text-xs font-medium transition-colors"
        >
          <Search className="w-3.5 h-3.5" />
          <span>{t('quick_search')}</span>
          <kbd className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-1.5 py-0.5 rounded text-[10px] font-mono">Ctrl K</kbd>
        </button>
      </div>

      <div className="flex items-center gap-3">
        <UserMenu />
      </div>
    </header>
  );
}
