/** Standard API success envelope from Laravel ADR-014 */
export interface ApiSuccess<T> {
  success: true;
  data: T;
  message?: string;
}

/** Standard API error envelope */
export interface ApiError {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
}

/** Paginated list response */
export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page:    number;
    per_page:     number;
    total:        number;
    from:         number | null;
    to:           number | null;
  };
  links?: {
    first: string | null;
    last:  string | null;
    prev:  string | null;
    next:  string | null;
  };
}

export interface PaginatedApiSuccess<T> {
  success: true;
  data:    T[];
  meta:    PaginatedResponse<T>['meta'];
}

/** Generic select option */
export interface SelectOption {
  value: string;
  label: string;
}
