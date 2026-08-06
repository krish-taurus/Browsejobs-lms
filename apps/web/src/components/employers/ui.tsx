import type { ReactNode } from "react";
import { ScrollReveal } from "@/components/motion/ScrollReveal";

/**
 * Surface primitives for /for-employers.
 *
 * The page runs dark end to end, on the same two surfaces the employer
 * portal uses (`deck`, `deck-card`) with the module's blue→violet accent —
 * so the workspace an employer signs into is the page they were sold, not a
 * different product in different colours.
 *
 * No component here carries a hex. Every colour is a design token, including
 * `employer`, `deck` and `deck-card`, which were added to globals.css for
 * this module.
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
    <section id={id} className={`relative scroll-mt-24 px-5 py-13 md:py-18 ${className}`}>
      <div className="relative mx-auto max-w-6xl">{children}</div>
    </section>
  );
}

/**
 * The two drifting aurora blobs behind a dark section. Decorative, so
 * `aria-hidden`; transform-only, so it costs nothing to paint; and it stops
 * dead under reduced motion.
 */
export function Aurora({ className = "" }: { className?: string }) {
  return (
    <div aria-hidden className={`pointer-events-none absolute inset-0 overflow-hidden ${className}`}>
      <div className="aurora-a absolute -left-40 -top-40 h-[520px] w-[620px] rounded-full bg-trust/12 blur-[150px]" />
      <div className="aurora-b absolute -right-40 top-20 h-[460px] w-[560px] rounded-full bg-employer/12 blur-[150px]" />
    </div>
  );
}

/** Kicker + display heading + optional standfirst, revealed as one block. */
export function SectionHead({
  kicker,
  title,
  highlight,
  sub,
  tone = "trust",
  center = false,
}: {
  kicker: string;
  title: string;
  highlight?: string;
  sub?: string;
  tone?: "trust" | "verify" | "employer";
  center?: boolean;
}) {
  const kickerColor =
    tone === "verify" ? "text-verify" : tone === "employer" ? "text-employer" : "text-trust";

  return (
    <ScrollReveal className={center ? "text-center" : undefined}>
      <p className={`kicker ${kickerColor}`}>{kicker}</p>
      <h2
        className={`display mt-3 text-3xl text-white md:text-[2.75rem] ${
          center ? "mx-auto max-w-3xl" : "max-w-3xl"
        }`}
      >
        {title}
        {highlight && (
          <>
            {" "}
            <span className="bg-gradient-to-r from-trust via-sky to-employer bg-clip-text text-transparent">
              {highlight}
            </span>
          </>
        )}
      </h2>
      {sub && (
        <p
          className={`mt-4 text-[15px] leading-relaxed text-white/55 md:text-base ${
            center ? "mx-auto max-w-2xl" : "max-w-2xl"
          }`}
        >
          {sub}
        </p>
      )}
    </ScrollReveal>
  );
}

/**
 * An ink panel — the portal's own tile. `glow` adds the single accent bloom
 * it uses, `live` adds the slow sheen that crosses a frame showing something
 * running, and `grain` is the shared film-grain overlay from globals.css.
 */
export function InkPanel({
  children,
  className = "",
  accent = "trust",
  glow = true,
  live = false,
}: {
  children: ReactNode;
  className?: string;
  accent?: "trust" | "employer" | "verify";
  glow?: boolean;
  live?: boolean;
}) {
  const rule =
    accent === "employer" ? "from-employer" : accent === "verify" ? "from-verify" : "from-trust";
  const bloom =
    accent === "employer" ? "bg-employer" : accent === "verify" ? "bg-verify" : "bg-trust";

  return (
    <div
      className={`grain relative overflow-hidden rounded-[22px] border border-white/10 bg-deck-card ${
        live ? "sheen" : ""
      } ${className}`}
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
  return <p className="mono text-[10px] uppercase tracking-[0.18em] text-white/40">{children}</p>;
}

/** A live dot with an expanding ring — used where something is running. */
export function LiveDot({ tone = "verify" }: { tone?: "verify" | "trust" }) {
  const color = tone === "verify" ? "text-verify" : "text-trust";
  return (
    <span aria-hidden className={`relative inline-flex h-2 w-2 shrink-0 ${color}`}>
      <span className="ping absolute inset-0 rounded-full" />
      <span className="relative h-2 w-2 rounded-full bg-current" />
    </span>
  );
}

/**
 * The "this is not a real person" stamp, rendered wherever the sample report
 * shows. Deliberately neutral rather than amber or red: the semantic colour
 * rules reserve amber for review stars and coach notes and red for a refused
 * promise, and a label is not either of those.
 */
export function SampleStamp({ className = "" }: { className?: string }) {
  return (
    <span
      className={`mono inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/[0.06] px-3 py-1 text-[10px] uppercase tracking-[0.14em] text-white/65 ${className}`}
    >
      <span aria-hidden>●</span>
      Sample · fictional candidate
    </span>
  );
}
