import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type {
  EndMembershipPayload,
  Membership,
  MembershipListFilters,
  MembershipListResult,
  StartMembershipPayload,
  TransferMembershipPayload,
} from '../types';

/** GET /admin/memberships nests its pagination block under `meta.pagination`. */
interface PaginatedMemberships {
  success: boolean;
  data:    Membership[];
  meta:    { pagination: { total: number } };
}

export const membershipService = {
  async getMemberships(filters: MembershipListFilters): Promise<MembershipListResult> {
    const params: Record<string, string | number | boolean> = {
      page: filters.page,
      per_page: filters.per_page,
    };

    if (filters.contestant_id) params.contestant_id = filters.contestant_id;
    if (filters.circle_id) params.circle_id = filters.circle_id;
    // Sent only when the operator picked a side. Omitted means the whole
    // history, which is the endpoint's own default.
    if (filters.active !== undefined) params.active = filters.active;

    const { data } = await http.get<PaginatedMemberships>('/admin/memberships', { params });

    return { memberships: data.data, total: data.meta.pagination.total };
  },

  async startMembership(payload: StartMembershipPayload): Promise<Membership> {
    const { data } = await http.post<ApiSuccess<Membership>>('/admin/memberships', payload);
    return data.data;
  },

  /**
   * A POST to a named action, not a DELETE: nothing is removed. The row stays
   * and acquires `left_at`, which is what makes the history readable later.
   */
  async endMembership({ id, ...body }: EndMembershipPayload): Promise<Membership> {
    const { data } = await http.post<ApiSuccess<Membership>>(`/admin/memberships/${id}/end`, body);
    return data.data;
  },

  /** One request, because a transfer is one act — see TransferMembershipPayload. */
  async transferMembership(payload: TransferMembershipPayload): Promise<Membership> {
    const { data } = await http.post<ApiSuccess<Membership>>('/admin/memberships/transfer', payload);
    return data.data;
  },
};
