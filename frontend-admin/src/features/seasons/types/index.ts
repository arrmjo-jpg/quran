export interface Season {
  id:                 string;
  slug:               string;
  year:               number;
  registration_start: string;
  registration_end:   string;
  start_date?:        string;
  end_date?:          string;
  status:             'draft' | 'registration_open' | 'registration_closed' | 'active' | 'completed' | 'archived';
  is_active:          boolean;
  created_at?:        string;
}

export interface CreateSeasonPayload {
  year:               number;
  slug:               string;
  registration_start: string;
  registration_end:   string;
  start_date:         string;
  end_date:           string;
  title_ar:           string;
  title_en:           string;
}

export interface SeasonFilters {
  status?: string;
  year?:   number;
  search?: string;
}
