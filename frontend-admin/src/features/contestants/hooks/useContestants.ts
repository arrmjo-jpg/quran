import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/core/constants';
import { contestantService } from '../api/contestant.service';
import type { ContestantSearchFilters } from '../types';

export function useContestants(filters?: ContestantSearchFilters) {
  return useQuery({
    queryKey: queryKeys.contestants.search(filters?.query ?? ''),
    queryFn: () => contestantService.searchContestants(filters),
  });
}

export function useContestant360(id: string) {
  return useQuery({
    queryKey: queryKeys.contestants.detail(id),
    queryFn: () => contestantService.getContestant360(id),
    enabled: Boolean(id),
  });
}
