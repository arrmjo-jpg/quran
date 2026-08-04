import React from 'react';
import ReactPlayer from 'react-player';
import { cn } from '@/core/utils';

export interface VideoPlayerProps {
  url:        string;
  className?: string;
}

export function VideoPlayer({ url, className }: VideoPlayerProps): React.JSX.Element {
  return (
    <div className={cn('relative rounded-xl overflow-hidden bg-black aspect-video', className)}>
      <ReactPlayer
        url={url}
        controls
        width="100%"
        height="100%"
        config={{
          file: {
            attributes: { controlsList: 'nodownload' },
          },
        }}
      />
    </div>
  );
}

export function ImagePreview({ src, alt }: { src: string; alt?: string }): React.JSX.Element {
  return (
    <div className="relative rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 aspect-video bg-slate-100 dark:bg-slate-900 flex items-center justify-center">
      <img src={src} alt={alt ?? 'Preview'} className="object-contain max-h-full" />
    </div>
  );
}

export function ProgressBar({ progress }: { progress: number }): React.JSX.Element {
  const clamped = Math.min(100, Math.max(0, progress));
  return (
    <div className="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden">
      <div className="bg-brand-600 h-full transition-all duration-300 rounded-full" style={{ width: `${clamped}%` }} />
    </div>
  );
}
