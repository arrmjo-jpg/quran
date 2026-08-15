import React, { useEffect, useMemo, useState } from 'react';
import { Dialog } from '@/ui/dialog/Dialog';
import { Select, Input } from '@/ui/input/Input';
import Button from '@/ui/Button';
import Badge from '@/ui/Badge';
import Spinner from '@/ui/Spinner';
import { useStages, useStageRules, useUpdateStageRules } from '../hooks/useStages';
import { useJudgeScoreSystems } from '@/features/seasons/hooks/useLookups';
import type { Stage, StageRule, StageRuleAssignment, StageType } from '../types';
import type { LookupOption } from '@/features/seasons/types';
import { AlertCircle } from 'lucide-react';

export interface StageRulesDialogProps {
  seasonId:   string | null;
  seasonYear: number | null;
  isFrozen:   boolean;
  onClose:    () => void;
}

const TYPE_LABEL: Record<StageType, string> = {
  preliminary: 'تمهيدية',
  semi_final: 'نصف نهائية',
  final: 'نهائية',
};

/** What the admin is editing for one stage, before it becomes a payload. */
interface RuleDraft {
  judge_score_system_id: string;
  /** Kept as a string so the field can be genuinely empty, meaning "no threshold". */
  qualification_percentage: string;
}

function labelOf(option: LookupOption): string {
  return option.name.ar ?? option.name.en ?? option.code;
}

function stageName(stage: Stage): string {
  return stage.translations.ar?.name ?? stage.name ?? '—';
}

/**
 * Builds the editable state by looking each stage's rule up by stage_id.
 *
 * This is the whole safety property of this screen. season_stage_rules has
 * no ordering column — the API sorts the rules by each stage's
 * stage_number when reading — so pairing rules[i] with stages[i] would
 * appear to work until someone reorders the stages, at which point every
 * rule would silently attach to the wrong stage. Keying on stage_id makes
 * that class of bug unrepresentable.
 */
function buildDrafts(stages: Stage[], rules: StageRule[]): Record<string, RuleDraft> {
  const byStageId = new Map(rules.map((rule) => [rule.stage_id, rule]));

  const drafts: Record<string, RuleDraft> = {};

  for (const stage of stages) {
    const rule = byStageId.get(stage.id);

    drafts[stage.id] = {
      judge_score_system_id: rule?.judge_score_system.id ?? '',
      qualification_percentage:
        rule?.qualification_percentage === null || rule?.qualification_percentage === undefined
          ? ''
          : String(rule.qualification_percentage),
    };
  }

  return drafts;
}

