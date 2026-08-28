import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { STORAGE_KEYS } from '@/core/constants';
import { registerForcedLogout } from '@/core/api/http';
import { reconcileLanguage } from '@/core/i18n/accountLanguage';

export interface AuthUser {
  id:    string;
  name:  string;
  email: string;
  /**
   * What this account IS. For display only — a badge, a profile line, a
   * column in the users screen. Never the basis of a show/hide decision:
   * a role name says nothing about what the server will actually permit,
   * and checking one here is how the panel drifts away from the API.
   */
  roles: string[];
  /**
   * What this account MAY DO, resolved by the server from those roles.
   * This is the only thing a UI decision may consult.
   */
  permissions: string[];
  type:  string;
  /**
   * The account's language — ADR-019 D2.
   *
   * Carried on every single-account auth response, so a login or a restored
   * session can bring the interface in line without a request of its own.
   * Optional because an account stored by an older build will not have it,
   * and reconcileLanguage() treats absent as "leave the browser alone".
   */
  preferred_locale?: string;
  /**
   * Whether THIS account carries a second factor — ADR-018 D4.
   *
   * A fact about the signed-in account itself, not about anyone else: the
   * server sends it only on single-account auth responses. Nothing gates a
   * screen on it; the security page uses it to know which buttons apply.
   */
  mfa_enabled?: boolean;
}

interface AuthContextValue {
  user:     AuthUser | null;
  token:    string | null;
  isAuthed: boolean;
  login:    (token: string, user: AuthUser) => void;
  logout:   () => void;
  /**
   * Patch the signed-in account in place — ADR-019 D3.
   *
   * The stored user is a SNAPSHOT taken at login. Anything that changes the
   * account afterwards must update it, or the snapshot starts lying: the
   * language reconciliation reads `preferred_locale` from here on reload, so
   * a stale copy does not merely fail to apply the new language, it reverts
   * to the old one on every reload.
   */
  updateUser: (patch: Partial<AuthUser>) => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }): React.JSX.Element {
  const [token, setToken] = useState<string | null>(() => localStorage.getItem(STORAGE_KEYS.token));
  const [user, setUser]   = useState<AuthUser | null>(() => {
    const raw = localStorage.getItem(STORAGE_KEYS.user);
    return raw ? (JSON.parse(raw) as AuthUser) : null;
  });

  const logout = useCallback(() => {
    localStorage.removeItem(STORAGE_KEYS.token);
    localStorage.removeItem(STORAGE_KEYS.user);
    setToken(null);
    setUser(null);
  }, []);

  const login = useCallback((t: string, u: AuthUser) => {
    localStorage.setItem(STORAGE_KEYS.token, t);
    localStorage.setItem(STORAGE_KEYS.user, JSON.stringify(u));

    // ADR-019 D3. Both login paths funnel through here -- the password one
    // and the MFA challenge -- which matters, because the first response of
    // an MFA login carries no user at all. Reconciling at the point the
    // account becomes KNOWN, rather than at the point login was attempted,
    // is what makes this correct for accounts that carry a second factor.
    reconcileLanguage(u.preferred_locale);
    setToken(t);
    setUser(u);
  }, []);

  const updateUser = useCallback((patch: Partial<AuthUser>) => {
    setUser((current) => {
      if (current === null) {
        return current;
      }

      const next = { ...current, ...patch };
      localStorage.setItem(STORAGE_KEYS.user, JSON.stringify(next));

      return next;
    });
  }, []);

  useEffect(() => {
    registerForcedLogout(logout);
  }, [logout]);

  // A reload has no login response to read, but the stored user object does
  // carry preferred_locale -- so a restored session follows the account
  // without a request. Runs once: after this, the switcher and login() own
  // the language, and re-running on every user change would fight a choice
  // the operator just made.
  useEffect(() => {
    reconcileLanguage(user?.preferred_locale);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <AuthContext.Provider value={{ user, token, isAuthed: Boolean(token && user), login, logout, updateUser }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
