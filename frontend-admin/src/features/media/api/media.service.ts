import { http } from '@/core/api/http';
import type { PaginatedApiSuccess, ApiSuccess } from '@/core/types';

export interface MediaItem {
  id:           string;
  filename:     string;
  mime_type:    string;
  size_bytes:   number;
  url?:         string;
  status:       string;
  created_at:   string;
}

export const mediaService = {
  async getMedia(): Promise<MediaItem[]> {
    const { data } = await http.get<PaginatedApiSuccess<MediaItem>>('/admin/media');
    return data.data;
  },

  async uploadFile(file: File): Promise<MediaItem> {
    const formData = new FormData();
    formData.append('file', file);
    const { data } = await http.post<ApiSuccess<MediaItem>>('/admin/media', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data.data;
  },
};
