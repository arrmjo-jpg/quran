import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/core/constants';
import { activityService } from '../api/activity.service';
import type { ActivityLogFilters } from '../types';

export function useActivityLogs(filters: ActivityLogFilters) {
  return useQuery({
    queryKey: queryKeys.activity.list(filters),
    queryFn: () => activityService.getActivityLogs(filters),
    // Keeps the current page on screen while the next loads, as the users and
    // contestants lists do.
    placeholderData: keepPreviousData,
  });
}
