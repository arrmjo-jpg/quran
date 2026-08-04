export interface ApplicationItem {
  id:             string;
  contestant_id: string;
  season_id:     string;
  stage_id:      string;
  status:        'submitted' | 'under_review' | 'ready_for_judging' | 'rejected' | 'modification_requested';
  created_at:    string;
  video_url?:    string;
  rejection_reason?: string;
}
