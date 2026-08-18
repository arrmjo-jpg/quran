import { useQuery } from '@tanstack/react-query';
import { lookupService } from '../api/lookup.service';

/**
 * Catalogs change rarely and are read on every rules screen, so they are
 * cached for the session rather than refetched per dialog open.
 */
const CATALOG_STALE_TIME = 10 * 60 * 1000;

export function useParticipationTypes() {
  return useQuery({
    queryKey: ['lookups', 'participation-types'],
    queryFn: () => lookupService.getParticipationTypes(),
    staleTime: CATALOG_STALE_TIME,
  });
}

export function useTajweedLevels() {
  return useQuery({
    queryKey: ['lookups', 'tajweed-levels'],
    queryFn: () => lookupService.getTajweedLevels(),
    staleTime: CATALOG_STALE_TIME,
  });
}

export function useJudgeScoreSystems() {
  return useQuery({
    queryKey: ['lookups', 'judge-score-systems'],
    queryFn: () => lookupService.getJudgeScoreSystems(),
    staleTime: CATALOG_STALE_TIME,
  });
}

export function useAllCountries() {
  return useQuery({
    queryKey: ['lookups', 'countries', 'all-active'],
    queryFn: () => lookupService.getAllActiveCountries(),
    staleTime: CATALOG_STALE_TIME,
  });
}
