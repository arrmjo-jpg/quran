import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type { ContestantProfile, ContestantSearchFilters } from '../types';

export const contestantService = {
  async searchContestants(filters?: ContestantSearchFilters): Promise<ContestantProfile[]> {
    const { data } = await http.get<ApiSuccess<ContestantProfile[]>>('/search', { params: { q: filters?.query ?? '' } });
    return data.data;
  },

  async getContestant360(id: string): Promise<ContestantProfile> {
    const { data } = await http.get<ApiSuccess<ContestantProfile>>(`/admin/contestants/${id}`);
    return data.data;
  },
};
