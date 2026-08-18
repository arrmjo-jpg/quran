import React, { useMemo, useState } from 'react';
import { ChevronDown, ChevronLeft, Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Button from '@/ui/Button';
import { Input } from '@/ui/input/Input';
import type { PermissionKey } from '@/core/permissions';
import { actionOf, groupPermissions } from '../utils/groupPermissions';

export interface PermissionMatrixProps {
  /** The catalogue, flat, exactly as the server sent it. */
  catalogue: readonly PermissionKey[];
  selected: PermissionKey[];
  onChange: (next: PermissionKey[]) => void;
  /** A system role is shown but cannot be edited. */
  disabled?: boolean;
}

/**
 * Picks permissions, grouped by resource.
 *
 * The groups are derived from the names (`seasons.view` → `seasons`), never
 * declared. A new server resource therefore appears here on its own, and there
 * is no second list that can disagree with the catalogue about what exists.
 *
 * Collapsed by default. Eighty-two checkboxes open at once is a wall rather
 * than a form, and the counter on each header is what makes a collapsed group
 * readable — `(3/9)` answers "did I grant anything here?" without expanding it.
 */
export function PermissionMatrix({
  catalogue,
  selected,
  onChange,
  disabled = false,
}: PermissionMatrixProps): React.JSX.Element {
  const { t } = useTranslation('roles');
  const [search, setSearch] = useState('');
  const [expanded, setExpanded] = useState<Set<string>>(new Set());

  const held = useMemo(() => new Set(selected), [selected]);

  const groups = useMemo(() => {
    const all = groupPermissions(catalogue);
    const term = search.trim().toLowerCase();
    if (term === '') return all;

    // Matching the whole name means a search for "view" finds the verb across
    // every resource, and a search for "season" finds the resource — both are
    // things someone building a role actually looks for.
    return all
      .map((group) => ({
        ...group,
        permissions: group.permissions.filter((p) => p.toLowerCase().includes(term)),
      }))
      .filter((group) => group.permissions.length > 0);
  }, [catalogue, search]);

  const toggle = (permission: PermissionKey): void => {
    if (disabled) return;
    const next = new Set(held);
    if (next.has(permission)) next.delete(permission);
    else next.add(permission);
    onChange([...next]);
  };

  /** Select or clear a whole group, honouring the current search filter. */
  const setGroup = (permissions: PermissionKey[], grant: boolean): void => {
    if (disabled) return;
    const next = new Set(held);
    for (const permission of permissions) {
      if (grant) next.add(permission);
      else next.delete(permission);
    }
    onChange([...next]);
  };

  const setAll = (grant: boolean): void => {
    if (disabled) return;
    const visible = groups.flatMap((g) => g.permissions);
    setGroup(visible, grant);
  };

  const toggleExpanded = (resource: string): void => {
    const next = new Set(expanded);
    if (next.has(resource)) next.delete(resource);
    else next.add(resource);
    setExpanded(next);
  };

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative flex-1 min-w-[12rem]">
          <Search className="w-4 h-4 absolute top-1/2 -translate-y-1/2 start-3 text-slate-400 pointer-events-none" />
          <Input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t('permission_search')}
            className="ps-9"
          />
        </div>
        <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={() => setAll(true)}>
          {t('select_all')}
        </Button>
        <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={() => setAll(false)}>
          {t('clear_all')}
        </Button>
      </div>

      <div className="text-xs text-slate-500 dark:text-slate-400">
        {t('selected_count', { count: selected.length, total: catalogue.length })}
      </div>

      <div className="max-h-[26rem] overflow-y-auto flex flex-col gap-2 pe-1">
        {groups.map((group) => {
          const grantedHere = group.permissions.filter((p) => held.has(p)).length;
          const isOpen = expanded.has(group.resource) || search.trim() !== '';

          return (
            <div
              key={group.resource}
              className="border border-slate-200 dark:border-slate-800 rounded-lg overflow-hidden"
            >
              <div className="flex items-center gap-2 bg-slate-50 dark:bg-slate-900/60 px-3 py-2">
                <button
                  type="button"
                  onClick={() => toggleExpanded(group.resource)}
                  className="flex items-center gap-2 flex-1 text-start"
                >
                  {isOpen ? (
                    <ChevronDown className="w-4 h-4 text-slate-400 shrink-0" />
                  ) : (
                    <ChevronLeft className="w-4 h-4 text-slate-400 shrink-0 rtl:rotate-180" />
                  )}
                  <span className="font-semibold text-sm text-slate-900 dark:text-white">
                    {t(`resource_${group.resource}`, { defaultValue: group.resource })}
                  </span>
                  <span
                    className={
                      grantedHere > 0
                        ? 'text-xs font-mono text-brand-600 dark:text-brand-400'
                        : 'text-xs font-mono text-slate-400'
                    }
                  >
                    ({grantedHere}/{group.permissions.length})
                  </span>
                </button>

                <button
                  type="button"
                  disabled={disabled}
                  onClick={() => setGroup(group.permissions, true)}
                  className="text-xs text-brand-600 hover:underline disabled:opacity-40 disabled:no-underline"
                >
                  {t('select_group')}
                </button>
                <button
                  type="button"
                  disabled={disabled}
                  onClick={() => setGroup(group.permissions, false)}
                  className="text-xs text-slate-500 hover:underline disabled:opacity-40 disabled:no-underline"
                >
                  {t('clear_group')}
                </button>
              </div>

              {isOpen && (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-1 p-3">
                  {group.permissions.map((permission) => (
                    <label
                      key={permission}
                      className={
                        disabled
                          ? 'flex items-center gap-2 text-sm text-slate-500 cursor-not-allowed'
                          : 'flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 cursor-pointer'
                      }
                    >
                      <input
                        type="checkbox"
                        checked={held.has(permission)}
                        disabled={disabled}
                        onChange={() => toggle(permission)}
                        className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                      />
                      <span className="font-mono text-xs">{actionOf(permission)}</span>
                    </label>
                  ))}
                </div>
              )}
            </div>
          );
        })}

        {groups.length === 0 && (
          <p className="text-sm text-slate-500 py-6 text-center">{t('permission_search_empty')}</p>
        )}
      </div>
    </div>
  );
}
