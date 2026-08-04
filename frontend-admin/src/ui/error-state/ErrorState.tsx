import React from 'react';
import { AlertTriangle, RefreshCw } from 'lucide-react';
import Button from '@/ui/Button';

export interface ErrorStateProps {
  title?:     string;
  message?:   string;
  onRetry?:   () => void;
}

export function ErrorState({
  title = 'فشل تحميل البيانات',
  message = 'تعذر الاتصال بخادم النظام. يرجى التحقق من الاتصال وإعادة المحاولة.',
  onRetry,
}: ErrorStateProps): React.JSX.Element {
  return (
    <div className="flex flex-col items-center justify-center text-center p-8 border border-rose-200 dark:border-rose-900/50 rounded-xl bg-rose-50/30 dark:bg-rose-950/20">
      <div className="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-900/50 text-rose-600 dark:text-rose-400 flex items-center justify-center mb-3">
        <AlertTriangle className="w-6 h-6" />
      </div>
      <h4 className="text-sm font-semibold text-rose-900 dark:text-rose-200">{title}</h4>
      <p className="text-xs text-rose-600 dark:text-rose-400 max-w-sm mt-1 mb-4">{message}</p>
      {onRetry && (
        <Button size="sm" variant="outline" onClick={onRetry} className="border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 hover:bg-rose-100/50">
          <RefreshCw className="w-3.5 h-3.5" />
          <span>إعادة المحاولة</span>
        </Button>
      )}
    </div>
  );
}
