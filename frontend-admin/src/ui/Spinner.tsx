import React from 'react';
import { Loader2 } from 'lucide-react';
import { cn } from '@/core/utils';

interface SpinnerProps {
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export default function Spinner({ size = 'md', className }: SpinnerProps): React.JSX.Element {
  const sizeMap = {
    sm: 'w-4 h-4',
    md: 'w-6 h-6',
    lg: 'w-10 h-10',
  };

  return <Loader2 className={cn('animate-spin text-brand-600', sizeMap[size], className)} />;
}
