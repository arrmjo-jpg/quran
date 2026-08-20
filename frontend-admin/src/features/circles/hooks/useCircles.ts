import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
import { queryKeys } from '@/core/constants';
import { extractErrorMessage } from '@/core/api/errors';
import { circleService } from '../api/circle.service';
import type { CircleListFilters, CreateCirclePayload, UpdateCirclePayload } from '../types';

export function useCircles(filters: CircleListFilters) {
  return useQuery({
    queryKey: queryKeys.circles.list(filters),
    queryFn: () => circleService.getCircles(filters),
    // Keeps the current page on screen while the next one loads, so paging
    // does not blank the table between requests.
    placeholderData: keepPreviousData,
  });
}

export function useCreateCircle() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('circles');

  return useMutation({
    mutationFn: (payload: CreateCirclePayload) => circleService.createCircle(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.circles.all() });
      toast.success(t('create_success'));
    },
    // 409 CIRCLE_NAME_TAKEN is scoped to the centre: the same name at another
    // centre is ordinary, so the message says where the clash is.
    onError: (err) => toast.error(extractErrorMessage(err, t('create_error'))),
  });
}

export function useUpdateCircle() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('circles');

  return useMutation({
    mutationFn: (payload: UpdateCirclePayload) => circleService.updateCircle(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.circles.all() });
      toast.success(t('update_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('update_error'))),
  });
}

/**
 * Soft delete.
 *
 * The server refuses a circle that still holds open memberships (409). That
 * needs a count in a table this screen never loads, so the message is
 * surfaced rather than replaced — it names how many contestants are enrolled,
 * which is what tells an operator whether to transfer them or wait.
 */
export function useDeleteCircle() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('circles');

  return useMutation({
    mutationFn: (id: string) => circleService.deleteCircle(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.circles.all() });
      toast.success(t('delete_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('delete_error'))),
  });
}
