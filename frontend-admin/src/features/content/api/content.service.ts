import { http } from '@/core/api/http';
import type { PaginatedApiSuccess, ApiSuccess } from '@/core/types';

export interface AnnouncementItem {
  id:             string;
  slug:           string;
  target_surface: string;
  is_published:   boolean;
}

export const contentService = {
  async getAnnouncements(): Promise<AnnouncementItem[]> {
    const { data } = await http.get<PaginatedApiSuccess<AnnouncementItem>>('/admin/content/announcements');
    return data.data;
  },

  async createAnnouncement(slug: string, target_surface: string): Promise<AnnouncementItem> {
    const { data } = await http.post<ApiSuccess<AnnouncementItem>>('/admin/content/announcements', { slug, target_surface, is_published: true });
    return data.data;
  },
};
