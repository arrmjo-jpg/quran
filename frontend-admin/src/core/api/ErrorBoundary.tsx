import React, { Component, type ErrorInfo, type ReactNode } from 'react';
import { AlertOctagon, RefreshCw } from 'lucide-react';
import i18n from '@/core/config/i18n';
import Button from '@/ui/Button';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
  error:     Error | null;
}

export class ErrorBoundary extends Component<Props, State> {
  public state: State = {
    hasError: false,
    error:    null,
  };

  public static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
    console.error('Uncaught error in React component tree:', error, errorInfo);
  }

  private handleReload = () => {
    this.setState({ hasError: false, error: null });
    window.location.reload();
  };

  public render(): ReactNode {
    if (this.state.hasError) {
      // A class component cannot use the hook. i18n.t() reads the active
      // language at render time, which is enough here: the crash screen
      // replaces the whole tree, so there is no switcher left to change it.
      const t = i18n.getFixedT(null, 'common');

      return (
        <div className="min-h-screen bg-slate-950 flex items-center justify-center p-6 text-center font-sans text-slate-100">
          <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl space-y-4">
            <div className="w-12 h-12 rounded-2xl bg-rose-900/50 text-rose-400 flex items-center justify-center mx-auto shadow-lg">
              <AlertOctagon className="w-6 h-6" />
            </div>
            <h2 className="text-lg font-bold text-white">{t('crash_title')}</h2>
            <p className="text-xs leading-relaxed text-slate-400">
              {t('crash_message')}
            </p>
            <div className="p-3 bg-slate-950 rounded-xl border border-slate-800/80 text-[11px] font-mono text-rose-400 text-right overflow-x-auto max-h-32">
              {this.state.error?.message ?? 'Unknown Error'}
            </div>
            <Button variant="primary" onClick={this.handleReload} className="w-full justify-center">
              <RefreshCw className="w-4 h-4" />
              <span>{t('reload_page')}</span>
            </Button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}
