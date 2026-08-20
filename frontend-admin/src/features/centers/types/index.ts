/**
 * Mirrors CenterResource — ADR-016 D7.
 *
 * A centre owns its location in full. Circles belong to one and duplicate none
 * of it, so this is the only shape in the admin panel that carries an address.
 */
export interface Coordinates {
  latitude:  number;
  longitude: number;
}

export interface Center {
  id:          string;
  name:        string;
  country_id:  string;
  city:        string;
  address:     string;
  /**
   * A nested pair, never two sibling keys — matching the Coordinates value
   * object on the server. Latitude without longitude is not half a location,
   * and a client that received them separately could render one.
   */
  coordinates: Coordinates | null;
  created_at:  string | null;
}

export interface CenterListFilters {
  page:        number;
  per_page:    number;
  search?:     string;
  country_id?: string;
}

export interface CenterListResult {
  centers: Center[];
  total:   number;
}

export interface CreateCenterPayload {
  name:       string;
  country_id: string;
  city:       string;
  address:    string;
  latitude?:  number | null;
  longitude?: number | null;
}

/**
 * No country_id, and its absence is the contract rather than an oversight.
 * A centre cannot change country — SaveCenterRequest marks the field
 * `prohibited` on update, so sending it is a 422 rather than a silent discard.
 */
export interface UpdateCenterPayload {
  id:         string;
  name:       string;
  city:       string;
  address:    string;
  latitude?:  number | null;
  longitude?: number | null;
}
