import React from 'react';
import { ChevronLeft } from 'lucide-react';
import { cn } from '@/core/utils';

export interface BreadcrumbItem {
  label: string;
  href?: string;
}

export interface PageLayoutProps {
  title:        string;
  subtitle?:    string;
  breadcrumbs?: BreadcrumbItem[];
  actions?:     React.ReactNode;
  children:     React.ReactNode;
  className?:   string;
}

export function PageLayout({
  title,
  subtitle,
  breadcrumbs,
  actions,
  children,
  className,
}: PageLayoutProps): React.JSX.Element {
  return (
    <div className={cn('space-y-6', className)}>
      {/* Breadcrumbs & Header Row */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          {breadcrumbs && breadcrumbs.length > 0 && (
            <nav className="flex items-center gap-1.5 text-xs text-slate-400 mb-2">
              {breadcrumbs.map((crumb, idx) => (
                <React.Fragment key={idx}>
                  {idx > 0 && <ChevronLeft className="w-3 h-3 text-slate-400" />}
                  {crumb.href ? (
                    <a href={crumb.href} className="hover:text-brand-600 transition-colors">
                      {crumb.label}
                    </a>
                  ) : (
                    <span className="text-slate-600 dark:text-slate-300 font-medium">{crumb.label}</span>
                  )}
                </React.Fragment>
              ))}
            </nav>
          )}

          <h2 className="text-xl font-bold text-slate-900 dark:text-white tracking-tight">{title}</h2>
          {subtitle && <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{subtitle}</p>}
        </div>

        {actions && <div className="flex items-center gap-2.5 shrink-0">{actions}</div>}
      </div>

      {/* Page Main Content Area */}
      <div>{children}</div>
    </div>
  );
}
