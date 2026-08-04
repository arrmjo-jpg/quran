import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Search, Calendar, Users, Award, FileCheck, FolderKanban, Video, Radio, FileText, Settings, Shield } from 'lucide-react';

export function CommandPalette(): React.JSX.Element | null {
  const navigate = useNavigate();
  const [isOpen, setIsOpen] = useState(false);
  const [query, setQuery] = useState('');

  const commands = [
    { label: 'لوحة التحكم الرئيسية', path: '/', icon: Search },
    { label: 'إدارة المواسم وفترات التسجيل', path: '/seasons', icon: Calendar },
    { label: 'المتسابقون وملفات 360°', path: '/contestants', icon: Users },
    { label: 'لجنة التحكيم والقراءات', path: '/judges', icon: Award },
    { label: 'طابور مراجعة الطلبات', path: '/applications', icon: FileCheck },
    { label: 'مركز التقييمات والنتائج', path: '/evaluations', icon: Award },
    { label: 'مكتبة الوسائط Cloudflare R2', path: '/media', icon: FolderKanban },
    { label: 'معالجة الفيديوهات HLS', path: '/videos', icon: Video },
    { label: 'غرفة البث المباشر RTMP', path: '/streaming', icon: Radio },
    { label: 'مركز التقارير والتصدير', path: '/reports', icon: FileText },
    { label: 'مستكشف سجلات التدقيق Audit', path: '/audit-logs', icon: Shield },
    { label: 'تشخيصات ومعلومات النظام', path: '/about', icon: Settings },
  ];

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

  const filteredCommands = commands.filter((cmd) => cmd.label.toLowerCase().includes(query.toLowerCase()));

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center pt-20 p-4 bg-slate-950/60 backdrop-blur-xs animate-in fade-in duration-150">
      <div className="w-full max-w-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden text-right">
        <div className="p-3 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
          <Search className="w-4 h-4 text-slate-400" />
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="اكتب اسم الصفحة أو الموديول للانتقال السريع (Ctrl + K)..."
            className="w-full bg-transparent text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none"
            autoFocus
          />
        </div>

        <div className="max-h-80 overflow-y-auto p-2 space-y-1">
          {filteredCommands.length === 0 ? (
            <p className="text-xs text-slate-400 text-center py-6">لم يتم العثور على أي خيار مطابقة.</p>
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
