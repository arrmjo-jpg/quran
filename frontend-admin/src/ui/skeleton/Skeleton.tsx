import React from 'react';
import { cn } from '@/core/utils';

export interface SkeletonProps {
  className?: string;
}

export function Skeleton({ className }: SkeletonProps): React.JSX.Element {
  return (
    <div className={cn('animate-pulse rounded-md bg-slate-200 dark:bg-slate-800', className)} />
  );
}

export function TableSkeleton({ rows = 5 }: { rows?: number }): React.JSX.Element {
  return (
    <div className="space-y-3 p-4">
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="flex items-center gap-4">
          <Skeleton className="h-4 w-1/4" />
          <Skeleton className="h-4 w-1/3" />
          <Skeleton className="h-4 w-1/6 mr-auto" />
        </div>
      ))}
    </div>
  );
}
