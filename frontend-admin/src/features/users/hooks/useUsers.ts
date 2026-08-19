import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { keepPreviousData } from '@tanstack/react-query';
import { queryKeys } from '@/core/constants';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
import { extractErrorMessage } from '@/core/api/errors';
import { userService } from '../api/user.service';
import type {
  CreateUserPayload,
  SyncUserRolesPayload,
  UpdateUserPayload,
  UserListFilters,
} from '../types';

export function useUsers(filters: UserListFilters) {
  return useQuery({
    queryKey: queryKeys.users.list(filters),
    queryFn: () => userService.getUsers(filters),
    // Keeps the current page on screen while the next one loads, so paging
    // does not blank the table between requests.
    placeholderData: keepPreviousData,
  });
}

export function useUser(id: string | null) {
  return useQuery({
    queryKey: queryKeys.users.detail(id ?? ''),
    queryFn: () => userService.getUser(id as string),
    enabled: id !== null,
  });
}

export function useSyncUserRoles() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('users');

  return useMutation({
    mutationFn: (payload: SyncUserRolesPayload) => userService.syncRoles(payload),
    onSuccess: (_user, payload) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all() });
      queryClient.invalidateQueries({ queryKey: queryKeys.users.detail(payload.id) });
      toast.success(t('roles_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('roles_error'))),
  });
}

/**
 * Activation and deactivation are separate permissions on the server, and
 * separate mutations here for the same reason: a single toggle would need one
 * permission to render, and whichever was chosen would be wrong for whoever
 * held only the other.
 */
export function useActivateUser() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('users');

  return useMutation({
    mutationFn: (id: string) => userService.activate(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all() });
      toast.success(t('activate_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('activate_error'))),
  });
}

export function useDeactivateUser() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('users');

  return useMutation({
    mutationFn: (id: string) => userService.deactivate(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all() });
      toast.success(t('deactivate_success'));
    },
    // The server refuses two cases this screen cannot know about on its own:
    // the last active holder of a system role (409), which needs a count
    // across accounts. The message names the reason, so it is surfaced rather
    // than second-guessed here.
    onError: (err) => toast.error(extractErrorMessage(err, t('deactivate_error'))),
  });
}

/**
 * Creating an account is an invitation, not a password handout. Nothing in the
 * response can be used to claim the account — the link goes to the invitee.
 */
export function useCreateUser() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('users');

  return useMutation({
    mutationFn: (payload: CreateUserPayload) => userService.createUser(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all() });
      toast.success(t('create_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('create_error'))),
  });
}

export function useUpdateUser() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('users');

  return useMutation({
    mutationFn: (payload: UpdateUserPayload) => userService.updateUser(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all() });
      toast.success(t('update_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('update_error'))),
  });
}

/**
 * Soft delete. The server refuses two cases this screen cannot know about —
 * the last active holder of a system role (409), and deleting yourself (403,
 * though that one the screen does know and disables in advance). Both arrive
 * as a message that names the reason.
 */
export function useDeleteUser() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('users');

  return useMutation({
    mutationFn: (id: string) => userService.deleteUser(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all() });
      toast.success(t('delete_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('delete_error'))),
  });
}

export function useRestoreUser() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('users');

  return useMutation({
    mutationFn: (id: string) => userService.restoreUser(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all() });
      toast.success(t('restore_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('restore_error'))),
  });
}
