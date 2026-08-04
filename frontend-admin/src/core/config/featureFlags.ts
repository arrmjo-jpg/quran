export const FEATURE_FLAGS = {
  ENABLE_SEASONS:      true,
  ENABLE_CONTESTANTS:   true,
  ENABLE_JUDGES:        true,
  ENABLE_APPLICATIONS:  true,
  ENABLE_EVALUATIONS:   true,
  ENABLE_MEDIA:         true,
  ENABLE_VIDEOS:        true,
  ENABLE_STREAMING:     true,
  ENABLE_SPONSORS:      true,
  ENABLE_CONTENT:       true,
  ENABLE_NOTIFICATIONS: true,
  ENABLE_REPORTS:       true,
  ENABLE_SEARCH:        true,
  ENABLE_COUNTRIES:     true,
} as const;

export type FeatureFlagKey = keyof typeof FEATURE_FLAGS;
