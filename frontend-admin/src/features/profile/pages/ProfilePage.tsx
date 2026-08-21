import React, { useEffect, useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { zodResolver } from '@hookform/resolvers/zod';
import { PageLayout } from '@/ui/page-layout/PageLayout';
import { ErrorState } from '@/ui/error-state/ErrorState';
import { Input, Textarea } from '@/ui/input/Input';
import { Card } from '@/ui/Card';
import Badge from '@/ui/Badge';
import Button from '@/ui/Button';
import Spinner from '@/ui/Spinner';
import { useMyAccount, useUpdateMyProfile } from '../hooks/useMyProfile';
import { makeProfileSchema, type ProfileFormValues } from '../schemas/profile.schema';
import { SOCIAL_PLATFORMS, type SocialLinks } from '../types';

/**
 * The signed-in administrator's own profile.
 *
 * NO PERMISSION GATE, and `/profile` is deliberately absent from
 * ROUTE_PERMISSIONS. It shows one account's own data and nothing else, which
 * is the same reason `/sessions` is open: gating it behind users.update would
 * mean a role could be edited into one that cannot correct its own name.
 *
 * The avatar is shown but not editable. Setting one needs an upload, uploading
 * needs `media.create`, and self-service does not carry it — so Story 3 reads
 * the column and Story 4 renders what it finds rather than inventing a control
 * the server would refuse.
 */
export default function ProfilePage(): React.JSX.Element {
  const { t } = useTranslation('profile');
  const { t: tc } = useTranslation('common');

  const { data: account, isLoading, isError, refetch } = useMyAccount();
  const updateProfile = useUpdateMyProfile();

  const schema = useMemo(() => makeProfileSchema(t), [t]);

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isDirty },
  } = useForm<ProfileFormValues>({ resolver: zodResolver(schema) });

  // Refilled whenever the server's copy changes, so a save leaves the form
  // showing what was actually stored rather than what was typed.
  useEffect(() => {
    if (!account) return;

    reset({
      name: account.name,
      display_name: account.profile.display_name ?? '',
      bio: account.profile.bio ?? '',
      social_links: Object.fromEntries(
        SOCIAL_PLATFORMS.map((platform) => [platform, account.profile.social_links?.[platform] ?? ''])
      ) as ProfileFormValues['social_links'],
    });
  }, [account, reset]);

  const onSubmit = (values: ProfileFormValues): void => {
    // An empty field is sent as null, which the server reads as "clear this",
    // while a field never touched still arrives — the form always submits the
    // whole shape. Partial-update semantics matter on the wire, not here.
    const links: SocialLinks = {};

    for (const platform of SOCIAL_PLATFORMS) {
      const value = values.social_links[platform]?.trim();
      // Only platforms with a link carry a key. Sending `"facebook": ""` would
      // be refused, and sending null would contradict Q5's rule that an unset
      // platform is simply absent.
      if (value) links[platform] = value;
    }

    updateProfile.mutate({
      name: values.name,
      profile: {
        display_name: values.display_name?.trim() || null,
        bio: values.bio?.trim() || null,
        social_links: links,
      },
    });
  };

  if (isError) {
    return (
      <PageLayout title={t('title')} subtitle={t('subtitle')}>
        <ErrorState onRetry={() => void refetch()} />
      </PageLayout>
    );
  }

  if (isLoading || !account) {
    return (
      <PageLayout title={t('title')} subtitle={t('subtitle')}>
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </PageLayout>
    );
  }

  return (
    <PageLayout
      title={t('title')}
      subtitle={t('subtitle')}
      breadcrumbs={[{ label: tc('home'), href: '/' }, { label: t('title') }]}
    >
      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4 max-w-3xl">
        <Card>
          <div className="space-y-4">
            <h2 className="text-sm font-semibold text-slate-900 dark:text-white">{t('section_account')}</h2>

            <Input label={t('field_name')} {...register('name')} error={errors.name?.message} />

            {/* Read-only: the email identifies the account and changing it is
                not something this endpoint offers. Shown because a profile
                screen that hid it would send people looking elsewhere. */}
            <Input label={t('field_email')} value={account.email} readOnly disabled />

            <div className="flex flex-wrap items-center gap-2">
              <span className="text-xs text-slate-500">{t('field_roles')}</span>
              {account.roles.length === 0 ? (
                <span className="text-xs text-slate-400">{t('no_roles')}</span>
              ) : (
                account.roles.map((role) => (
                  <Badge key={role} variant="neutral">
                    <span className="font-mono text-[11px]">{role}</span>
                  </Badge>
                ))
              )}
            </div>
          </div>
        </Card>

        <Card>
          <div className="space-y-4">
            <h2 className="text-sm font-semibold text-slate-900 dark:text-white">{t('section_profile')}</h2>

            <Input
              label={t('field_display_name')}
              placeholder={account.name}
              {...register('display_name')}
              error={errors.display_name?.message}
            />
            <p className="text-[11px] text-slate-500">{t('display_name_hint')}</p>

            <Textarea label={t('field_bio')} {...register('bio')} error={errors.bio?.message} />

            <div className="flex items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
              <span className="text-xs text-slate-500">{t('field_avatar')}</span>
              <span className="text-xs text-slate-400 font-mono dir-ltr">
                {account.profile.avatar_media_id ?? t('avatar_none')}
              </span>
            </div>
            <p className="text-[11px] text-slate-500">{t('avatar_readonly_hint')}</p>
          </div>
        </Card>

        <Card>
          <div className="space-y-4">
            <h2 className="text-sm font-semibold text-slate-900 dark:text-white">{t('section_links')}</h2>
            <p className="text-[11px] text-slate-500">{t('links_hint')}</p>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {SOCIAL_PLATFORMS.map((platform) => (
                <Input
                  key={platform}
                  label={t(`platform_${platform}`)}
                  placeholder={t(`placeholder_${platform}`)}
                  className="dir-ltr"
                  {...register(`social_links.${platform}` as const)}
                  error={errors.social_links?.[platform]?.message}
                />
              ))}
            </div>
          </div>
        </Card>

        <div className="flex items-center justify-end gap-2">
          <Button
            variant="secondary"
            size="sm"
            type="button"
            disabled={!isDirty || updateProfile.isPending}
            onClick={() => void refetch()}
          >
            {tc('cancel')}
          </Button>
          <Button variant="primary" size="sm" type="submit" isLoading={updateProfile.isPending}>
            {t('save')}
          </Button>
        </div>
      </form>
    </PageLayout>
  );
}
