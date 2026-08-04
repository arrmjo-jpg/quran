import { http } from '@/core/api/http';
import type { PaginatedApiSuccess, ApiSuccess } from '@/core/types';
import type { ApplicationItem } from '../types';

export const applicationService = {
  async getApplications(): Promise<ApplicationItem[]> {
    const { data } = await http.get<PaginatedApiSuccess<ApplicationItem>>('/admin/applications');
    return data.data;
  },

  async markReadyForJudging(id: string): Promise<ApplicationItem> {
    const { data } = await http.post<ApiSuccess<ApplicationItem>>(`/admin/applications/${id}/ready-for-judging`);
    return data.data;
  },
};
