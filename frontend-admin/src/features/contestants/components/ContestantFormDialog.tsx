import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog } from '@/ui/dialog/Dialog';
import Button from '@/ui/Button';
import { Input, Select } from '@/ui/input/Input';
import { useAuth } from '@/core/auth/AuthContext';
import { useAllCountries } from '@/features/seasons/hooks/useLookups';
import { useUsers } from '@/features/users/hooks/useUsers';
import { useCreateContestant, useUpdateContestant } from '../hooks/useContestants';
import type { ContestantListItem } from '../types';

export interface ContestantFormDialogProps {
  isOpen:  boolean;
  onClose: () => void;
  /** Null registers a new contestant; a row edits an existing one. */
  editing: ContestantListItem | null;
}

/** One screenful of accounts at a time; the search box beside it narrows them. */
const ACCOUNT_PAGE_SIZE = 20;

/**
 * Registers a contestant against an existing account, or edits one.
 *
 * THERE IS NO ACCOUNT-CREATION FIELD, AND THERE WILL NOT BE ONE. ADR-016 D14:
 * this endpoint links to an account that already exists, it does not
 * provision one. A field here that minted an account would either bypass the
 * activation flow or silently duplicate Epic 1's invitation.
 *
 * Editing shows neither the account nor the country. Both are fixed at
 * registration: re-pointing a contestant at another account would move a
 * person's whole history, and the country is frozen onto their applications
 * (ADR-016 D8). The server ignores both fields on update, so offering them
 * would be offering an edit that does nothing.
 */
export function ContestantFormDialog({
  isOpen,
  onClose,
  editing,
}: ContestantFormDialogProps): React.JSX.Element {
  const { t } = useTranslation('contestants');
  const { t: tc } = useTranslation('common');
  const { user } = useAuth();

  const [userId, setUserId] = useState('');
  const [countryId, setCountryId] = useState('');
  const [fullName, setFullName] = useState('');
  const [dateOfBirth, setDateOfBirth] = useState('');
  const [gender, setGender] = useState<'male' | 'female'>('male');
  const [phoneNumber, setPhoneNumber] = useState('');
  const [nationalId, setNationalId] = useState('');
  const [accountSearch, setAccountSearch] = useState('');

  const isEditing = editing !== null;

  /**
   * The account picker needs users.view, which contestants.create does not
   * imply — data_entry holds the second and not the first (RolesSeeder). It
   * is asked for here rather than assumed, and the field degrades to the raw
   * account id when the answer is no, because hiding the field would hide the
   * one input the request cannot be built without.
   */
  const canBrowseAccounts = user?.permissions?.includes('users.view') ?? false;

  const countries = useAllCountries();
  const accounts = useUsers({
    page: 1,
    per_page: ACCOUNT_PAGE_SIZE,
    type: 'contestant',
    search: accountSearch || undefined,
  });

  const createContestant = useCreateContestant();
  const updateContestant = useUpdateContestant();

  useEffect(() => {
    if (!isOpen) return;
    setUserId('');
    setCountryId(editing?.country_id ?? '');
    setFullName(editing?.full_name ?? '');
    setDateOfBirth(editing?.date_of_birth ?? '');
    setGender(editing?.gender ?? 'male');
    setPhoneNumber(editing?.phone_number ?? '');
    setAccountSearch('');
    // Blank on purpose, even when editing. A list row carries no national_id
    // — ContestantListResource does not return one — so this field cannot be
    // prefilled, and it is only sent when the operator actually types in it.
    setNationalId('');
  }, [isOpen, editing]);

  const submit = (e: React.FormEvent): void => {
    e.preventDefault();

    if (isEditing) {
      updateContestant.mutate(
        {
          id: editing.id,
          payload: {
            full_name: fullName,
            date_of_birth: dateOfBirth,
            gender,
            phone_number: phoneNumber,
            ...(nationalId !== '' ? { national_id: nationalId } : {}),
          },
        },
        { onSuccess: onClose }
      );

      return;
    }

    createContestant.mutate(
      {
        user_id: userId,
        country_id: countryId,
        full_name: fullName,
        date_of_birth: dateOfBirth,
        gender,
        phone_number: phoneNumber,
        national_id: nationalId === '' ? null : nationalId,
      },
      { onSuccess: onClose }
    );
  };

  const isPending = createContestant.isPending || updateContestant.isPending;

  return (
    <Dialog
      isOpen={isOpen}
      onClose={onClose}
      title={isEditing ? t('form_title_edit') : t('form_title_create')}
      className="max-w-xl"
    >
      <form onSubmit={submit} className="flex flex-col gap-4">
        {!isEditing && (
          <>
            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
                {t('field_account')}
              </label>

              {canBrowseAccounts ? (
                <>
                  <Input
                    type="search"
                    value={accountSearch}
                    onChange={(e) => setAccountSearch(e.target.value)}
                    placeholder={t('account_search_placeholder')}
                  />
                  <Select
                    value={userId}
                    onChange={(e) => setUserId(e.target.value)}
                    required
                    options={[
                      { value: '', label: t('account_placeholder') },
                      ...(accounts.data?.users ?? []).map((account) => ({
                        value: account.id,
                        label: `${account.name} — ${account.email}`,
                      })),
                    ]}
                  />
                  <p className="text-xs text-slate-500">{t('account_hint')}</p>
                </>
              ) : (
                <>
                  <Input
                    value={userId}
                    onChange={(e) => setUserId(e.target.value)}
                    placeholder="00000000-0000-0000-0000-000000000000"
                    required
                  />
                  <p className="text-xs text-slate-500">{t('account_id_hint')}</p>
                </>
              )}
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
                {t('field_country')}
              </label>
              <Select
                value={countryId}
                onChange={(e) => setCountryId(e.target.value)}
                required
                options={[
                  { value: '', label: t('country_placeholder') },
                  ...(countries.data ?? []).map((country) => ({
                    value: country.id,
                    label: country.name,
                  })),
                ]}
              />
              <p className="text-xs text-slate-500">{t('country_immutable_hint')}</p>
            </div>
          </>
        )}

        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
            {t('field_full_name')}
          </label>
          <Input
            value={fullName}
            onChange={(e) => setFullName(e.target.value)}
            minLength={2}
            maxLength={255}
            required
          />
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
              {t('field_date_of_birth')}
            </label>
            <Input
              type="date"
              value={dateOfBirth}
              onChange={(e) => setDateOfBirth(e.target.value)}
              max={new Date().toISOString().slice(0, 10)}
              required
            />
          </div>

          <div className="flex flex-col gap-1.5">
            <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
              {t('field_gender')}
            </label>
            <Select
              value={gender}
              onChange={(e) => setGender(e.target.value as 'male' | 'female')}
              options={[
                { value: 'male', label: t('gender_male') },
                { value: 'female', label: t('gender_female') },
              ]}
            />
          </div>
        </div>

        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
            {t('field_phone')}
          </label>
          <Input
            value={phoneNumber}
            onChange={(e) => setPhoneNumber(e.target.value)}
            maxLength={50}
            required
          />
        </div>

        <div className="flex flex-col gap-1.5">
          <label className="text-sm font-medium text-slate-700 dark:text-slate-200">
            {t('field_national_id')}
          </label>
          <Input
            value={nationalId}
            onChange={(e) => setNationalId(e.target.value)}
            maxLength={100}
          />
          <p className="text-xs text-slate-500">
            {isEditing ? t('national_id_edit_hint') : t('national_id_hint')}
          </p>
        </div>

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
