import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/ar';

dayjs.extend(relativeTime);
dayjs.locale('ar');

/** Merge Tailwind classes safely */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}

/** Format date to Arabic readable */
export function formatDate(date: string | null | undefined, format = 'D MMMM YYYY'): string {
  if (!date) return '—';
  return dayjs(date).format(format);
}

/** Format date relative (e.g. "منذ 3 أيام") */
export function fromNow(date: string | null | undefined): string {
  if (!date) return '—';
  return dayjs(date).fromNow();
}

/** Truncate text */
export function truncate(text: string, length = 60): string {
  return text.length > length ? `${text.slice(0, length)}…` : text;
}

/** Get initials from name */
export function getInitials(name: string): string {
  return name
    .split(' ')
    .slice(0, 2)
    .map((n) => n[0])
    .join('')
    .toUpperCase();
}
