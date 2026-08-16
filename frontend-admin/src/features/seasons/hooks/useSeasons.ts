import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/core/constants';
import { seasonService } from '../api/season.service';
import type {
  CreateSeasonPayload,
  UpdateSeasonPayload,
  UpdateSeasonRulesPayload,
  ArchiveSeasonPayload,
  CancelSeasonPayload,
  SeasonFilters,
} from '../types';
import { toast } from 'sonner';
import { extractErrorMessage } from '@/core/api/errors';
import { extractRestoreErrorMessage } from '../api/restoreErrors';

/**
 * Every lifecycle action changes the season row the list renders, so each
 * mutation invalidates the list; the ones that take an id invalidate that
 * season's detail key too, so a detail view opened behind a dialog does
 * not keep showing the pre-action state.
 */
function useSeasonMutation<TVariables>(
  mutationFn: (variables: TVariables) => Promise<unknown>,
  successMessage: string,
  errorMessage: string,
  detailIdOf?: (variables: TVariables) => string,
) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn,
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.seasons.all() });

      if (detailIdOf) {
        queryClient.invalidateQueries({ queryKey: queryKeys.seasons.detail(detailIdOf(variables)) });
      }

      toast.success(successMessage);
    },
    onError: (err) => {
      toast.error(extractErrorMessage(err, errorMessage));
    },
  });
}

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
  return useSeasonMutation<CreateSeasonPayload>(
    (payload) => seasonService.createSeason(payload),
    'تم إنشاء الموسم الجديد بنجاح',
    'فشل إنشاء الموسم. تحقق من البيانات.',
  );
}

export function useUpdateSeason() {
  return useSeasonMutation<{ id: string; payload: UpdateSeasonPayload }>(
    ({ id, payload }) => seasonService.updateSeason(id, payload),
    'تم تحديث بيانات الموسم بنجاح',
    'فشل تحديث الموسم. تحقق من البيانات.',
    ({ id }) => id,
  );
}

export function useUpdateSeasonRules() {
  return useSeasonMutation<{ id: string; payload: UpdateSeasonRulesPayload }>(
    ({ id, payload }) => seasonService.updateSeasonRules(id, payload),
    'تم حفظ قواعد الموسم بنجاح',
    'فشل حفظ قواعد الموسم. تحقق من البيانات.',
    ({ id }) => id,
  );
}

export function useOpenRegistration() {
  return useSeasonMutation<string>(
    (id) => seasonService.openRegistration(id),
    'تم فتح فترة التسجيل للموسم بنجاح',
    'فشل فتح فترة التسجيل.',
    (id) => id,
  );
}

export function useCloseRegistration() {
  return useSeasonMutation<string>(
    (id) => seasonService.closeRegistration(id),
    'تم إغلاق التسجيل للموسم بنجاح',
    'فشل إغلاق فترة التسجيل.',
    (id) => id,
  );
}

export function useArchiveSeason() {
  return useSeasonMutation<{ id: string; payload?: ArchiveSeasonPayload }>(
    ({ id, payload }) => seasonService.archiveSeason(id, payload ?? {}),
    'تمت أرشفة الموسم بنجاح',
    'فشلت أرشفة الموسم.',
    ({ id }) => id,
  );
}

/**
 * Not built on useSeasonMutation: the restore endpoint's refusals carry a
 * specific reason code and a list of what is blocking it, and collapsing
 * those into the generic message would throw away the only useful part.
 */
export function useRestoreSeason() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: string) => seasonService.restoreSeason(id),
    onSuccess: (_data, id) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.seasons.all() });
      queryClient.invalidateQueries({ queryKey: queryKeys.seasons.detail(id) });
      toast.success('تمت استعادة الموسم إلى مسودة');
    },
    onError: (err) => {
      toast.error(extractRestoreErrorMessage(err, 'فشلت استعادة الموسم.'));
    },
  });
}

export function useCancelSeason() {
  return useSeasonMutation<{ id: string; payload: CancelSeasonPayload }>(
    ({ id, payload }) => seasonService.cancelSeason(id, payload),
    'تم إلغاء الموسم بنجاح',
    'فشل إلغاء الموسم.',
    ({ id }) => id,
  );
}
