export interface ContestantProfile {
  id:             string;
  first_name:     string;
  last_name:      string;
  email?:         string;
  phone?:         string;
  country_code?:  string;
  created_at?:    string;
  applications?: {
    id:             string;
    season_id:      string;
    stage_id:       string;
    status:         string;
    video_hls_url?: string;
    total_score?:   number;
  }[];
  appeals?: {
    id:             string;
    reason:         string;
    status:         string;
    admin_response?:string;
  }[];
}

export interface ContestantSearchFilters {
  query?:          string;
  application_id?: string;
  phone?:          string;
  country_code?:   string;
  season_id?:      string;
  status?:         string;
}
