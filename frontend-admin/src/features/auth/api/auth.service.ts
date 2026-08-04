import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';

export interface LoginPayload {
  email:    string;
  password: string;
}

export interface AuthResponseData {
  token: string;
  user: {
    id:    string;
    name:  string;
    email: string;
    type:  string;
    roles: string[];
  };
}

export const authService = {
  async login(payload: LoginPayload): Promise<AuthResponseData> {
    const { data } = await http.post<ApiSuccess<AuthResponseData>>('/admin/auth/login', payload);
    return data.data;
  },

  async getMe(): Promise<AuthResponseData['user']> {
    const { data } = await http.get<ApiSuccess<AuthResponseData['user']>>('/admin/auth/me');
    return data.data;
  },
};
