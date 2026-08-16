import React, { useState } from 'react';
import { Dialog } from '@/ui/dialog/Dialog';
import Button from '@/ui/Button';
import { Input } from '@/ui/input/Input';
import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import { ShieldCheck, Key, Copy, Check } from 'lucide-react';
import { toast } from 'sonner';

export interface TotpMfaModalProps {
  isOpen:  boolean;
  onClose: () => void;
}

export function TotpMfaModal({ isOpen, onClose }: TotpMfaModalProps): React.JSX.Element | null {
  const [step, setStep] = useState<'setup' | 'recovery'>('setup');
  const [secret, setSecret] = useState('');
  const [qrUrl, setQrUrl] = useState('');
  const [code, setCode] = useState('');
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);

  const handleStartSetup = async () => {
    setLoading(true);
    try {
      const { data } = await http.post<ApiSuccess<{ secret: string; qr_code_url: string }>>('/admin/auth/mfa/setup');
      setSecret(data.data.secret);
      setQrUrl(data.data.qr_code_url);
    } catch {
      toast.error('فشل جلب مفتاح التوثيق الثنائي.');
    } finally {
      setLoading(false);
    }
  };

  const handleVerify = async () => {
    if (code.length !== 6) {
      toast.error('رمز التوثيق يتكون من 6 أرقام');
      return;
    }

    setLoading(true);
    try {
      const { data } = await http.post<ApiSuccess<{ recovery_codes: string[] }>>('/admin/auth/mfa/verify', {
        secret,
        code,
      });
      setRecoveryCodes(data.data.recovery_codes);
      setStep('recovery');
      toast.success('تم تفعيل التوثيق الثنائي (Google Authenticator) بنجاح!');
    } catch {
      toast.error('رمز التوثيق 6-أرقام غير صحيح. حاول مجدداً.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog isOpen={isOpen} onClose={onClose} title="إعداد التوثيق الثنائي (TOTP MFA)">
      <div className="space-y-4 text-xs text-start">
        {step === 'setup' && (
          <>
            {!secret ? (
              <div className="text-center py-6 space-y-4">
                <ShieldCheck className="w-12 h-12 text-brand-600 mx-auto" />
                <p className="text-slate-600 dark:text-slate-300">
                  تفعيل التوثيق الثنائي لحماية حساب الإدارة عبر تطبيقات مثل Google Authenticator أو Authy.
                </p>
                <Button isLoading={loading} onClick={handleStartSetup} className="mx-auto">
                  بدء إعداد TOTP MFA
                </Button>
              </div>
            ) : (
              <div className="space-y-4">
                <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 text-center space-y-2">
                  <p className="font-semibold text-slate-900 dark:text-white">مسح رمز الـ QR عبر تطبيق Authenticator:</p>
                  <p className="font-mono text-xs text-brand-600 dark:text-brand-400 font-bold bg-white dark:bg-slate-900 p-2 rounded-lg border border-slate-200 dark:border-slate-700 select-all">
                    {secret}
                  </p>
                </div>

                <Input
                  label="أدخل رمز الـ 6 أرقام المولّد من التطبيق"
                  placeholder="123456"
                  maxLength={6}
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                />

                <div className="flex justify-end gap-2 pt-2">
                  <Button variant="secondary" onClick={onClose}>إلغاء</Button>
                  <Button isLoading={loading} onClick={handleVerify}>تأكيد وتفعيل MFA</Button>
                </div>
              </div>
            )}
          </>
        )}

        {step === 'recovery' && (
          <div className="space-y-4">
            <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 rounded-xl text-emerald-900 dark:text-emerald-200">
              <span className="font-bold block mb-1">تم التفعيل بنجاح! احفظ رموز الاسترجاع الثمانية (Recovery Codes):</span>
              <p className="text-[11px] text-emerald-700 dark:text-emerald-300">
                في حال فقدان جهازك، يمكنك استخدام أي من هذه الرموز الثمانية لمرة واحدة فقط للدخول.
              </p>
            </div>

            <div className="grid grid-cols-2 gap-2 bg-slate-950 p-4 rounded-xl font-mono text-center text-amber-400 text-sm border border-slate-800">
              {recoveryCodes.map((c, i) => (
                <div key={i} className="p-1.5 bg-slate-900 rounded-lg border border-slate-800">{c}</div>
              ))}
            </div>

            <div className="flex justify-end">
              <Button onClick={onClose}>إغلاق وحفظ</Button>
            </div>
          </div>
        )}
      </div>
    </Dialog>
  );
}
