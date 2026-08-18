import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/core/constants';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
import { extractErrorMessage } from '@/core/api/errors';
import { roleService } from '../api/role.service';
import type {
  CreateRolePayload,
  RenameRolePayload,
  SyncRolePermissionsPayload,
} from '../types';

export function useRoles() {
  return useQuery({
    queryKey: queryKeys.roles.all(),
    queryFn: () => roleService.getRoles(),
  });
}

/**
 * The catalogue changes only when the server is redeployed, so it is fetched
 * once and kept. Refetching it on every dialog open would be a request per
 * click for a list that cannot have changed.
 */
export function usePermissionCatalog() {
  return useQuery({
    queryKey: queryKeys.permissions.all(),
    queryFn: () => roleService.getPermissions(),
    staleTime: Infinity,
  });
}

export function useCreateRole() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('roles');

  return useMutation({
    mutationFn: (payload: CreateRolePayload) => roleService.createRole(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.roles.all() });
      toast.success(t('create_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('create_error'))),
  });
}

export function useRenameRole() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('roles');

  return useMutation({
    mutationFn: (payload: RenameRolePayload) => roleService.renameRole(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.roles.all() });
      toast.success(t('rename_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('rename_error'))),
  });
}

export function useSyncRolePermissions() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('roles');

  return useMutation({
    mutationFn: (payload: SyncRolePermissionsPayload) => roleService.syncPermissions(payload),
    // KNOWN GAP, deliberately not hidden behind an invalidate that would do
    // nothing: if the operator edits a role they themselves hold, their own
    // AuthContext permissions stay as they were until they sign in again.
    // The session user is read from localStorage once at startup and has no
    // refresh path, so no query key can invalidate it. The server re-resolves
    // on every request, so this affects which controls the panel offers them,
    // never what it lets them do.
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.roles.all() });
      toast.success(t('permissions_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('permissions_error'))),
  });
}

export function useDeleteRole() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('roles');

  return useMutation({
    mutationFn: (id: string) => roleService.deleteRole(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.roles.all() });
      toast.success(t('delete_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('delete_error'))),
  });
}
