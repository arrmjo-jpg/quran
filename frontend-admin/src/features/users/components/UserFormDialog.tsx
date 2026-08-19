import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Lock, Mail } from 'lucide-react';
import { Dialog } from '@/ui/dialog/Dialog';
import Button from '@/ui/Button';
import { Input, Select } from '@/ui/input/Input';
import Badge from '@/ui/Badge';
import { useRoles } from '@/features/roles/hooks/useRoles';
import { useCreateUser, useUpdateUser } from '../hooks/useUsers';
import type { AdminUser } from '../types';

export interface UserFormDialogProps {
  isOpen: boolean;
  onClose: () => void;
  /** Null creates and invites; a user edits its display details. */
  editing: AdminUser | null;
}

/**
 * Creates an account by inviting its owner, or edits an existing one.
 *
 * THERE IS NO PASSWORD FIELD, AND THERE WILL NOT BE ONE. ADR-016 D14: the
 * person named on the account chooses their own password through the emailed
 * invitation, and no administrator ever sets it. A field here would not merely
 * be ignored by the server — it would tell the operator something untrue about
 * how this platform works.
 *
 * Editing shows neither roles nor email. Roles have their own dialog behind
 * users.assign_roles, because granting authority is not editing a name; email
 * is not changeable at all until there is a verification flow, since it is
 * both the login identity and the address an invitation was delivered to.
 */
export function UserFormDialog({ isOpen, onClose, editing }: UserFormDialogProps): React.JSX.Element {
  const { t } = useTranslation('users');
  const { t: tc } = useTranslation('common');

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [locale, setLocale] = useState('ar');
  const [roleIds, setRoleIds] = useState<string[]>([]);

  const { data: roles } = useRoles();
  const createUser = useCreateUser();
  const updateUser = useUpdateUser();

  const isEditing = editing !== null;

  useEffect(() => {
    if (!isOpen) return;
    setName(editing?.name ?? '');
    setEmail(editing?.email ?? '');
    setLocale(editing?.preferred_locale ?? 'ar');
    setRoleIds([]);
  }, [isOpen, editing]);

  const toggleRole = (id: string): void => {
    setRoleIds((current) =>
      current.includes(id) ? current.filter((held) => held !== id) : [...current, id]
    );
  };

  const submit = (e: React.FormEvent): void => {
    e.preventDefault();

    if (isEditing) {
      updateUser.mutate({ id: editing.id, name, locale }, { onSuccess: onClose });
      return;
    }

    createUser.mutate({ email, name, roles: roleIds, locale }, { onSuccess: onClose });
  };

  const isPending = createUser.isPending || updateUser.isPending;

  return (
    <Dialog
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? t('edit_title') : t('create_title')}
      className="max-w-xl"
    >
      <form onSubmit={submit} className="flex flex-col gap-4">
        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
            {t('field_name')}
          </label>
          <Input type="text" value={name} onChange={(e) => setName(e.target.value)} required />
        </div>

        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
            {t('field_email')}
          </label>
          <Input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required={!isEditing}
            disabled={isEditing}
          />
          {isEditing && <p className="text-xs text-slate-500">{t('email_immutable_hint')}</p>}
        </div>

        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
            {t('field_locale')}
          </label>
          <Select
            value={locale}
            onChange={(e) => setLocale(e.target.value)}
            options={[
              { value: 'ar', label: 'العربية' },
              { value: 'en', label: 'English' },
              { value: 'es', label: 'Español' },
            ]}
          />
        </div>

        {!isEditing && (
          <>
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
                {t('field_roles')}
              </label>
              {/* Chosen now rather than after the invitation is accepted, so
                  the server refuses an over-privileged grant while the
                  operator is still looking at this form. */}
              <div className="flex flex-col gap-1 max-h-52 overflow-y-auto">
                {(roles ?? []).map((role) => (
                  <label
                    key={role.id}
                    className="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer"
                  >
                    <input
                      type="checkbox"
                      checked={roleIds.includes(role.id)}
                      onChange={() => toggleRole(role.id)}
                      className="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                    />
                    <span className="font-mono text-sm text-slate-900 dark:text-white">{role.name}</span>
                    {role.is_system && (
                      <Badge variant="warning">
                        <Lock className="w-3 h-3" />
                        <span>{t('badge_system')}</span>
                      </Badge>
                    )}
                  </label>
                ))}
              </div>
            </div>

            <div className="flex items-start gap-2 rounded-lg border border-sky-200 dark:border-sky-900 bg-sky-50 dark:bg-sky-950/40 px-3 py-2">
              <Mail className="w-4 h-4 text-sky-600 mt-0.5 shrink-0" />
              <p className="text-xs text-sky-800 dark:text-sky-200">{t('invitation_hint')}</p>
            </div>
          </>
        )}

        <div className="flex items-center justify-end gap-2 pt-1">
          <Button type="button" variant="outline" onClick={onClose}>
            {tc('cancel')}
          </Button>
          <Button type="submit" isLoading={isPending}>
            {isEditing ? tc('save') : t('action_create_submit')}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
