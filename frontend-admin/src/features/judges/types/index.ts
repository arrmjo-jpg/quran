export interface Judge {
  id:             string;
  user_id:        string;
  full_name:      string;
  specialization: string;
  title?:         string;
  bio?:           string;
  is_active:      boolean;
}
