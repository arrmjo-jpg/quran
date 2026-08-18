import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/core/constants';
import { seasonService } from '../api/season.service';
import type {
  CreateSeasonPayload,
  UpdateSeasonPayload,
  UpdateSeasonRulesPayload,
  ArchiveSeasonPayload,
  CancelSeasonPayload,
  ReopenRegistrationPayload,
  SeasonFilters,
} from '../types';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
import { extractErrorMessage } from '@/core/api/errors';
import { extractRestoreErrorMessage } from '../api/restoreErrors';
import { extractReopenErrorMessage } from '../api/reopenErrors';

/**
 * Every lifecycle action changes the season row the list renders, so each
 * mutation invalidates the list; the ones that take an id invalidate that
 * season's detail key too, so a detail view opened behind a dialog does
 * not keep showing the pre-action state.
 *
 * Takes translation keys, not finished strings: the toast fires long after
 * the hook was called, and resolving through `t` here means the message
 * follows whatever language is active at that moment.
 */
function useSeasonMutation<TVariables>(
  mutationFn: (variables: TVariables) => Promise<unknown>,
  successKey: string,
  errorKey: string,
  detailIdOf?: (variables: TVariables) => string,
) {
  const queryClient = useQueryClient();
  const { t } = useTranslation('seasons');

  return useMutation({
    mutationFn,
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.seasons.all() });

      if (detailIdOf) {
        queryClient.invalidateQueries({ queryKey: queryKeys.seasons.detail(detailIdOf(variables)) });
      }

      toast.success(t(successKey));
    },
    onError: (err) => {
      toast.error(extractErrorMessage(err, t(errorKey)));
    },
  });
}

export function useSeasons(filters?: SeasonFilters) {
  return useQuery({
    queryKey: [...queryKeys.seasons.all(), filters],
    queryFn: () => seasonService.getSeasons(filters),
  });
}

export function useSeason(id: string) {
  return useQuery({
    queryKey: queryKeys.seasons.detail(id),
    queryFn: () => seasonService.getSeason(id),
    enabled: Boolean(id),
  });
}

export function useCreateSeason() {
  return useSeasonMutation<CreateSeasonPayload>(
    (payload) => seasonService.createSeason(payload),
    'create_success',
    'create_error',
  );
}

export function useUpdateSeason() {
  return useSeasonMutation<{ id: string; payload: UpdateSeasonPayload }>(
    ({ id, payload }) => seasonService.updateSeason(id, payload),
    'update_success',
    'update_error',
    ({ id }) => id,
  );
}

export function useUpdateSeasonRules() {
  return useSeasonMutation<{ id: string; payload: UpdateSeasonRulesPayload }>(
    ({ id, payload }) => seasonService.updateSeasonRules(id, payload),
    'rules_success',
    'rules_error',
    ({ id }) => id,
  );
}

export function useOpenRegistration() {
  return useSeasonMutation<string>(
    (id) => seasonService.openRegistration(id),
    'open_success',
    'open_error',
    (id) => id,
  );
}

export function useCloseRegistration() {
  return useSeasonMutation<string>(
    (id) => seasonService.closeRegistration(id),
    'close_success',
    'close_error',
    (id) => id,
  );
}

export function useArchiveSeason() {
  return useSeasonMutation<{ id: string; payload?: ArchiveSeasonPayload }>(
    ({ id, payload }) => seasonService.archiveSeason(id, payload ?? {}),
    'archive_success',
    'archive_error',
    ({ id }) => id,
  );
}

/**
 * Not built on useSeasonMutation: the restore endpoint's refusals carry a
 * specific reason code and a list of what is blocking it, and collapsing
 * those into the generic message would throw away the only useful part.
 */
export function useRestoreSeason() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('seasons');

  return useMutation({
    mutationFn: (id: string) => seasonService.restoreSeason(id),
    onSuccess: (_data, id) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.seasons.all() });
      queryClient.invalidateQueries({ queryKey: queryKeys.seasons.detail(id) });
      toast.success(t('restore_success'));
    },
    onError: (err) => {
      toast.error(extractRestoreErrorMessage(err, t('restore_error')));
    },
  });
}

/**
 * Like restore, this keeps its own error handling: the "another season is
 * active" refusal names the blocking season, and the generic message would
 * drop exactly the part the admin needs to act on.
 */
export function useReopenRegistration() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('seasons');

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: ReopenRegistrationPayload }) =>
      seasonService.reopenRegistration(id, payload),
    onSuccess: (_data, { id }) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.seasons.all() });
      queryClient.invalidateQueries({ queryKey: queryKeys.seasons.detail(id) });
      toast.success(t('reopen_success'));
    },
    onError: (err) => {
      toast.error(extractReopenErrorMessage(err, t('reopen_error')));
    },
  });
}

export function useCancelSeason() {
  return useSeasonMutation<{ id: string; payload: CancelSeasonPayload }>(
    ({ id, payload }) => seasonService.cancelSeason(id, payload),
    'cancel_success',
    'cancel_error',
    ({ id }) => id,
  );
}
