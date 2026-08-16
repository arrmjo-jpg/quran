import type { Stage, Locale } from '../types';

/** Tried in order once the active language and the server's own name fail. */
const FALLBACK_ORDER: Locale[] = ['ar', 'en', 'es'];

/**
 * A stage's display name in the active language.
 *
 * The fallback chain matters more than it looks. StageResource returns
 * `name` as the server-side localised value and `translations` as the full
 * map, but the admin client never sends Accept-Language, so `name` comes
 * back null; and a stage may legitimately have only its Arabic name filled
 * in, because the API requires one translation on create and only checks
 * for all three when the season freezes.
 *
 * So neither `translations[language]` nor `name` can be the last step: a
 * chain that ends there renders "—" for a stage that plainly has a name,
 * which is worse than showing it in another language. It walks the
 * remaining locales instead, and only gives up when there is truly nothing.
 *
 * Reading translations.ar first — what this replaced — was the opposite
 * mistake: never empty, but never anything but Arabic either.
 */
export function stageNameIn(stage: Stage, language: string): string {
  const translations = stage.translations as Partial<Record<string, { name?: string }>>;

  const preferred = translations[language]?.name;
  if (preferred) return preferred;

  if (stage.name) return stage.name;

  for (const locale of FALLBACK_ORDER) {
    const name = translations[locale]?.name;
    if (name) return name;
  }

  return '—';
}
