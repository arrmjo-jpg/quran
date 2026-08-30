import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Check, ChevronDown, Globe, LogOut, Moon, ShieldCheck, Sun, User } from 'lucide-react';
import { useAuth } from '@/core/auth/AuthContext';
import { STORAGE_KEYS } from '@/core/constants';
import { SUPPORTED_LANGUAGES, type LanguageCode } from '@/core/config/i18n';
import { applyLanguage, persistLanguage } from '@/core/i18n/accountLanguage';

/**
 * The account's menu — ADR-019 D1.
 *
 * One place for the five things that belong to the signed-in person: profile,
 * security, language, theme and signing out. Before this they were a flat row
 * in the header, with `/profile` hidden behind a name that did not look like a
 * link and `/security` — added by Epic 6 — reachable only from the sidebar.
 *
 * NO PERMISSION GATES IT (D9). Every destination here acts on the account
 * doing the acting, so holding an account is the authorisation. That is the
 * same reasoning ADR-018 D3 used for the security screen itself.
 *
 * BUILT BY HAND because there is no dropdown primitive in this project and no
 * headless library installed. That makes the keyboard and focus behaviour
 * this component's own responsibility rather than a dependency's, so it is
 * written out explicitly below instead of being assumed.
 */
export default function UserMenu(): React.JSX.Element {
  const { t } = useTranslation('navigation');
  const { user, logout, updateUser, isAuthed } = useAuth();
  const { i18n } = useTranslation();

  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);

  const [isDark, setIsDark] = useState<boolean>(() => {
    return (
      localStorage.getItem(STORAGE_KEYS.theme) === 'dark' ||
      document.documentElement.classList.contains('dark')
    );
  });

  /**
   * Theme stays in localStorage — ADR-019 D6. There is no theme column on
   * `users` or `user_profiles`, so unlike language there is no server value
   * being ignored: this is a per-browser display preference living where it
   * belongs. The effect moved here with the control, so the header no longer
   * carries state for something it does not render.
   */
  useEffect(() => {
    if (isDark) {
      document.documentElement.classList.add('dark');
      localStorage.setItem(STORAGE_KEYS.theme, 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      localStorage.setItem(STORAGE_KEYS.theme, 'light');
    }
  }, [isDark]);

  const close = useCallback((returnFocus: boolean) => {
    setIsOpen(false);

    // Focus goes back to the trigger when the menu was dismissed rather than
    // navigated away from. Without it, Escape leaves focus on a hidden
    // element and the next Tab starts from the top of the document.
    if (returnFocus) {
      triggerRef.current?.focus();
    }
  }, []);

  /** Every item that can hold focus, in the order they are rendered. */
  const items = useCallback((): HTMLElement[] => {
    if (!panelRef.current) return [];

    return Array.from(panelRef.current.querySelectorAll<HTMLElement>('[role="menuitem"]'));
  }, []);

  // Opening moves focus into the menu. A dropdown a keyboard cannot enter is
  // decoration, and this one holds the only route to sign out.
  useEffect(() => {
    if (isOpen) {
      items()[0]?.focus();
    }
  }, [isOpen, items]);

  // Pointer dismissal. Bound on the document because the click that closes
  // the menu is by definition outside it.
  useEffect(() => {
    if (!isOpen) return;

    const onPointerDown = (event: MouseEvent): void => {
      if (!containerRef.current?.contains(event.target as Node)) {
        close(false);
      }
    };

    document.addEventListener('mousedown', onPointerDown);

    return () => document.removeEventListener('mousedown', onPointerDown);
  }, [isOpen, close]);

  /**
   * Keyboard behaviour, scoped to the menu rather than the document.
   *
   * Arrow keys wrap, which is what a roving menu is expected to do. Tab is
   * deliberately NOT trapped: a menu is not a modal, and trapping it would
   * strand a keyboard user who wanted to leave — instead, leaving closes it,
   * handled by the focus-out below.
   */
  const onPanelKeyDown = (event: React.KeyboardEvent): void => {
    const focusable = items();
    const current = focusable.indexOf(document.activeElement as HTMLElement);

    switch (event.key) {
      case 'Escape':
        event.preventDefault();
        close(true);
        break;
      case 'ArrowDown':
        event.preventDefault();
        focusable[(current + 1) % focusable.length]?.focus();
        break;
      case 'ArrowUp':
        event.preventDefault();
        focusable[(current - 1 + focusable.length) % focusable.length]?.focus();
        break;
      case 'Home':
        event.preventDefault();
        focusable[0]?.focus();
        break;
      case 'End':
        event.preventDefault();
        focusable[focusable.length - 1]?.focus();
        break;
      default:
        break;
    }
  };

  const onFocusOut = (event: React.FocusEvent): void => {
    if (!containerRef.current?.contains(event.relatedTarget as Node)) {
      close(false);
    }
  };

  /**
   * Language — ADR-019 D2, D4. The behaviour is S2's, unchanged: apply first
   * so the interface answers immediately, then save to the account, and keep
   * the stored snapshot in step so a reload does not revert the choice.
   *
   * The menu is now the only control for this. The header's separate switcher
   * is gone, because two controls for one setting is how they drift.
   */
  const chooseLanguage = (code: LanguageCode): void => {
    applyLanguage(code);

    if (isAuthed) {
      updateUser({ preferred_locale: code });
      void persistLanguage(code).catch(() => {
        // Already on screen and in localStorage; only the spread to other
        // devices failed. Reverting a visible change to report a background
        // failure is the worse trade.
      });
    }

    close(true);
  };

  const itemClass =
    'w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-700 ' +
    'dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 focus:bg-slate-100 ' +
    'dark:focus:bg-slate-800 focus:outline-none transition-colors text-start';

  return (
    <div ref={containerRef} className="relative" onBlur={onFocusOut}>
      <button
        ref={triggerRef}
        type="button"
        onClick={() => setIsOpen((open) => !open)}
        onKeyDown={(e) => {
          // Opening with the keyboard should land inside the menu, not merely
          // reveal it.
          if (e.key === 'ArrowDown' && !isOpen) {
            e.preventDefault();
            setIsOpen(true);
          }
        }}
        aria-haspopup="menu"
        aria-expanded={isOpen}
        className="flex items-center gap-2.5 rounded-lg px-1.5 py-1 hover:bg-slate-50 dark:hover:bg-slate-800/60 focus:outline-none focus:ring-2 focus:ring-brand-500 transition-colors"
      >
        <div className="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-300 shrink-0">
          <User className="w-4 h-4" />
        </div>
        <div className="text-xs text-start hidden sm:block">
          <p className="font-semibold text-slate-800 dark:text-slate-200">{user?.name ?? '—'}</p>
          <p className="text-[10px] text-slate-400 dark:text-slate-500">{user?.email}</p>
        </div>
        <ChevronDown
          className={`w-3.5 h-3.5 text-slate-400 transition-transform ${isOpen ? 'rotate-180' : ''}`}
        />
      </button>

      {isOpen && (
        <div
          ref={panelRef}
          role="menu"
          aria-label={t('profile')}
          onKeyDown={onPanelKeyDown}
          /* `end-0` rather than `right-0`: in RTL the menu must hang from the
             trigger's inline-end edge, which is the left. The max-width keeps
             it on screen on a narrow viewport. */
          className="absolute end-0 top-full mt-2 w-64 max-w-[calc(100vw-2rem)] z-50 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xl p-1.5"
        >
          {/* The name and email are shown here too: on a narrow viewport the
              trigger hides them, and this is where an operator checks which
              account they are signed in as. */}
          <div className="px-3 py-2 border-b border-slate-100 dark:border-slate-800 mb-1.5">
            <p className="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate">
              {user?.name ?? '—'}
            </p>
            <p className="text-[10px] text-slate-400 truncate">{user?.email}</p>
          </div>

          <Link to="/profile" role="menuitem" className={itemClass} onClick={() => close(false)}>
            <User className="w-4 h-4 text-slate-400" />
            {t('profile')}
          </Link>

          {/* Epic 6's screen, linked and not rebuilt (D1). */}
          <Link to="/security" role="menuitem" className={itemClass} onClick={() => close(false)}>
            <ShieldCheck className="w-4 h-4 text-slate-400" />
            {t('security')}
          </Link>

          <div className="my-1.5 border-t border-slate-100 dark:border-slate-800" />

          <p className="px-3 pt-1 pb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400 flex items-center gap-1.5">
            <Globe className="w-3 h-3" />
            {t('menu_language')}
          </p>

          {SUPPORTED_LANGUAGES.map((lang) => (
            <button
              key={lang.code}
              type="button"
              role="menuitem"
              onClick={() => chooseLanguage(lang.code)}
              className={itemClass}
            >
              <span className="w-4 flex justify-center">
                {i18n.language === lang.code && <Check className="w-3.5 h-3.5 text-brand-600" />}
              </span>
              {lang.label}
            </button>
          ))}

          <div className="my-1.5 border-t border-slate-100 dark:border-slate-800" />

          <button type="button" role="menuitem" onClick={() => setIsDark(!isDark)} className={itemClass}>
            {isDark ? (
              <Sun className="w-4 h-4 text-slate-400" />
            ) : (
              <Moon className="w-4 h-4 text-slate-400" />
            )}
            {isDark ? t('theme_light') : t('theme_dark')}
          </button>

          <div className="my-1.5 border-t border-slate-100 dark:border-slate-800" />

          <button
            type="button"
            role="menuitem"
            onClick={() => {
              close(false);
              logout();
            }}
            className={`${itemClass} text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 focus:bg-red-50 dark:focus:bg-red-950/30`}
          >
            <LogOut className="w-4 h-4" />
            {t('logout')}
          </button>
        </div>
      )}
    </div>
  );
}
