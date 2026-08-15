import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { stageService } from '../api/stage.service';
import { extractStageErrorMessage } from '../api/stageErrors';
import type { CreateStagePayload, UpdateStagePayload } from '../types';

/** Stage lists are always scoped to a season; there is no global stage list. */
export const stageKeys = {
  bySeason: (seasonId: string) => ['stages', seasonId] as const,
};

export function useStages(seasonId: string | null) {
  return useQuery({
    queryKey: stageKeys.bySeason(seasonId ?? ''),
    queryFn: () => stageService.getStages(seasonId as string),
    enabled: Boolean(seasonId),
  });
}

/**
 * Every stage write changes the season's ordered list, so they all
 * invalidate the same key. Reorder also renumbers rows that were not
 * touched directly, which is exactly why refetching the list beats
 * patching entries in the cache by hand.
 */
function useStageMutation<TVariables>(
  seasonId: string,
  mutationFn: (variables: TVariables) => Promise<unknown>,
  successMessage: string,
  errorMessage: string,
) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: stageKeys.bySeason(seasonId) });
      toast.success(successMessage);
    },
    onError: (err) => {
      toast.error(extractStageErrorMessage(err, errorMessage));
    },
  });
}

export function useCreateStage(seasonId: string) {
  return useStageMutation<CreateStagePayload>(
    seasonId,
    (payload) => stageService.createStage(seasonId, payload),
    'تمت إضافة المرحلة بنجاح',
    'فشل إضافة المرحلة. تحقق من البيانات.',
  );
}

export function useUpdateStage(seasonId: string) {
  return useStageMutation<{ id: string; payload: UpdateStagePayload }>(
    seasonId,
    ({ id, payload }) => stageService.updateStage(id, payload),
    'تم تحديث المرحلة بنجاح',
    'فشل تحديث المرحلة. تحقق من البيانات.',
  );
}

export function useDeleteStage(seasonId: string) {
  return useStageMutation<string>(
    seasonId,
    (id) => stageService.deleteStage(id),
    'تم حذف المرحلة بنجاح',
    'فشل حذف المرحلة.',
  );
}

export function useReorderStages(seasonId: string) {
  return useStageMutation<string[]>(
    seasonId,
    (stageIds) => stageService.reorderStages(seasonId, { stage_ids: stageIds }),
    'تم حفظ ترتيب المراحل بنجاح',
    'فشل حفظ ترتيب المراحل.',
  );
}
