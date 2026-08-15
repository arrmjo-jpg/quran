import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type {
  Season,
  CreateSeasonPayload,
  UpdateSeasonPayload,
  ArchiveSeasonPayload,
  CancelSeasonPayload,
  SeasonFilters,
} from '../types';

/**
 * Every call here hits the admin routes, never the public /seasons ones:
 * the public endpoints return PublicSeasonResource, which withholds the
 * judging configuration and all archive metadata these screens rely on.
 */
export const seasonService = {
  async getSeasons(filters?: SeasonFilters): Promise<Season[]> {
    // NOTE: the API ignores query params today — GET /admin/seasons returns
    // every season. Filtering is applied client-side until a season count
    // justifies real server-side filtering and pagination.
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

  async updateSeason(id: string, payload: UpdateSeasonPayload): Promise<Season> {
    const { data } = await http.patch<ApiSuccess<Season>>(`/admin/seasons/${id}`, payload);
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

  /** Only legal from `completed` — the API answers 409 otherwise. */
  async archiveSeason(id: string, payload: ArchiveSeasonPayload = {}): Promise<Season> {
    const { data } = await http.post<ApiSuccess<Season>>(`/admin/seasons/${id}/archive`, payload);
    return data.data;
  },

  /** Only legal from `draft`, and the reason is mandatory. */
  async cancelSeason(id: string, payload: CancelSeasonPayload): Promise<Season> {
    const { data } = await http.post<ApiSuccess<Season>>(`/admin/seasons/${id}/cancel`, payload);
    return data.data;
  },
};
