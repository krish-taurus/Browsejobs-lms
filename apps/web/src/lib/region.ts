"use client";

import { useEffect, useState } from "react";

/**
 * Which currency a visitor is quoted, in one place.
 *
 * India keeps the rupee price; everywhere else is quoted in dollars. The region
 * is read from the browser's own timezone, so the site needs no geo service in
 * front of it — the trade-off is that a VPN or a traveller reads as wherever
 * their clock is set, because a VPN changes the IP and never the clock.
 *
 * When an IP-based country header is available (Cloudflare's CF-IPCountry, say),
 * this hook is the only thing that has to change: every page asks it, none of
 * them decide for themselves.
 */

/** Registration for students outside India: one payment, no EMI plan abroad. */
export const INTL_PRICE = 600;

/**
 * The first render is always the India price, matching the server-rendered
 * HTML: that keeps hydration quiet and leaves the markup search engines index
 * in rupees, consistent with the schema.org price on the course pages.
 */
export function useIsInternational(): boolean {
  const [international, setInternational] = useState(false);

  useEffect(() => {
    try {
      const tz = Intl.DateTimeFormat().resolvedOptions().timeZone ?? "";
      setInternational(!/^Asia\/(Kolkata|Calcutta)$/.test(tz));
    } catch {
      // No Intl support — stay on the India price rather than guess.
    }
  }, []);

  return international;
}
