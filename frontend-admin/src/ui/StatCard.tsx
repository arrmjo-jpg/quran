import React from 'react';
import { Card } from './Card';
import { cn } from '@/core/utils';

interface StatCardProps {
  title: string;
  value: string | number;
  icon: React.ReactNode;
  description?: string;
  trend?: string;
  color?: 'brand' | 'emerald' | 'amber' | 'purple';
}

export default function StatCard({ title, value, icon, description, color = 'brand' }: StatCardProps): React.JSX.Element {
  const iconColors = {
    brand:   'bg-brand-50 text-brand-600 dark:bg-brand-950/50 dark:text-brand-400',
    emerald: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400',
    amber:   'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400',
    purple:  'bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-400',
  };

  return (
    <Card>
      <div className="flex items-center justify-between">
        <div>
          <p className="text-xs font-medium text-slate-500 dark:text-slate-400">{title}</p>
          <p className="text-2xl font-bold text-slate-900 dark:text-white mt-1.5">{value}</p>
          {description && <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">{description}</p>}
        </div>
        <div className={cn('p-3 rounded-xl', iconColors[color])}>
          {icon}
        </div>
      </div>
    </Card>
  );
}
