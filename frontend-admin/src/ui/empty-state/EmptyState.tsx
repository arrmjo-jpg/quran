import React from 'react';
import { Inbox } from 'lucide-react';
import { cn } from '@/core/utils';

export interface EmptyStateProps {
  title?:       string;
  description?: string;
  action?:      React.ReactNode;
  icon?:        React.ReactNode;
  className?:   string;
}

export function EmptyState({
  title = 'لا توجد بيانات',
  description = 'لم يتم العثور على أي سجلات مطابقة للبحث أو التصفية.',
  action,
  icon,
  className,
}: EmptyStateProps): React.JSX.Element {
  return (
    <div className={cn('flex flex-col items-center justify-center text-center p-8 border border-dashed border-slate-200 dark:border-slate-800 rounded-xl bg-slate-50/50 dark:bg-slate-900/30', className)}>
      <div className="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mb-3">
        {icon ?? <Inbox className="w-6 h-6" />}
      </div>
      <h4 className="text-sm font-semibold text-slate-800 dark:text-slate-200">{title}</h4>
      {description && <p className="text-xs text-slate-400 dark:text-slate-500 max-w-sm mt-1 mb-4">{description}</p>}
      {action && <div>{action}</div>}
    </div>
  );
}
