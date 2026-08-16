import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { BookOpen, Lock, Mail, ShieldCheck } from 'lucide-react';
import { useAuth } from '@/core/auth/AuthContext';
import { authService, isMfaChallenge } from '../api/auth.service';
import Button from '@/ui/Button';
import { Input } from '@/ui/input/Input';
import { extractErrorMessage } from '@/core/api/errors';
import { toast } from 'sonner';

export default function LoginPage(): React.JSX.Element {
  const { t } = useTranslation('auth');
  const { t: tc } = useTranslation('common');
  const navigate = useNavigate();
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  // Set once login() returns mfa_required — switches the form to the
  // second step (TOTP/recovery code) instead of completing login.
  const [challengeToken, setChallengeToken] = useState<string | null>(null);
  const [mfaCode, setMfaCode] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const result = await authService.login({ email, password });

      if (isMfaChallenge(result)) {
        setChallengeToken(result.challenge_token);
        return;
      }

      login(result.token, result.user);
      toast.success(t('login_success'));
      navigate('/');
    } catch (err) {
      toast.error(extractErrorMessage(err, t('login_failed')));
    } finally {
      setLoading(false);
    }
  };

  const handleMfaSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!challengeToken) return;
    setLoading(true);

    try {
      const data = await authService.completeMfaChallenge(challengeToken, mfaCode);
      login(data.token, data.user);
      toast.success(t('login_success'));
      navigate('/');
    } catch (err) {
      toast.error(extractErrorMessage(err, t('mfa_code_invalid')));
    } finally {
      setLoading(false);
    }
  };

  if (challengeToken) {
    return (
      <div className="min-h-screen bg-slate-950 flex items-center justify-center p-4 font-sans text-slate-100">
        <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
          <div className="text-center mb-8">
            <div className="w-12 h-12 rounded-2xl bg-brand-600 flex items-center justify-center text-white mx-auto mb-3 shadow-lg shadow-brand-600/30">
              <ShieldCheck className="w-6 h-6" />
            </div>
            <h1 className="text-xl font-bold text-white">{t('mfa_title')}</h1>
            <p className="mt-1 text-xs text-slate-400">{t('mfa_subtitle')}</p>
          </div>

          <form onSubmit={handleMfaSubmit} className="space-y-4">
            <Input
              label={t('mfa_code_label')}
              placeholder="123456"
              maxLength={8}
              value={mfaCode}
              onChange={(e) => setMfaCode(e.target.value)}
            />

            <Button type="submit" isLoading={loading} className="w-full py-3 mt-2 text-sm font-semibold">
              {tc('confirm')}
            </Button>

            <button
              type="button"
              onClick={() => { setChallengeToken(null); setMfaCode(''); }}
              className="w-full text-center text-xs text-slate-400 hover:text-slate-200 mt-2"
            >
              {t('back_to_login')}
            </button>
          </form>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-950 flex items-center justify-center p-4 font-sans text-slate-100">
      <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
        <div className="text-center mb-8">
          <div className="w-12 h-12 rounded-2xl bg-brand-600 flex items-center justify-center text-white mx-auto mb-3 shadow-lg shadow-brand-600/30">
            <BookOpen className="w-6 h-6" />
          </div>
          <h1 className="text-xl font-bold text-white">{tc('app_title')}</h1>
          <p className="mt-1 text-xs text-slate-400">{tc('app_subtitle')}</p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-300 mb-1.5">{t('email_label')}</label>
            <div className="relative">
              <Mail className="w-4 h-4 text-slate-500 absolute right-3 top-3" />
              <input
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full bg-slate-950 border border-slate-800 rounded-xl pr-9 pl-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition-colors"
                placeholder="admin@quran.test"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-300 mb-1.5">{t('password_label')}</label>
            <div className="relative">
              <Lock className="w-4 h-4 text-slate-500 absolute right-3 top-3" />
              <input
                type="password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full bg-slate-950 border border-slate-800 rounded-xl pr-9 pl-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition-colors"
                placeholder="••••••••"
              />
            </div>
          </div>

          <Button type="submit" isLoading={loading} className="w-full py-3 mt-2 text-sm font-semibold">
            {t('sign_in')}
          </Button>
        </form>
      </div>
    </div>
  );
}
