import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/core/constants';
import { securityService } from '../api/security.service';
import type { LoginHistoryFilters } from '../types';

export function useLoginHistory(filters: LoginHistoryFilters) {
  return useQuery({
    queryKey: queryKeys.security.loginHistory(filters),
    queryFn: () => securityService.getLoginHistory(filters),
    // Keeps the current page on screen while the next loads, as the activity
    // feed and the users list do.
    placeholderData: keepPreviousData,
  });
}

export function useTrustedDevices() {
  return useQuery({
    queryKey: queryKeys.security.devices(),
    queryFn: () => securityService.getTrustedDevices(),
  });
}

export function useTrustThisDevice() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => securityService.trustThisDevice(),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.security.devices() });
    },
  });
}

export function useRevokeDevice() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, wasThisBrowser }: { id: string; wasThisBrowser: boolean }) =>
      securityService.revokeDevice(id, wasThisBrowser),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.security.devices() });
    },
  });
}

export function useDisableMfa() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (password: string) => securityService.disableMfa(password),
    onSuccess: () => {
      // Disabling MFA revokes every device server-side (ADR-018 D4), so the
      // list on screen is stale the moment this returns.
      void queryClient.invalidateQueries({ queryKey: queryKeys.security.all() });
    },
  });
}

export function useRegenerateRecoveryCodes() {
  return useMutation({
    mutationFn: (password: string) => securityService.regenerateRecoveryCodes(password),
  });
}
