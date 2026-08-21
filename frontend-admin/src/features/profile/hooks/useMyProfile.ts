import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
import { queryKeys } from '@/core/constants';
import { extractErrorMessage } from '@/core/api/errors';
import { profileService } from '../api/profile.service';
import type { UpdateMyProfilePayload } from '../types';

/**
 * The screen fetches its own account rather than reading AuthContext.
 *
 * AuthUser deliberately does not carry `profile`: it is the authorization
 * context every screen consults, and widening it would mean refreshing it
 * after every profile save for the benefit of one screen. Querying here keeps
 * that cost where it belongs, and invalidation after a save is one line.
 */
export function useMyAccount() {
  return useQuery({
    queryKey: queryKeys.auth.me(),
    queryFn: () => profileService.getMyAccount(),
  });
}

export function useUpdateMyProfile() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('profile');

  return useMutation({
    mutationFn: (payload: UpdateMyProfilePayload) => profileService.updateMyProfile(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.auth.me() });
      toast.success(t('save_success'));
    },
    /**
     * The server owns the social-link rules and its message names both the
     * platform and what was wrong with the link — "The linkedin link must
     * point at linkedin.com". Replacing that with a generic failure would
     * throw away the only part an operator can act on.
     */
    onError: (err) => toast.error(extractErrorMessage(err, t('save_error'))),
  });
}
