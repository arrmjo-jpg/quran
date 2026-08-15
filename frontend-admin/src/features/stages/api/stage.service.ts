import { http } from '@/core/api/http';
import type { ApiSuccess } from '@/core/types';
import type {
  Stage,
  CreateStagePayload,
  UpdateStagePayload,
  ReorderStagesPayload,
  StageRule,
  UpdateStageRulesPayload,
} from '../types';

export const stageService = {
  /** Already ordered by stage_number server-side. */
  async getStages(seasonId: string): Promise<Stage[]> {
    const { data } = await http.get<ApiSuccess<Stage[]>>(`/admin/seasons/${seasonId}/stages`);
    return data.data;
  },

  /** Appends to the end of the season's stages; stage_number is assigned server-side. */
  async createStage(seasonId: string, payload: CreateStagePayload): Promise<Stage> {
    const { data } = await http.post<ApiSuccess<Stage>>(`/admin/seasons/${seasonId}/stages`, payload);
    return data.data;
  },

  async updateStage(id: string, payload: UpdateStagePayload): Promise<Stage> {
    const { data } = await http.patch<ApiSuccess<Stage>>(`/admin/stages/${id}`, payload);
    return data.data;
  },

  /**
   * Hard delete, and only of an unused stage on an unfrozen season. The API
   * answers 409 STAGE_IN_USE once anything references it — there is no soft
   * delete to fall back on.
   */
  async deleteStage(id: string): Promise<void> {
    await http.delete(`/admin/stages/${id}`);
  },

  /** Takes the season's complete ordering; a partial list is a 422. */
  async reorderStages(seasonId: string, payload: ReorderStagesPayload): Promise<Stage[]> {
    const { data } = await http.put<ApiSuccess<Stage[]>>(
      `/admin/seasons/${seasonId}/stages/order`,
      payload,
    );
    return data.data;
  },

  /** The rules currently stored, ordered by the stages' stage_number. */
  async getStageRules(seasonId: string): Promise<StageRule[]> {
    const { data } = await http.get<ApiSuccess<StageRule[]>>(`/admin/seasons/${seasonId}/stage-rules`);
    return data.data;
  },

  /**
   * Replaces the season's whole rule set. Every stage must appear exactly
   * once, so callers build the payload from the stage list rather than from
   * whatever the admin happened to touch.
   */
  async updateStageRules(seasonId: string, payload: UpdateStageRulesPayload): Promise<StageRule[]> {
    const { data } = await http.patch<ApiSuccess<StageRule[]>>(
      `/admin/seasons/${seasonId}/stage-rules`,
      payload,
    );
    return data.data;
  },
};
