import { http } from '@/core/api/http';
import type { PaginatedApiSuccess, ApiSuccess } from '@/core/types';

export interface SponsorItem {
  id:        string;
  name:      string;
  tier:      string;
  logo_url?: string;
}

export const sponsorService = {
  async getSponsors(): Promise<SponsorItem[]> {
    const { data } = await http.get<PaginatedApiSuccess<SponsorItem>>('/admin/sponsors');
    return data.data;
  },

  async createSponsor(name: string, tier: string): Promise<SponsorItem> {
    const { data } = await http.post<ApiSuccess<SponsorItem>>('/admin/sponsors', { name, tier });
    return data.data;
  },
};
