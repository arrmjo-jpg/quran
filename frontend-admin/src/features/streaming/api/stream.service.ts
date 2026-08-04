import { http } from '@/core/api/http';
import type { PaginatedApiSuccess, ApiSuccess } from '@/core/types';

export interface StreamItem {
  id:          string;
  season_id:   string;
  title:       string;
  status:      string;
  stream_key?: string;
  playback_url?: string;
}

export const streamService = {
  async getStreams(): Promise<StreamItem[]> {
    const { data } = await http.get<PaginatedApiSuccess<StreamItem>>('/admin/streams');
    return data.data;
  },

  async createStream(season_id: string, title: string): Promise<StreamItem> {
    const { data } = await http.post<ApiSuccess<StreamItem>>('/admin/streams', { season_id, title });
    return data.data;
  },

  async startStream(id: string): Promise<void> {
    await http.post(`/admin/streams/${id}/start`);
  },

  async stopStream(id: string): Promise<void> {
    await http.post(`/admin/streams/${id}/stop`);
  },
};
