export interface ApplicationItem {
  id:                string;
  contestant_id:     string;
  season_id:         string;
  stage_id:          string;
  status:            'draft' | 'submitted' | 'reupload_requested' | 'under_review' | 'ready_for_judging' | 'under_judging' | 'qualified' | 'waitlisted' | 'eliminated' | 'disqualified' | 'published';
  submitted_at?:      string;
  video_media_asset_id?: string;
  video_url?:        string;
  reupload_reason?:  string;
}
