import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { STORAGE_KEYS } from '@/core/constants';
import { registerForcedLogout } from '@/core/api/http';

export interface AuthUser {
  id:    string;
  name:  string;
  email: string;
  roles: string[];
  type:  string;
}

interface AuthContextValue {
  user:     AuthUser | null;
  token:    string | null;
  isAuthed: boolean;
  login:    (token: string, user: AuthUser) => void;
  logout:   () => void;
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
    setToken(t);
    setUser(u);
  }, []);

  useEffect(() => {
    registerForcedLogout(logout);
  }, [logout]);

  return (
    <AuthContext.Provider value={{ user, token, isAuthed: Boolean(token && user), login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
