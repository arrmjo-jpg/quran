export interface Judge {
  id:                     string;
  user_id:                string;
  full_name:              string;
  specialization:         string;
  title?:                 string;
  bio?:                   string;
  assigned_contestants?:  number;
  completed_evaluations?: number;
  pending_evaluations?:   number;
  avg_time_seconds?:      number;
  is_online?:             boolean;
}
