import { http } from '@/core/api/http';
import type { PaginatedApiSuccess } from '@/core/types';

/**
 * One row of the notification log, as the server actually returns it.
 *
 * THE PREVIOUS SHAPE DESCRIBED A DIFFERENT API. It declared `type` and
 * `recipient`, and NotificationLogResource has never returned either — so
 * `row.original.type.includes('email')` was a TypeError waiting for its first
 * row. It never fired because `notification_logs` was empty: nothing on the
 * platform wrote to it. ADR-020 D2 changed that, which turned a dormant
 * mismatch into a crash on the first invited administrator.
 *
 * There is no recipient address here, and that is deliberate rather than
 * missing: administrators read other people's notification rows, so the log
 * carries `user_id` and the address is resolved only at send time.
 */
export interface NotificationItem {
  id:           string;
  user_id:      string;
  channel:      'email' | 'sms' | 'push';
  template_key: string;
  /** ADR-020 D6 — three states. `retrying` was removed; the domain never modelled it. */
  status:       'queued' | 'sent' | 'failed';
  sent_at:      string | null;
  /** Why the last attempt failed. Kept because `failed` alone says nothing (D10). */
  error:        string | null;
  created_at:   string;
}

export const notificationService = {
  async getNotifications(): Promise<NotificationItem[]> {
    const { data } = await http.get<PaginatedApiSuccess<NotificationItem>>('/admin/notifications');
    return data.data;
  },

  /**
   * Send a failed notification again — ADR-020 D5.
   *
   * The server refuses more than this screen can predict: a notification that
   * is not failed, a template no module can rebuild, an invitation whose
   * account has since been claimed. Each answers 409 with a reason, so the
   * caller surfaces the message rather than second-guessing it.
   */
  async retry(id: string): Promise<void> {
    await http.post(`/admin/notifications/${id}/retry`);
  },
};
