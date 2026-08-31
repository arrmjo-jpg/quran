import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { queryKeys } from '@/core/constants';
import { extractErrorMessage } from '@/core/api/errors';
import { notificationService, type NotificationItem } from '../api/notification.service';

export function useNotifications() {
  return useQuery<NotificationItem[]>({
    queryKey: queryKeys.notifications.list({}),
    queryFn: () => notificationService.getNotifications(),
  });
}

/**
 * Retry a failed notification — ADR-020 D5.
 *
 * THE BUTTON USED TO BE A TOAST. Its handler was
 * `onClick={() => toast.success(t('retry_success'))}` — no request, no
 * mutation, nothing. It reported success for an action that had not happened,
 * over an endpoint that also queued nothing, so both halves of the feature
 * agreed with each other and neither did anything.
 *
 * The list is invalidated on success because the row's status changes to
 * `queued` and its error is cleared; leaving the table stale would show the
 * operator the failure they just cleared.
 *
 * Every refusal is surfaced verbatim. The server knows three reasons this
 * screen cannot work out on its own — the notification is not failed, no
 * module can rebuild that template, or the invited account has since been
 * claimed — and each 409 names which.
 */
export function useRetryNotification() {
  const queryClient = useQueryClient();
  const { t } = useTranslation('notifications');

  return useMutation({
    mutationFn: (id: string) => notificationService.retry(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.notifications.all() });
      toast.success(t('retry_success'));
    },
    onError: (err) => toast.error(extractErrorMessage(err, t('retry_error'))),
  });
}
