import React from 'react';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { sponsorService, type SponsorItem } from '../api/sponsor.service';
import Spinner from '@/ui/Spinner';
import Badge from '@/ui/Badge';
import { Card, CardHeader } from '@/ui/Card';

export default function SponsorsPage(): React.JSX.Element {
  const { t } = useTranslation('sponsors');

  const { data: sponsors, isLoading } = useQuery({
    queryKey: ['sponsors'],
    queryFn: () => sponsorService.getSponsors(),
  });

  if (isLoading) {
    return <div className="flex h-64 items-center justify-center"><Spinner size="lg" /></div>;
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-bold text-slate-900 dark:text-white">{t('title')}</h2>
        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{t('subtitle')}</p>
      </div>

      <Card>
        <CardHeader title={t('card_title')} subtitle={t('card_subtitle', { count: sponsors?.length ?? 0 })} />
        <div className="overflow-x-auto">
          <table className="w-full text-xs text-start text-slate-600 dark:text-slate-300">
            <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 uppercase font-medium">
              <tr>
                <th className="px-4 py-3 text-start">{t('col_name')}</th>
                <th className="px-4 py-3 text-start">{t('col_tier')}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {sponsors?.map((sp: SponsorItem) => (
                <tr key={sp.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                  <td className="px-4 py-3 font-semibold text-slate-900 dark:text-white">{sp.name}</td>
                  <td className="px-4 py-3">
                    <Badge variant={sp.tier === 'gold' ? 'warning' : 'neutral'}>
                      {sp.tier}
                    </Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