export function StageRulesDialog({
  seasonId,
  seasonYear,
  isFrozen,
  onClose,
}: StageRulesDialogProps): React.JSX.Element | null {
  const [drafts, setDrafts] = useState<Record<string, RuleDraft>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});

  const stages = useStages(seasonId);
  const rules = useStageRules(seasonId);
  const scoreSystems = useJudgeScoreSystems();
  const updateRules = useUpdateStageRules(seasonId ?? '');

  useEffect(() => {
    if (stages.data && rules.data) {
      setDrafts(buildDrafts(stages.data, rules.data));
      setErrors({});
    }
  }, [stages.data, rules.data]);

  const scoreSystemById = useMemo(
    () => new Map((scoreSystems.data ?? []).map((s) => [s.id, s])),
    [scoreSystems.data],
  );

  if (!seasonId) return null;

  const loading = stages.isLoading || rules.isLoading || scoreSystems.isLoading;
  const failed = stages.isError || rules.isError || scoreSystems.isError;
  const orderedStages = stages.data ?? [];

  const setDraft = (stageId: string, patch: Partial<RuleDraft>) => {
    setDrafts((prev) => ({ ...prev, [stageId]: { ...prev[stageId], ...patch } }));
  };

  const validate = (): StageRuleAssignment[] | null => {
    const nextErrors: Record<string, string> = {};

    // Built from the stage list, never from the drafts object: the API
    // requires every stage covered exactly once, so the stages are what
    // define the payload.
    const payload: StageRuleAssignment[] = orderedStages.map((stage) => {
      const draft = drafts[stage.id] ?? { judge_score_system_id: '', qualification_percentage: '' };

      if (draft.judge_score_system_id === '') {
        nextErrors[stage.id] = 'يرجى اختيار نظام الدرجات لهذه المرحلة';
      }

      let percentage: number | null = null;

      if (draft.qualification_percentage.trim() !== '') {
        const parsed = Number(draft.qualification_percentage);

        if (Number.isNaN(parsed) || parsed < 0 || parsed > 100) {
          nextErrors[stage.id] = 'نسبة التأهل يجب أن تكون رقماً بين 0 و 100';
        } else {
          percentage = parsed;
        }
      }

      return {
        stage_id: stage.id,
        judge_score_system_id: draft.judge_score_system_id,
        qualification_percentage: percentage,
      };
    });

    setErrors(nextErrors);

    return Object.keys(nextErrors).length > 0 ? null : payload;
  };

  const save = () => {
    const payload = validate();
    if (!payload) return;

    updateRules.mutate(payload, { onSuccess: onClose });
  };

  return (
    <Dialog isOpen onClose={onClose} title={`قواعد تحكيم مراحل موسم ${seasonYear ?? ''}`} className="max-w-3xl">
      {loading ? (
        <div className="flex items-center justify-center py-10">
          <Spinner />
        </div>
      ) : failed ? (
        <p className="p-3 text-xs border rounded-xl bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-900/50 text-rose-700 dark:text-rose-300">
          تعذر تحميل المراحل أو قواعدها أو أنظمة الدرجات. لا يمكن ضبط القواعد قبل تحميلها — أغلق
          النافذة وأعد فتحها للمحاولة.
        </p>
      ) : orderedStages.length === 0 ? (
        <p className="p-6 text-xs text-center border border-dashed rounded-xl border-slate-200 dark:border-slate-800 text-slate-500">
          لا توجد مراحل لهذا الموسم بعد. أضف المراحل أولاً من شاشة المراحل، ثم عُد لضبط قواعد تحكيمها.
        </p>
      ) : (
        <div className="space-y-4">
          {isFrozen && (
            <div className="flex items-start gap-3 p-3 text-xs leading-relaxed border rounded-xl bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900/50 text-amber-800 dark:text-amber-300">
              <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
              <p>
                هذا الموسم مجمّد. القواعد معروضة للاطلاع فقط، وسيرفض الخادم أي حفظ.
              </p>
            </div>
          )}

          <p className="p-3 text-[11px] leading-relaxed border rounded-xl bg-sky-50 dark:bg-sky-950/30 border-sky-200 dark:border-sky-900/50 text-sky-800 dark:text-sky-300">
            لكل مرحلة قاعدة واحدة إلزامية. اترك نسبة التأهل فارغة إذا كانت المرحلة ترتّب المتسابقين
            دون إقصاء أحد — وهو الحال المعتاد للمرحلة النهائية.
          </p>

          {/* Rendered in stage_number order, but each row is bound by
              stage.id — the position in this list never identifies a rule. */}
          <ul className="space-y-3">
            {orderedStages.map((stage) => {
              const draft = drafts[stage.id] ?? { judge_score_system_id: '', qualification_percentage: '' };
              const selectedSystem = scoreSystemById.get(draft.judge_score_system_id);
              const percentage = Number(draft.qualification_percentage);

              const requiredScore =
                selectedSystem?.max_score !== undefined &&
                draft.qualification_percentage.trim() !== '' &&
                !Number.isNaN(percentage)
                  ? Math.round(selectedSystem.max_score * (percentage / 100) * 100) / 100
                  : null;

              return (
                <li key={stage.id} className="p-3 space-y-3 border rounded-xl border-slate-200 dark:border-slate-800">
                  <div className="flex items-center gap-2">
                    <span className="flex items-center justify-center text-xs font-semibold rounded-lg w-7 h-7 shrink-0 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                      {stage.stage_number}
                    </span>
                    <span className="text-xs font-semibold text-slate-900 dark:text-white">
                      {stageName(stage)}
                    </span>
                    <Badge variant="neutral">{TYPE_LABEL[stage.type]}</Badge>
                  </div>

                  <div className="grid grid-cols-3 gap-3">
                    <Select
                      label="نظام الدرجات"
                      value={draft.judge_score_system_id}
                      disabled={isFrozen}
                      onChange={(e) => setDraft(stage.id, { judge_score_system_id: e.target.value })}
                      options={[
                        { value: '', label: '— اختر —' },
                        ...(scoreSystems.data ?? []).map((s) => ({
                          value: s.id,
                          label: `${labelOf(s)} (${s.max_score ?? '؟'})`,
                        })),
                      ]}
                    />

                    <Input
                      label="نسبة التأهل %"
                      type="number"
                      min={0}
                      max={100}
                      placeholder="بدون إقصاء"
                      disabled={isFrozen}
                      value={draft.qualification_percentage}
                      onChange={(e) => setDraft(stage.id, { qualification_percentage: e.target.value })}
                    />

                    <div className="w-full space-y-1">
                      <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                        الدرجة المطلوبة
                      </label>
                      <p className="px-3 py-2 text-xs border rounded-xl bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300">
                        {requiredScore === null ? 'لا يوجد حد للإقصاء' : requiredScore}
                      </p>
                    </div>
                  </div>

                  {errors[stage.id] && <p className="text-[11px] text-rose-500">{errors[stage.id]}</p>}
                </li>
              );
            })}
          </ul>

          <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
            <Button variant="secondary" size="sm" onClick={onClose} disabled={updateRules.isPending}>
              إغلاق
            </Button>
            {!isFrozen && (
              <Button variant="primary" size="sm" isLoading={updateRules.isPending} onClick={save}>
                حفظ قواعد المراحل
              </Button>
            )}
          </div>
        </div>
      )}
    </Dialog>
  );
}
