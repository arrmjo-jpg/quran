import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type {
  Center,
  CenterListFilters,
  CenterListResult,
  CreateCenterPayload,
  UpdateCenterPayload,
} from '../types';

/** GET /admin/centers nests its pagination block under `meta.pagination`. */
interface PaginatedCenters {
  success: boolean;
  data:    Center[];
  meta:    { pagination: { total: number } };
}

export const centerService = {
  async getCenters(filters: CenterListFilters): Promise<CenterListResult> {
    const params: Record<string, string | number> = {
      page: filters.page,
      per_page: filters.per_page,
    };

    // Only sent when set. An empty `search=` would filter on the empty string
    // rather than mean "no filter".
    if (filters.search) params.search = filters.search;
    if (filters.country_id) params.country_id = filters.country_id;

    const { data } = await http.get<PaginatedCenters>('/admin/centers', { params });

    return { centers: data.data, total: data.meta.pagination.total };
  },

  async createCenter(payload: CreateCenterPayload): Promise<Center> {
    const { data } = await http.post<ApiSuccess<Center>>('/admin/centers', payload);
    return data.data;
  },

  async updateCenter({ id, ...body }: UpdateCenterPayload): Promise<Center> {
    const { data } = await http.patch<ApiSuccess<Center>>(`/admin/centers/${id}`, body);
    return data.data;
  },

  /**
   * Soft delete. Refused with 409 CENTER_HAS_CIRCLES while the centre still
   * holds circles — a count this screen cannot take for itself, so the
   * server's message is surfaced rather than second-guessed.
   */
  async deleteCenter(id: string): Promise<void> {
    await http.delete(`/admin/centers/${id}`);
  },
};
