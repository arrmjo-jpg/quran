import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type { Season, CreateSeasonPayload, SeasonFilters } from '../types';

export const seasonService = {
  // Admin endpoints, not the public /seasons ones: the public resource
  // withholds the judging configuration and archive metadata these
  // screens need.
  async getSeasons(filters?: SeasonFilters): Promise<Season[]> {
    const { data } = await http.get<ApiSuccess<Season[]>>('/admin/seasons', { params: filters });
    return data.data;
  },

  async getSeason(id: string): Promise<Season> {
    const { data } = await http.get<ApiSuccess<Season>>(`/admin/seasons/${id}`);
    return data.data;
  },

  async createSeason(payload: CreateSeasonPayload): Promise<Season> {
    const { data } = await http.post<ApiSuccess<Season>>('/admin/seasons', payload);
    return data.data;
  },

  async openRegistration(id: string): Promise<Season> {
    const { data } = await http.post<ApiSuccess<Season>>(`/admin/seasons/${id}/open-registration`);
    return data.data;
  },

  async closeRegistration(id: string): Promise<Season> {
    const { data } = await http.post<ApiSuccess<Season>>(`/admin/seasons/${id}/close-registration`);
    return data.data;
  },
};
