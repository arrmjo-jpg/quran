import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
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
 *
 * Takes translation keys rather than finished strings: the toast fires
 * long after the hook was called, so the message is resolved through `t`
 * at that moment instead of being captured here.
 */
function useStageMutation<TVariables>(
  seasonId: string,
  mutationFn: (variables: TVariables) => Promise<unknown>,
  successKey: string,
  errorKey: string,
) {
  const queryClient = useQueryClient();
  const { t } = useTranslation('stages');

  return useMutation({
    mutationFn,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: stageKeys.bySeason(seasonId) });
      toast.success(t(successKey));
    },
    onError: (err) => {
      toast.error(extractStageErrorMessage(err, t(errorKey)));
    },
  });
}

export function useCreateStage(seasonId: string) {
  return useStageMutation<CreateStagePayload>(
    seasonId,
    (payload) => stageService.createStage(seasonId, payload),
    'create_success',
    'create_error',
  );
}

export function useUpdateStage(seasonId: string) {
  return useStageMutation<{ id: string; payload: UpdateStagePayload }>(
    seasonId,
    ({ id, payload }) => stageService.updateStage(id, payload),
    'update_success',
    'update_error',
  );
}

export function useDeleteStage(seasonId: string) {
  return useStageMutation<string>(
    seasonId,
    (id) => stageService.deleteStage(id),
    'delete_success',
    'delete_error',
  );
}

export function useReorderStages(seasonId: string) {
  const queryClient = useQueryClient();
  const { t } = useTranslation('stages');

  return useMutation({
    mutationFn: (stageIds: string[]) => stageService.reorderStages(seasonId, { stage_ids: stageIds }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: stageKeys.bySeason(seasonId) });
      // Rules carry each stage's stage_number for display, so a reorder
      // makes the cached rules stale even though no rule itself changed.
      queryClient.invalidateQueries({ queryKey: stageKeys.rulesBySeason(seasonId) });
      toast.success(t('reorder_success'));
    },
    onError: (err) => {
      toast.error(extractStageErrorMessage(err, t('reorder_error')));
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
  const { t } = useTranslation('stages');

  return useMutation({
    mutationFn: (rules: StageRuleAssignment[]) => stageService.updateStageRules(seasonId, { rules }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: stageKeys.rulesBySeason(seasonId) });
      toast.success(t('rules_success'));
    },
    onError: (err) => {
      toast.error(extractStageErrorMessage(err, t('rules_error')));
    },
  });
}
