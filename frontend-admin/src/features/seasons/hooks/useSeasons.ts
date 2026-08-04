import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/core/constants';
import { seasonService } from '../api/season.service';
import type { CreateSeasonPayload, SeasonFilters } from '../types';
import { toast } from 'sonner';
import { extractErrorMessage } from '@/core/api/errors';

export function useSeasons(filters?: SeasonFilters) {
  return useQuery({
    queryKey: [...queryKeys.seasons.all(), filters],
    queryFn: () => seasonService.getSeasons(filters),
  });
}

export function useSeason(id: string) {
  return useQuery({
    queryKey: queryKeys.seasons.detail(id),
    queryFn: () => seasonService.getSeason(id),
    enabled: Boolean(id),
  });
}

export function useCreateSeason() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateSeasonPayload) => seasonService.createSeason(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.seasons.all() });
      toast.success('تم إنشاء الموسم الجديد بنجاح');
    },
    onError: (err) => {
      toast.error(extractErrorMessage(err, 'فشل إنشاء الموسم. تحقق من البيانات.'));
    },
  });
}

export function useOpenRegistration() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => seasonService.openRegistration(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.seasons.all() });
      toast.success('تم فتح فترات التسجيل للموسم بنجاح');
    },
    onError: (err) => {
      toast.error(extractErrorMessage(err, 'فشل فتح فترة التسجيل.'));
    },
  });
}

export function useCloseRegistration() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => seasonService.closeRegistration(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.seasons.all() });
      toast.success('تم إغلاق التسجيل للموسم بنجاح');
    },
    onError: (err) => {
      toast.error(extractErrorMessage(err, 'فشل إغلاق فترة التسجيل.'));
    },
  });
}
