import { http } from '@/core/api/http';
import type { PaginatedApiSuccess } from '@/core/types';

export interface VideoItem {
  id:             string;
  application_id: string;
  media_id:       string;
  status:         string;
  hls_url?:       string;
}

export const videoService = {
  async getVideos(): Promise<VideoItem[]> {
    const { data } = await http.get<PaginatedApiSuccess<VideoItem>>('/admin/videos');
    return data.data;
  },

  async reprocessVideo(id: string): Promise<void> {
    await http.post(`/admin/videos/${id}/reprocess`);
  },
};
