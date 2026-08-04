import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';

export interface Judge {
  id:             string;
  user_id:        string;
  full_name:      string;
  specialization: string;
  title?:         string;
  bio?:           string;
}

export const judgeService = {
  async getJudges(): Promise<Judge[]> {
    const { data } = await http.get<ApiSuccess<Judge[]>>('/admin/judges');
    return data.data;
  },

  async createJudge(payload: Omit<Judge, 'id'>): Promise<Judge> {
    const { data } = await http.post<ApiSuccess<Judge>>('/admin/judges', payload);
    return data.data;
  },
};
