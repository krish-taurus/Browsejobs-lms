/**
 * "5–8 years" when both bounds are real, "5+ years" when there's no
 * recorded ceiling — never assumed, always read straight off
 * experience_max_years being null. A 25+ record stays 25+, not silently
 * clamped to something that looks more plausible.
 */
export function formatExperience(minYears: number, maxYears: number | null): string {
  return maxYears === null ? `${minYears}+ years` : `${minYears}–${maxYears} years`;
}
