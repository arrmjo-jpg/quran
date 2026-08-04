import { http } from '@/core/api/http';
import type { PaginatedApiSuccess } from '@/core/types';

export interface NotificationItem {
  id:         string;
  type:       string;
  recipient:  string;
  status:     string;
  created_at: string;
}

export const notificationService = {
  async getNotifications(): Promise<NotificationItem[]> {
    const { data } = await http.get<PaginatedApiSuccess<NotificationItem>>('/admin/notifications');
    return data.data;
  },
};
