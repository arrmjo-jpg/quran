import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
import { queryKeys } from '@/core/constants';
import { extractErrorMessage } from '@/core/api/errors';
import { centerService } from '../api/center.service';
import type { CenterListFilters, CreateCenterPayload, UpdateCenterPayload } from '../types';

export function useCenters(filters: CenterListFilters) {
  return useQuery({
    queryKey: queryKeys.centers.list(filters),
    queryFn: () => centerService.getCenters(filters),
    // Keeps the current page on screen while the next one loads, so paging
    // does not blank the table between requests.
    placeholderData: keepPreviousData,
  });
}

export function useCreateCenter() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('centers');

  return useMutation({
    mutationFn: (payload: CreateCenterPayload) => centerService.createCenter(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.centers.all() });
      toast.success(t('create_success'));
    },
    // 409 CENTER_NAME_TAKEN carries the city in its message, which is the part
    // that makes it actionable — the same name is perfectly legal one town over.
    onError: (err) => toast.error(extractErrorMessage(err, t('create_error'))),
  });
}

export function useUpdateCenter() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('centers');

  return useMutation({
    mutationFn: (payload: UpdateCenterPayload) => centerService.updateCenter(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.centers.all() });
      toast.success(t('update_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('update_error'))),
  });
}

/**
 * Soft delete.
 *
 * The server refuses a centre that still holds circles (409), which needs a
 * count across another table this screen never loads. Its message names how
 * many are in the way, so it is surfaced rather than replaced by a generic
 * failure the operator cannot act on.
 */
export function useDeleteCenter() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('centers');

  return useMutation({
    mutationFn: (id: string) => centerService.deleteCenter(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.centers.all() });
      toast.success(t('delete_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('delete_error'))),
  });
}
