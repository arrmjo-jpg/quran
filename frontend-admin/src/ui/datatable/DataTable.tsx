import React from 'react';
import {
  useReactTable,
  getCoreRowModel,
  flexRender,
  type ColumnDef,
  type SortingState,
} from '@tanstack/react-table';
import { ChevronUp, ChevronDown, Download, Search, RefreshCw } from 'lucide-react';
import Button from '@/ui/Button';
import Spinner from '@/ui/Spinner';
import { useTranslation } from 'react-i18next';

export interface PaginationState {
  pageIndex: number;
  pageSize:  number;
  total:     number;
}

export interface DataTableProps<TData> {
  columns:          ColumnDef<TData, any>[];
  data:             TData[];
  loading?:         boolean;
  pagination?:      PaginationState;
  onPageChange?:    (page: number) => void;
  onPageSizeChange?:(size: number) => void;
  onSearchChange?:  (query: string) => void;
  onRefresh?:       () => void;
  onExport?:        () => void;
  searchable?:      boolean;
  selectable?:      boolean;
  exportable?:      boolean;
  emptyMessage?:    string;
}

export function DataTable<TData>({
  columns,
  data,
  loading = false,
  pagination,
  onPageChange,
  onPageSizeChange,
  onSearchChange,
  onRefresh,
  onExport,
  searchable = true,
  exportable = false,
  emptyMessage,
}: DataTableProps<TData>): React.JSX.Element {
  const { t } = useTranslation('common');
  const [sorting, setSorting] = React.useState<SortingState>([]);
  const [searchQuery, setSearchQuery] = React.useState('');

  const table = useReactTable({
    data,
    columns,
    state: { sorting },
    onSortingChange: setSorting,
    getCoreRowModel: getCoreRowModel(),
  });

  const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = e.target.value;
    setSearchQuery(val);
    onSearchChange?.(val);
  };

  return (
    <div className="space-y-4">
      {/* Control Bar: Search + Actions */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        {searchable && (
          <div className="relative flex-1 min-w-[240px] max-w-md">
            <Search className="w-4 h-4 text-slate-400 absolute right-3 top-2.5" />
            <input
              type="text"
              value={searchQuery}
              onChange={handleSearch}
              placeholder={t('search')}
              className="w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl pr-9 pl-4 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-brand-500 transition-colors"
            />
          </div>
        )}

        <div className="flex items-center gap-2 mr-auto">
          {onRefresh && (
            <Button variant="ghost" size="sm" onClick={onRefresh} disabled={loading}>
              <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
            </Button>
          )}

          {exportable && onExport && (
            <Button variant="outline" size="sm" onClick={onExport}>
              <Download className="w-3.5 h-3.5" />
              <span>{t('export_csv')}</span>
            </Button>
          )}
        </div>
      </div>

      {/* Main Table */}
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-xs">
        <div className="overflow-x-auto">
          <table className="w-full text-xs text-start text-slate-600 dark:text-slate-300">
            <thead className="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase font-semibold border-b border-slate-100 dark:border-slate-800">
              {table.getHeaderGroups().map((headerGroup) => (
                <tr key={headerGroup.id}>
                  {headerGroup.headers.map((header) => (
                    <th key={header.id} className="px-4 py-3 text-start select-none">
                      {header.isPlaceholder ? null : (
                        <div
                          className={`flex items-center gap-1.5 ${header.column.getCanSort() ? 'cursor-pointer hover:text-slate-900 dark:hover:text-white' : ''}`}
                          onClick={header.column.getToggleSortingHandler()}
                        >
                          {flexRender(header.column.columnDef.header, header.getContext())}
                          {{
                            asc:  <ChevronUp className="w-3.5 h-3.5 text-brand-600" />,
                            desc: <ChevronDown className="w-3.5 h-3.5 text-brand-600" />,
                          }[header.column.getIsSorted() as string] ?? null}
                        </div>
                      )}
                    </th>
                  ))}
                </tr>
              ))}
            </thead>

            <tbody className="divide-y divide-slate-100 dark:divide-slate-800/60">
              {loading ? (
                <tr>
                  <td colSpan={columns.length} className="py-12 text-center">
                    <Spinner size="md" className="mx-auto mb-2" />
                    <p className="text-xs text-slate-400">{t('loading')}</p>
                  </td>
                </tr>
              ) : table.getRowModel().rows.length === 0 ? (
                <tr>
                  <td colSpan={columns.length} className="py-12 text-center text-slate-400">
                    {emptyMessage ?? t('no_data')}
                  </td>
                </tr>
              ) : (
                table.getRowModel().rows.map((row) => (
                  <tr key={row.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                    {row.getVisibleCells().map((cell) => (
                      <td key={cell.id} className="px-4 py-3">
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                      </td>
                    ))}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Footer */}
        {pagination && (
          <div className="flex items-center justify-between px-4 py-3 bg-slate-50/50 dark:bg-slate-800/30 border-t border-slate-100 dark:border-slate-800 text-xs">
            <span className="text-slate-500 dark:text-slate-400">
              {t('pagination_summary', {
                page:  pagination.pageIndex + 1,
                pages: Math.ceil(pagination.total / pagination.pageSize) || 1,
                total: pagination.total,
              })}
            </span>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={pagination.pageIndex <= 0 || loading}
                onClick={() => onPageChange?.(pagination.pageIndex - 1)}
              >
                {t('previous')}
              </Button>
              <Button
                variant="outline"
                size="sm"
                disabled={(pagination.pageIndex + 1) * pagination.pageSize >= pagination.total || loading}
                onClick={() => onPageChange?.(pagination.pageIndex + 1)}
              >
                {t('next')}
              </Button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
