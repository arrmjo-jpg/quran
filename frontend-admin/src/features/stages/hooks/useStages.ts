import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { stageService } from '../api/stage.service';
import { extractStageErrorMessage } from '../api/stageErrors';
import type { CreateStagePayload, UpdateStagePayload, StageRuleAssignment } from '../types';

/** Stage lists are always scoped to a season; there is no global stage list. */
export const stageKeys = {
  bySeason: (seasonId: string) => ['stages', seasonId] as const,
  rulesBySeason: (seasonId: string) => ['stage-rules', seasonId] as const,
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
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (stageIds: string[]) => stageService.reorderStages(seasonId, { stage_ids: stageIds }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: stageKeys.bySeason(seasonId) });
      // Rules carry each stage's stage_number for display, so a reorder
      // makes the cached rules stale even though no rule itself changed.
      queryClient.invalidateQueries({ queryKey: stageKeys.rulesBySeason(seasonId) });
      toast.success('تم حفظ ترتيب المراحل بنجاح');
    },
    onError: (err) => {
      toast.error(extractStageErrorMessage(err, 'فشل حفظ ترتيب المراحل.'));
    },
  });
}

export function useStageRules(seasonId: string | null) {
  return useQuery({
    queryKey: stageKeys.rulesBySeason(seasonId ?? ''),
    queryFn: () => stageService.getStageRules(seasonId as string),
    enabled: Boolean(seasonId),
  });
}

export function useUpdateStageRules(seasonId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (rules: StageRuleAssignment[]) => stageService.updateStageRules(seasonId, { rules }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: stageKeys.rulesBySeason(seasonId) });
      toast.success('تم حفظ قواعد المراحل بنجاح');
    },
    onError: (err) => {
      toast.error(extractStageErrorMessage(err, 'فشل حفظ قواعد المراحل. تحقق من البيانات.'));
    },
  });
}
