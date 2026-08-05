import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type { Judge } from '../types';

export const judgeService = {
  async getJudges(): Promise<Judge[]> {
    const { data } = await http.get<ApiSuccess<Judge[]>>('/admin/judges');
    return data.data;
  },

  async createJudge(payload: Pick<Judge, 'user_id' | 'full_name' | 'specialization'> & Partial<Pick<Judge, 'title' | 'bio'>>): Promise<Judge> {
    const { data } = await http.post<ApiSuccess<Judge>>('/admin/judges', payload);
    return data.data;
  },
};
