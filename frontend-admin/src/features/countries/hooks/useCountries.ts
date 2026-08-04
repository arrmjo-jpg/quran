import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/core/constants';
import { countryService } from '../api/country.service';
import type { CreateCountryPayload } from '../types';
import { toast } from 'sonner';
import { extractErrorMessage } from '@/core/api/errors';

export function useCountries() {
  return useQuery({
    queryKey: queryKeys.countries.all(),
    queryFn: () => countryService.getCountries(),
  });
}

export function useCreateCountry() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateCountryPayload) => countryService.createCountry(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.countries.all() });
      toast.success('تمت إضافة الدولة المعتمدة بنجاح');
    },
    onError: (err) => {
      toast.error(extractErrorMessage(err, 'فشلت إضافة الدولة. تحقق من عدم تكرار الرموز.'));
    },
  });
}
