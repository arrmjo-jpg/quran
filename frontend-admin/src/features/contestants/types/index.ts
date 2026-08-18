export interface ContestantProfile {
  id:             string;
  full_name:      string;
  country_id:     string;
  date_of_birth?: string;
  gender?:        string;
  phone_number?:  string;
  national_id?:   string;
  created_at?:    string;
  profile_completeness?: {
    completeness_percent: number;
    is_complete:           boolean;
    missing_fields:        string[];
  };
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
