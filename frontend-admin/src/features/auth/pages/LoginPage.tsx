import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { BookOpen, Lock, Mail } from 'lucide-react';
import { useAuth } from '@/core/auth/AuthContext';
import { authService } from '../api/auth.service';
import Button from '@/ui/Button';
import { extractErrorMessage } from '@/core/api/errors';
import { toast } from 'sonner';

export default function LoginPage(): React.JSX.Element {
  const navigate = useNavigate();
  const { login } = useAuth();
  const [email, setEmail] = useState('admin@quran.test');
  const [password, setPassword] = useState('Pass123!');
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const data = await authService.login({ email, password });
      login(data.token, data.user);
      toast.success('تم تسجيل الدخول بنجاح');
      navigate('/');
    } catch (err) {
      toast.error(extractErrorMessage(err, 'فشل تسجيل الدخول. تحقق من البيانات.'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 flex items-center justify-center p-4 font-sans text-slate-100" dir="rtl">
      <div className="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl">
        <div className="text-center mb-8">
          <div className="w-12 h-12 rounded-2xl bg-brand-600 flex items-center justify-center text-white mx-auto mb-3 shadow-lg shadow-brand-600/30">
            <BookOpen className="w-6 h-6" />
          </div>
          <h1 className="text-xl font-bold text-white">منصة مسابقات القرآن الكريم</h1>
          <p className="text-xs text-slate-400 mt-1">لوحة الإدارة المركزية</p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-300 mb-1.5">البريد الإلكتروني</label>
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
            <label className="block text-xs font-medium text-slate-300 mb-1.5">كلمة المرور</label>
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
            تسجيل الدخول
          </Button>
        </form>
      </div>
    </div>
  );
}
