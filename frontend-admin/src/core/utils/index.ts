import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/ar';
import 'dayjs/locale/es';

dayjs.extend(relativeTime);

/**
 * Dates are user-visible text, and dayjs holds one global locale that used
 * to be pinned to Arabic at import time — so every date in the admin read
 * "١٥ أغسطس ٢٠٢٦" even with the interface in English or Spanish.
 *
 * i18n.ts calls this on boot and on every language change. It lives here
 * rather than there because the dayjs locale bundles belong with the
 * formatting helpers; i18n.ts only says *when*, this says *what*.
 *
 * dayjs ships English built in, so only ar and es need importing.
 */
export function applyDateLocale(language: string): void {
  dayjs.locale(language === 'ar' || language === 'es' ? language : 'en');
}

/** Merge Tailwind classes safely */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}

/** Format a date in the active language, e.g. "15 August 2026". */
export function formatDate(date: string | null | undefined, format = 'D MMMM YYYY'): string {
  if (!date) return '—';
  return dayjs(date).format(format);
}

/** Format date relative to now, e.g. "3 days ago" / "منذ 3 أيام" */
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
