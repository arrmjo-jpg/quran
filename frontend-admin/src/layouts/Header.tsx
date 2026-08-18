import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Sun, Moon, LogOut, User, Search } from 'lucide-react';
import { useAuth } from '@/core/auth/AuthContext';
import { STORAGE_KEYS } from '@/core/constants';
import Button from '@/ui/Button';
import LanguageSwitcher from '@/ui/language-switcher/LanguageSwitcher';
import { CommandPalette } from '@/ui/command-palette/CommandPalette';

export default function Header(): React.JSX.Element {
  const { t } = useTranslation('navigation');
  const { user, logout } = useAuth();
  const [isDark, setIsDark] = useState<boolean>(() => {
    return localStorage.getItem(STORAGE_KEYS.theme) === 'dark' || document.documentElement.classList.contains('dark');
  });

  useEffect(() => {
    if (isDark) {
      document.documentElement.classList.add('dark');
      localStorage.setItem(STORAGE_KEYS.theme, 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      localStorage.setItem(STORAGE_KEYS.theme, 'light');
    }
  }, [isDark]);

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
        {/* Language Switcher */}
        <LanguageSwitcher />

        {/* Dark Mode Toggle */}
        <button
          onClick={() => setIsDark(!isDark)}
          className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 dark:text-slate-400 transition-colors"
          title={t('toggle_theme')}
        >
          {isDark ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
        </button>

        {/* User Info & Logout */}
        <div className="flex items-center gap-3 pr-3 border-r border-slate-100 dark:border-slate-800">
          <div className="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-300">
            <User className="w-4 h-4" />
          </div>
          <div className="text-xs">
            <p className="font-semibold text-slate-800 dark:text-slate-200">{user?.name ?? 'Admin User'}</p>
            <p className="text-[10px] text-slate-400 dark:text-slate-500">{user?.email}</p>
          </div>
          <Button variant="ghost" size="sm" onClick={logout} className="text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30">
            <LogOut className="w-3.5 h-3.5" />
            <span>{t('logout')}</span>
          </Button>
        </div>
      </div>
    </header>
  );
}
