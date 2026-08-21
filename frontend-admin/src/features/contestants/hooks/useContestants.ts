import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
import { queryKeys } from '@/core/constants';
import { extractErrorMessage } from '@/core/api/errors';
import { contestantService } from '../api/contestant.service';
import type {
  ContestantListFilters,
  CreateContestantPayload,
  UpdateContestantPayload,
} from '../types';

export function useContestants(filters: ContestantListFilters) {
  return useQuery({
    queryKey: queryKeys.contestants.list(filters),
    queryFn: () => contestantService.getContestants(filters),
    // Keeps the current page on screen while the next one loads, so paging
    // does not blank the table between requests — as the users list does.
    placeholderData: keepPreviousData,
  });
}

export function useContestant360(id: string) {
  return useQuery({
    queryKey: queryKeys.contestants.detail(id),
    queryFn: () => contestantService.getContestant360(id),
    enabled: Boolean(id),
  });
}

export function useCreateContestant() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('contestants');

  return useMutation({
    mutationFn: (payload: CreateContestantPayload) => contestantService.createContestant(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.contestants.all() });
      toast.success(t('create_success'));
    },
    // The server refuses a duplicate account with a field error the panel
    // cannot predict: contestants.user_id is unique across deleted rows too,
    // so an account whose contestant was removed is still taken. The message
    // names the reason rather than this guessing at it.
    onError: (err) => toast.error(extractErrorMessage(err, t('create_error'))),
  });
}

export function useUpdateContestant() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('contestants');

  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: UpdateContestantPayload }) =>
      contestantService.updateContestant(id, payload),
    onSuccess: (_contestant, { id }) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.contestants.all() });
      queryClient.invalidateQueries({ queryKey: queryKeys.contestants.detail(id) });
      toast.success(t('update_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('update_error'))),
  });
}

/**
 * Delete and restore are separate permissions on the server — both
 * super_admin's alone, per ADR-016 D17 — and separate mutations here for the
 * reason the users feature gives for activate/deactivate: a single toggle
 * would need one permission to render, and whichever was chosen would be
 * wrong for whoever held only the other.
 */
export function useDeleteContestant() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('contestants');

  return useMutation({
    mutationFn: (id: string) => contestantService.deleteContestant(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.contestants.all() });
      toast.success(t('delete_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('delete_error'))),
  });
}

export function useRestoreContestant() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('contestants');

  return useMutation({
    mutationFn: (id: string) => contestantService.restoreContestant(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.contestants.all() });
      toast.success(t('restore_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('restore_error'))),
  });
}
