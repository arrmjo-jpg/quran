import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type {
  Circle,
  CircleListFilters,
  CircleListResult,
  CreateCirclePayload,
  UpdateCirclePayload,
} from '../types';

/** GET /admin/circles nests its pagination block under `meta.pagination`. */
interface PaginatedCircles {
  success: boolean;
  data:    Circle[];
  meta:    { pagination: { total: number } };
}

export const circleService = {
  async getCircles(filters: CircleListFilters): Promise<CircleListResult> {
    const params: Record<string, string | number> = {
      page: filters.page,
      per_page: filters.per_page,
    };

    // Only sent when set. An empty `search=` would filter on the empty string
    // rather than mean "no filter".
    if (filters.search) params.search = filters.search;
    if (filters.center_id) params.center_id = filters.center_id;
    if (filters.supervisor_user_id) params.supervisor_user_id = filters.supervisor_user_id;

    const { data } = await http.get<PaginatedCircles>('/admin/circles', { params });

    return { circles: data.data, total: data.meta.pagination.total };
  },

  async createCircle(payload: CreateCirclePayload): Promise<Circle> {
    const { data } = await http.post<ApiSuccess<Circle>>('/admin/circles', payload);
    return data.data;
  },

  async updateCircle({ id, ...body }: UpdateCirclePayload): Promise<Circle> {
    const { data } = await http.patch<ApiSuccess<Circle>>(`/admin/circles/${id}`, body);
    return data.data;
  },

  /**
   * Soft delete. Refused with 409 CIRCLE_HAS_MEMBERS while contestants are
   * still enrolled — only OPEN memberships count, so a circle whose
   * contestants have all moved on can be closed.
   */
  async deleteCircle(id: string): Promise<void> {
    await http.delete(`/admin/circles/${id}`);
  },
};
