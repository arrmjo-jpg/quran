import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type { LookupOption, CountryOption } from '../types';

/** The countries endpoint's own cap; asking for more is a 422. */
const COUNTRIES_MAX_PER_PAGE = 100;

/** Stops a malformed meta block from spinning forever. */
const COUNTRIES_MAX_PAGES = 20;

interface PaginationMeta {
  meta?: {
    pagination?: {
      current_page: number;
      last_page:    number;
    };
  };
}

export const lookupService = {
  async getParticipationTypes(): Promise<LookupOption[]> {
    const { data } = await http.get<ApiSuccess<LookupOption[]>>('/admin/participation-types');
    return data.data;
  },

  async getTajweedLevels(): Promise<LookupOption[]> {
    const { data } = await http.get<ApiSuccess<LookupOption[]>>('/admin/tajweed-levels');
    return data.data;
  },

  async getJudgeScoreSystems(): Promise<LookupOption[]> {
    const { data } = await http.get<ApiSuccess<LookupOption[]>>('/admin/judge-score-systems');
    return data.data;
  },

  /**
   * Every active country, not the first page of them.
   *
   * GET /countries is paginated — 20 per page by default, capped at 100 —
   * so a single request cannot return the roughly 195 countries that exist.
   * A truncated list here would not look broken: the admin would simply
   * never see the country they wanted and would have no way to know it was
   * missing, while the season silently became ineligible for it.
   *
   * Walking the pages is done here rather than in the shared country
   * service, which is left exactly as it is — its own truncation is a
   * separate bug in the countries module, tracked separately.
   */
  async getAllActiveCountries(): Promise<CountryOption[]> {
    const collected: CountryOption[] = [];
    let page = 1;
    let lastPage = 1;

    do {
      const { data } = await http.get<ApiSuccess<CountryOption[]> & PaginationMeta>('/countries', {
        params: { page, per_page: COUNTRIES_MAX_PER_PAGE, active: true },
      });

      collected.push(...data.data);

      lastPage = data.meta?.pagination?.last_page ?? page;
      page += 1;
    } while (page <= lastPage && page <= COUNTRIES_MAX_PAGES);

    return collected;
  },
};
