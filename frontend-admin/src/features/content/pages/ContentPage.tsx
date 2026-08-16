import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { contentService, type AnnouncementItem } from '../api/content.service';
import Spinner from '@/ui/Spinner';
import Badge from '@/ui/Badge';
import { Card, CardHeader } from '@/ui/Card';

export default function ContentPage(): React.JSX.Element {
  const { data: announcements, isLoading } = useQuery({
    queryKey: ['content', 'announcements'],
    queryFn: () => contentService.getAnnouncements(),
  });

  if (isLoading) {
    return <div className="flex h-64 items-center justify-center"><Spinner size="lg" /></div>;
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-bold text-slate-900 dark:text-white">إدارة المحتوى والإعلانات</h2>
        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">التحكم في الإعلانات الرسمية والتنبيهات الموجهة للمستخدمين</p>
      </div>

      <Card>
        <CardHeader title="الإعلانات المنشورة" subtitle={`إجمالي الإعلانات: ${announcements?.length ?? 0}`} />
        <div className="overflow-x-auto">
          <table className="w-full text-xs text-start text-slate-600 dark:text-slate-300">
            <thead className="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 uppercase font-medium">
              <tr>
                <th className="px-4 py-3 text-start">المعرف الفرعي (Slug)</th>
                <th className="px-4 py-3 text-start">الوجهة المستهدفة</th>
                <th className="px-4 py-3 text-start">النشر</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {announcements?.map((item: AnnouncementItem) => (
                <tr key={item.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-800/40">
                  <td className="px-4 py-3 font-semibold text-slate-900 dark:text-white">{item.slug}</td>
                  <td className="px-4 py-3 text-slate-500">{item.target_surface}</td>
                  <td className="px-4 py-3">
                    <Badge variant={item.is_published ? 'success' : 'neutral'}>
                      {item.is_published ? 'منشور' : 'مسودة'}
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
