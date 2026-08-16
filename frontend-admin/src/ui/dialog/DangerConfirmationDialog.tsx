import React, { useEffect, useState } from 'react';
import { Dialog } from './Dialog';
import { Input } from '@/ui/input/Input';
import Button from '@/ui/Button';
import { AlertTriangle } from 'lucide-react';

export interface DangerConfirmationDialogProps {
  isOpen:            boolean;
  onClose:           () => void;
  onConfirm:         () => void;
  title:             string;
  /** What is about to happen and why it cannot be undone. */
  description:       string;
  /**
   * The exact text the operator must type. Use something that identifies
   * the specific record — a slug, not a fixed word like "DELETE".
   */
  confirmationText:  string;
  /** Explains what to type, e.g. "اكتب معرّف الموسم للتأكيد". */
  confirmationLabel: string;
  confirmLabel:      string;
  isLoading?:        boolean;
  /** Blocks confirmation for reasons the caller owns, e.g. a missing reason. */
  extraBlocked?:     boolean;
  /** Extra fields rendered above the confirmation input. */
  children?:         React.ReactNode;
}

/**
 * DangerConfirmationDialog
 *
 * For operations that cannot be undone. A plain "are you sure?" only proves
 * the operator meant to click something; typing the record's own identifier
 * proves they were looking at the right row — which is the mistake that
 * actually happens in a table where every row has the same buttons.
 *
 * Deliberately does not judge whether an operation *should* be allowed:
 * that belongs to the domain. Cancelling a season with stages and rules
 * already configured is legitimate — SeasonStateMachine permits draft →
 * archived unconditionally — so this adds friction, never a new rule.
 */
export function DangerConfirmationDialog({
  isOpen,
  onClose,
  onConfirm,
  title,
  description,
  confirmationText,
  confirmationLabel,
  confirmLabel,
  isLoading = false,
  extraBlocked = false,
  children,
}: DangerConfirmationDialogProps): React.JSX.Element | null {
  const [typed, setTyped] = useState('');

  useEffect(() => {
    if (isOpen) setTyped('');
  }, [isOpen, confirmationText]);

  if (!isOpen) return null;

  // Exact match, trimmed only for stray whitespace — not case-insensitive,
  // because a slug is lowercase by definition and matching loosely would
  // weaken the check for no real gain.
  const matches = typed.trim() === confirmationText;
  const blocked = !matches || extraBlocked || isLoading;

  return (
    <Dialog isOpen onClose={onClose} title={title}>
      <div className="space-y-4">
        <div className="flex items-start gap-3 p-3 border rounded-xl bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-900/50 text-rose-800 dark:text-rose-300">
          <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5" />
          <p className="text-xs leading-relaxed">{description}</p>
        </div>

        {children}

        <div className="space-y-2">
          <p className="text-xs text-slate-600 dark:text-slate-300">
            {confirmationLabel}
          </p>
          <p className="px-3 py-2 font-mono text-xs border rounded-xl bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200">
            {confirmationText}
          </p>
          <Input
            value={typed}
            onChange={(e) => setTyped(e.target.value)}
            placeholder={confirmationText}
            autoComplete="off"
            className="font-mono"
          />
        </div>

        <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
          <Button variant="secondary" size="sm" onClick={onClose} disabled={isLoading}>
            تراجع
          </Button>
          <Button variant="danger" size="sm" isLoading={isLoading} disabled={blocked} onClick={onConfirm}>
            {confirmLabel}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
