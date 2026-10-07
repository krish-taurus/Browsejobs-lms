import Link from "next/link";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { TiltCard } from "@/components/motion/TiltCard";
import { recommendedCourses } from "@/content/home";

/** Courses sit after the fork. Links are live pages only. */
export function CourseStrip() {
  const tracks = recommendedCourses();

  return (
    <section id="courses" className="scroll-mt-28 border-t border-white/10 bg-surface/40">
      <div className="mx-auto max-w-6xl px-5 py-28 md:py-40">
        <ScrollReveal>
          <p className="kicker text-trust">Only if the interview says you need one</p>
          <h2 className="display mt-6 max-w-3xl text-[clamp(2.5rem,6vw,4.5rem)] leading-[0.95] tracking-[-0.04em] text-fg">
            Courses that fix a specific gap.
          </h2>
          <p className="mt-4 max-w-2xl text-lg text-muted">
            These are not the front door. We point you here only when the interview shows that skill
            is where you&apos;re stuck. Every link is a live course page.
          </p>
        </ScrollReveal>

        <ul className="mt-10 grid gap-4 sm:grid-cols-2">
          {tracks.map((track, i) => (
            <ScrollReveal as="li" key={track.slug} delay={i * 0.07}>
              <TiltCard className="h-full">
                <Link
                  href={track.href}
                  className="group relative flex h-full flex-col overflow-hidden rounded-[14px] border border-white/10 bg-ink/80 p-6 transition-transform hover:-translate-y-1"
                >
                  <span
                    aria-hidden
                    className="pointer-events-none absolute -right-8 -top-10 h-32 w-32 rounded-full bg-trust/20 opacity-0 blur-2xl transition-opacity duration-300 group-hover:opacity-100"
                  />
                  <div className="relative flex items-center justify-between gap-3">
                    <span className="mono text-xs font-semibold text-sky">{track.code}</span>
                    <span className="mono text-[10px] uppercase tracking-[0.14em] text-muted">Open page</span>
                  </div>
                  <h3 className="display relative mt-4 text-2xl text-fg">{track.name}</h3>
                  <p className="relative mt-2 text-sm text-muted">{track.tagline}</p>
                  <p className="relative mt-4 flex-1 border-l-2 border-amber pl-3 text-sm leading-snug text-ink2">
                    {track.when}
                  </p>
                  <span className="relative mt-6 text-sm font-semibold text-sky">
                    View the course <span aria-hidden className="inline-block transition-transform group-hover:translate-x-1">→</span>
                  </span>
                </Link>
              </TiltCard>
            </ScrollReveal>
          ))}
        </ul>
      </div>
    </section>
  );
}
