import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';

export interface SystemServiceHealth {
  name:      string;
  status:    'healthy' | 'degraded' | 'down';
  latency_ms:number;
  checked_at:string;
}

export interface SystemHealthData {
  overall_status: 'healthy' | 'degraded' | 'down';
  services:        SystemServiceHealth[];
  system_info: {
    platform_version: string;
    git_commit:       string;
    build_date:       string;
    laravel_version:  string;
    php_version:      string;
    react_version:    string;
    db_version:       string;
    ffmpeg_version:   string;
  };
}

export const systemHealthService = {
  async getHealth(): Promise<SystemHealthData> {
    const { data } = await http.get<ApiSuccess<SystemHealthData>>('/admin/system/health');
    return data.data;
  },
};
