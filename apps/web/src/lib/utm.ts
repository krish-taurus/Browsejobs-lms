/**
 * First-touch UTM capture, persisted for attribution (spec §6.1).
 *
 * First touch, not last: the value is written once per session and read back
 * unchanged, so a visitor who arrives from an ad and later navigates to a
 * different page still attributes to the ad that brought them.
 *
 * Every lead form on the site shares this, so two forms on the same visit
 * cannot disagree about where the visitor came from.
 */
export function captureUtm(): Record<string, string> {
  try {
    const stored = sessionStorage.getItem("bj_utm");
    if (stored) return JSON.parse(stored) as Record<string, string>;

    const params = new URLSearchParams(window.location.search);
    const utm: Record<string, string> = {};
    for (const key of ["utm_source", "utm_medium", "utm_campaign"]) {
      const v = params.get(key);
      if (v) utm[key] = v;
    }
    if (Object.keys(utm).length) sessionStorage.setItem("bj_utm", JSON.stringify(utm));
    return utm;
  } catch {
    return {};
  }
}
