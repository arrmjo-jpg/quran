import { http } from '@/core/api/http';
import type { ActivityLogEntry, ActivityLogFilters, ActivityLogResult } from '../types';

interface ActivityLogResponse {
  success: boolean;
  data:    ActivityLogEntry[];
  actors:  Record<string, string>;
  meta:    { pagination: { total: number; last_page: number } };
}

export const activityService = {
  /**
   * The activity feed — `GET /admin/activity-logs`, behind `audit.view`.
   *
   * Replaces `systemHealthService.getAuditLogs()`, which called
   * `/admin/system/audit-logs`: an endpoint that has never existed, returning
   * a shape nobody had implemented.
   */
  async getActivityLogs(filters: ActivityLogFilters): Promise<ActivityLogResult> {
    const params: Record<string, string | number> = {
      page: filters.page,
      per_page: filters.per_page,
    };

    // Only sent when set — an empty value would filter on the empty string
    // rather than mean "no filter".
    if (filters.action) params.action = filters.action;
    if (filters.actor_id) params.actor_id = filters.actor_id;
    if (filters.correlation_id) params.correlation_id = filters.correlation_id;
    if (filters.from) params.from = filters.from;
    if (filters.to) params.to = filters.to;

    // The pair travels together or not at all: the server refuses an id
    // without its type, because an id alone could collide across tables.
    if (filters.entity_type && filters.entity_id) {
      params.entity_type = filters.entity_type;
      params.entity_id = filters.entity_id;
    }

    const { data } = await http.get<ActivityLogResponse>('/admin/activity-logs', { params });

    return {
      entries: data.data,
      actors: data.actors,
      total: data.meta.pagination.total,
      lastPage: data.meta.pagination.last_page,
    };
  },
};
