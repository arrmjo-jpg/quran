import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';

export interface IndexingLog {
  id:           string;
  index_name:   string;
  status:       string;
  indexed_count:number;
  created_at:   string;
}

export const searchService = {
  async triggerReindex(): Promise<void> {
    await http.post('/admin/search/reindex', { index_name: 'all' });
  },

  async getIndexingLogs(): Promise<IndexingLog[]> {
    const { data } = await http.get<ApiSuccess<IndexingLog[]>>('/admin/search/indexing-logs');
    return data.data;
  },

  async globalSearch(query: string): Promise<unknown> {
    const { data } = await http.get('/search', { params: { q: query } });
    return data.data;
  },
};
