import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type { Country, CreateCountryPayload } from '../types';

export const countryService = {
  async getCountries(): Promise<Country[]> {
    const { data } = await http.get<ApiSuccess<Country[]>>('/countries');
    return data.data;
  },

  async createCountry(payload: CreateCountryPayload): Promise<Country> {
    const { data } = await http.post<ApiSuccess<Country>>('/admin/countries', payload);
    return data.data;
  },
};
