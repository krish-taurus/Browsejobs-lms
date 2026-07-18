import type { NextConfig } from "next";

/** The element type Next expects from `redirects()` — derived, so no internal import. */
type Redirect = Awaited<ReturnType<NonNullable<NextConfig["redirects"]>>>[number];

/**
 * Legacy → new 301 map for the browsejobs.ai cutover (PRD P4.10). The old site is
 * PHP with `.php` URLs; these preserve link equity and inbound ad/organic traffic.
 *
 * This seed covers the predictable patterns. COMPLETE IT from the real crawl:
 * run the crawl in docs/cutover-runbook.md, then add every legacy URL that has no
 * 1:1 match here so nothing 404s on launch day. `permanent: true` emits a 301.
 */
export const legacyRedirects: Redirect[] = [
  // Home + common entry points.
  { source: "/index.php", destination: "/", permanent: true },
  { source: "/home.php", destination: "/", permanent: true },
  { source: "/index.html", destination: "/", permanent: true },

  // Course catalogue.
  { source: "/courses.php", destination: "/courses", permanent: true },
  { source: "/course.php", has: [{ type: "query", key: "slug" }], destination: "/courses/:slug", permanent: true },
  { source: "/course/:slug.php", destination: "/courses/:slug", permanent: true },
  { source: "/courses/:slug.php", destination: "/courses/:slug", permanent: true },

  // Funnel: everything old that meant "book the free masterclass" lands on register.
  { source: "/masterclass.php", destination: "/register", permanent: true },
  { source: "/register.php", destination: "/register", permanent: true },
  { source: "/enroll.php", destination: "/register", permanent: true },

  // Legal / policy pages.
  { source: "/privacy.php", destination: "/privacy-policy", permanent: true },
  { source: "/privacy-policy.php", destination: "/privacy-policy", permanent: true },
  { source: "/terms.php", destination: "/terms", permanent: true },
  { source: "/terms-and-conditions.php", destination: "/terms", permanent: true },
  { source: "/refund.php", destination: "/refund-policy", permanent: true },
  { source: "/refund-policy.php", destination: "/refund-policy", permanent: true },

  // Social proof.
  { source: "/reviews.php", destination: "/reviews", permanent: true },
  { source: "/testimonials.php", destination: "/reviews", permanent: true },

  // About/contact had no standalone route in the new IA — fold into the landing page.
  { source: "/about.php", destination: "/#proof", permanent: true },
  { source: "/about-us.php", destination: "/#proof", permanent: true },
  { source: "/contact.php", destination: "/#contact", permanent: true },
];

/**
 * Host canonicalisation: browsejobs.in and any www host 301 to the apex .ai
 * domain (PRD §14.1 — browsejobs.in 301s to browsejobs.ai). Path is preserved.
 */
export const hostRedirects: Redirect[] = [
  {
    source: "/:path*",
    has: [{ type: "host", value: "browsejobs.in" }],
    destination: "https://browsejobs.ai/:path*",
    permanent: true,
  },
  {
    source: "/:path*",
    has: [{ type: "host", value: "www.browsejobs.in" }],
    destination: "https://browsejobs.ai/:path*",
    permanent: true,
  },
  {
    source: "/:path*",
    has: [{ type: "host", value: "www.browsejobs.ai" }],
    destination: "https://browsejobs.ai/:path*",
    permanent: true,
  },
];

export const cutoverRedirects: Redirect[] = [...hostRedirects, ...legacyRedirects];
