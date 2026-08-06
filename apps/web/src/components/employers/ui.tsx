import type { ReactNode } from "react";
import { ScrollReveal } from "@/components/motion/ScrollReveal";

/**
 * Surface primitives for /for-employers.
 *
 * The page is a marketing page, so it lives in the light system (paper, ink,
 * trust, line) — but it is grading itself the way the employer portal does:
 * trust blue paired with the module's violet, and ink panels wherever the
 * page is showing the product, so a screenshot-shaped block on the marketing
 * site is literally the colour of the workspace it is selling.
 *
 * No component here carries a hex. Every colour is a design token, including
 * `employer`, `deck` and `deck-card`, which were added to globals.css for
 * exactly this module.
 */

/** Standard section rhythm: ~72px desktop / ~52px mobile (spec §2.4). */
export function Section({
  id,
  children,
  className = "",
}: {
  id?: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <section id={id} className={`scroll-mt-24 px-5 py-13 md:py-18 ${className}`}>
      <div className="mx-auto max-w-6xl">{children}</div>
    </section>
  );
}

/** Kicker + display heading + optional standfirst, revealed as one block. */
export function SectionHead({
  kicker,
  title,
  highlight,
  sub,
  tone = "trust",
  invert = false,
}: {
  kicker: string;
  title: string;
  highlight?: string;
  sub?: string;
  tone?: "trust" | "verify" | "employer";
  invert?: boolean;
}) {
  const kickerColor =
    tone === "verify" ? "text-verify" : tone === "employer" ? "text-employer" : "text-trust";

  return (
    <ScrollReveal>
      <p className={`kicker ${invert && tone === "employer" ? "text-employer" : kickerColor}`}>
        {kicker}
      </p>
      <h2
        className={`display mt-3 max-w-3xl text-3xl md:text-[2.75rem] ${
          invert ? "text-white" : "text-ink"
        }`}
      >
        {title}
        {highlight && (
          <>
            {" "}
            <span className="bg-gradient-to-r from-trust to-employer bg-clip-text text-transparent">
              {highlight}
            </span>
          </>
        )}
      </h2>
      {sub && (
        <p
          className={`mt-4 max-w-2xl text-[15px] leading-relaxed md:text-base ${
            invert ? "text-white/55" : "text-muted"
          }`}
        >
          {sub}
        </p>
      )}
    </ScrollReveal>
  );
}

/**
 * An ink panel — the portal's own canvas, dropped into the light page.
 * `glow` adds the single accent bloom the portal uses; `grain` is the shared
 * film-grain overlay from globals.css.
 */
export function InkPanel({
  children,
  className = "",
  accent = "trust",
  glow = true,
}: {
  children: ReactNode;
  className?: string;
  accent?: "trust" | "employer" | "verify";
  glow?: boolean;
}) {
  const rule =
    accent === "employer"
      ? "from-employer"
      : accent === "verify"
        ? "from-verify"
        : "from-trust";
  const bloom =
    accent === "employer" ? "bg-employer" : accent === "verify" ? "bg-verify" : "bg-trust";

  return (
    <div
      className={`grain relative overflow-hidden rounded-[22px] border border-white/10 bg-deck-card ${className}`}
    >
      {/* Accent top-rule, fading out to the right — the portal's tile signature. */}
      <span
        aria-hidden
        className={`pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r ${rule} to-transparent`}
      />
      {glow && (
        <span
          aria-hidden
          className={`pointer-events-none absolute -right-24 -top-28 h-64 w-72 rounded-full opacity-20 blur-[90px] ${bloom}`}
        />
      )}
      <div className="relative">{children}</div>
    </div>
  );
}

/** Micro-label inside an ink panel. */
export function InkLabel({ children }: { children: ReactNode }) {
  return (
    <p className="mono text-[10px] uppercase tracking-[0.18em] text-white/40">{children}</p>
  );
}

/**
 * The "this is not a real person" stamp, rendered wherever the sample report
 * shows. Deliberately neutral rather than amber or red: the semantic colour
 * rules reserve amber for review stars and coach notes and red for a refused
 * promise, and a label is not either of those.
 */
export function SampleStamp({
  onInk = false,
  className = "",
}: {
  onInk?: boolean;
  className?: string;
}) {
  const skin = onInk
    ? "border-white/20 bg-white/[0.06] text-white/65"
    : "border-line bg-white text-muted";

  return (
    <span
      className={`mono inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[10px] uppercase tracking-[0.14em] ${skin} ${className}`}
    >
      <span aria-hidden>●</span>
      Sample · fictional candidate
    </span>
  );
}
