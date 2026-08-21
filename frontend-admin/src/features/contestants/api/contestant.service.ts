import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type {
  ContestantDetail,
  ContestantListFilters,
  ContestantListItem,
  ContestantListResult,
  ContestantProfile,
  CreateContestantPayload,
  UpdateContestantPayload,
} from '../types';

interface PaginatedContestants {
  success: boolean;
  data: ContestantListItem[];
  meta: { pagination: { total: number; last_page: number } };
}

export const contestantService = {
  async getContestants(filters: ContestantListFilters): Promise<ContestantListResult> {
    const params: Record<string, string | number | boolean> = {
      page: filters.page,
      per_page: filters.per_page,
    };

    // Only sent when set, following userService: an empty `search=` would
    // filter on the empty string rather than mean "no filter".
    if (filters.search) params.search = filters.search;
    if (filters.country_id) params.country_id = filters.country_id;
    if (filters.gender) params.gender = filters.gender;
    if (filters.with_deleted) params.with_deleted = true;

    const { data } = await http.get<PaginatedContestants>('/admin/contestants', { params });

    return {
      contestants: data.data,
      total: data.meta.pagination.total,
      lastPage: data.meta.pagination.last_page,
    };
  },

  async getContestant(id: string): Promise<ContestantDetail> {
    const { data } = await http.get<ApiSuccess<ContestantDetail>>(`/admin/contestants/${id}`);
    return data.data;
  },

  async createContestant(payload: CreateContestantPayload): Promise<ContestantDetail> {
    const { data } = await http.post<ApiSuccess<ContestantDetail>>('/admin/contestants', payload);
    return data.data;
  },

  async updateContestant(id: string, payload: UpdateContestantPayload): Promise<ContestantDetail> {
    const { data } = await http.patch<ApiSuccess<ContestantDetail>>(`/admin/contestants/${id}`, payload);
    return data.data;
  },

  async deleteContestant(id: string): Promise<void> {
    await http.delete(`/admin/contestants/${id}`);
  },

  async restoreContestant(id: string): Promise<void> {
    await http.post(`/admin/contestants/${id}/restore`);
  },

  /**
   * The 360 drawer's source. Reads the same detail endpoint — the extra
   * branches it renders (applications, appeals) are Identity 360's to fill
   * in Story 2 and are absent rather than faked.
   */
  async getContestant360(id: string): Promise<ContestantProfile> {
    const { data } = await http.get<ApiSuccess<ContestantProfile>>(`/admin/contestants/${id}`);
    return data.data;
  },
};
