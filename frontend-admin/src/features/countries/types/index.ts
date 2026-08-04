export interface Country {
  id:         string;
  iso2:       string;
  iso3:       string;
  phone_code: string;
  flag_url?:  string;
  is_active:  boolean;
  name_ar?:   string;
  name_en?:   string;
}

export interface CreateCountryPayload {
  iso2:       string;
  iso3:       string;
  phone_code: string;
  name_ar:    string;
  name_en:    string;
  flag_url?:  string;
}
