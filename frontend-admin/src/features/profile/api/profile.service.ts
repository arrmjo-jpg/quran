import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type { MyAccount, UpdateMyProfilePayload } from '../types';

/**
 * Both calls live on `/admin/auth`, which is where the panel already reads its
 * own account from. `PATCH /admin/auth/me` was added in Story 4 for exactly
 * this reason: before it, the only way to write was `/me`, and this screen
 * would have been the one place in the panel reaching outside `/admin`.
 */
export const profileService = {
  async getMyAccount(): Promise<MyAccount> {
    const { data } = await http.get<ApiSuccess<MyAccount>>('/admin/auth/me');
    return data.data;
  },

  async updateMyProfile(payload: UpdateMyProfilePayload): Promise<MyAccount> {
    const { data } = await http.patch<ApiSuccess<MyAccount>>('/admin/auth/me', payload);
    return data.data;
  },
};
