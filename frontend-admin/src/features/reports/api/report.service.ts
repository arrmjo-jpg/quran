import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';

export interface ExecutiveSummaryData {
  total_contestants:       number;
  total_applications:      number;
  applications_by_status:  Record<string, number>;
  total_evaluations:       number;
  completed_evaluations:   number;
}

export interface ExportJobData {
  id:           string;
  type:         string;
  status:       string;
  file_url?:    string;
  created_at:   string;
}

export const reportService = {
  async getSummary(): Promise<ExecutiveSummaryData> {
    const { data } = await http.get<ApiSuccess<ExecutiveSummaryData>>('/admin/reports/summary');
    return data.data;
  },

  async getExports(): Promise<ExportJobData[]> {
    const { data } = await http.get<ApiSuccess<ExportJobData[]>>('/admin/reports/exports');
    return data.data;
  },

  async createExport(type: string): Promise<ExportJobData> {
    const { data } = await http.post<ApiSuccess<ExportJobData>>('/admin/reports/exports', { type });
    return data.data;
  },
};
