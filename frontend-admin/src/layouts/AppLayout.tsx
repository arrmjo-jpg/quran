import React from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import Header from './Header';

/**
 * Note the absence of a dir attribute. Direction is set once on <html>
 * from the selected language (see applyDirection in core/config/i18n).
 * Pinning dir="rtl" on this wrapper kept the entire shell right-to-left
 * even after switching to English or Spanish, which made the language
 * switcher look broken for everything except the text itself.
 */
export default function AppLayout(): React.JSX.Element {
  return (
    <div className="flex min-h-screen font-sans bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100">
      <Sidebar />
      <div className="flex flex-col flex-1 min-w-0">
        <Header />
        <main className="flex-1 p-6 overflow-y-auto">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
