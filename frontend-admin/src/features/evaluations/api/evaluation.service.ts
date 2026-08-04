import { http } from '@/core/api/http';
import type { PaginatedApiSuccess, ApiSuccess } from '@/core/types';

export interface EvaluationItem {
  id:             string;
  application_id: string;
  judge_id:       string;
  total_score:    number;
  status:         string;
  submitted_at?:  string;
}

export const evaluationService = {
  async getEvaluations(): Promise<EvaluationItem[]> {
    const { data } = await http.get<PaginatedApiSuccess<EvaluationItem>>('/admin/evaluations');
    return data.data;
  },

  async calculateResults(stageId: string): Promise<void> {
    await http.post(`/admin/stages/${stageId}/calculate-results`);
  },

  async publishResults(stageId: string): Promise<void> {
    await http.post(`/admin/stages/${stageId}/publish-results`);
  },
};
