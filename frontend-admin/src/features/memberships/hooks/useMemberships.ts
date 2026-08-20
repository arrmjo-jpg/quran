import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
import { queryKeys } from '@/core/constants';
import { extractErrorMessage } from '@/core/api/errors';
import { membershipService } from '../api/membership.service';
import type {
  EndMembershipPayload,
  MembershipListFilters,
  StartMembershipPayload,
  TransferMembershipPayload,
} from '../types';

export function useMemberships(filters: MembershipListFilters) {
  return useQuery({
    queryKey: queryKeys.memberships.list(filters),
    queryFn: () => membershipService.getMemberships(filters),
    placeholderData: keepPreviousData,
  });
}

/**
 * Enrols a contestant.
 *
 * The server enforces G1 — one open membership at a time — so enrolling a
 * contestant who already belongs somewhere is refused rather than silently
 * creating a second. That is a conflict this screen could only detect by
 * loading every membership, so the refusal is surfaced with its message.
 */
export function useStartMembership() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('memberships');

  return useMutation({
    mutationFn: (payload: StartMembershipPayload) => membershipService.startMembership(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.memberships.all() });
      toast.success(t('start_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('start_error'))),
  });
}

export function useEndMembership() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('memberships');

  return useMutation({
    mutationFn: (payload: EndMembershipPayload) => membershipService.endMembership(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.memberships.all() });
      // Circles show an enrolment count for their close guard, so the number
      // this just changed must not stay cached.
      queryClient.invalidateQueries({ queryKey: queryKeys.circles.all() });
      toast.success(t('end_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('end_error'))),
  });
}

/**
 * Moves a contestant to another circle.
 *
 * One request rather than an end followed by a start: the server closes the
 * old membership and opens the new one in a single transaction, so a failure
 * halfway cannot leave a contestant belonging to neither circle — or to both.
 */
export function useTransferMembership() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('memberships');

  return useMutation({
    mutationFn: (payload: TransferMembershipPayload) => membershipService.transferMembership(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.memberships.all() });
      queryClient.invalidateQueries({ queryKey: queryKeys.circles.all() });
      toast.success(t('transfer_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('transfer_error'))),
  });
}
