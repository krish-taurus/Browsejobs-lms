import Link from "next/link";
import { BookCta } from "@/components/landing/BookCta";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { recommendedCourses } from "@/content/home";

/** Soft fork after a miss: counselling first, a live course second. */
export function GapFork() {
  const tracks = recommendedCourses();

  return (
    <section id="gaps" className="scroll-mt-28 border-t border-white/10">
      <div className="mx-auto grid max-w-6xl items-start gap-12 px-5 py-16 md:py-24 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
        <ScrollReveal>
          <p className="kicker text-amber">If you don&apos;t clear</p>
          <h2 className="display mt-3 text-4xl text-fg md:text-6xl">Didn&apos;t clear? Here&apos;s what&apos;s blocking interviews.</h2>
          <p className="mt-4 max-w-xl text-lg leading-relaxed text-muted">
            A miss still helps. You see the questions that slipped, and the skills that role is
            hiring for. Free counselling walks you through what&apos;s blocking you. A course is only
            there if you need it to fix that.
          </p>
          <div className="mt-8 flex flex-wrap items-center gap-3">
            <BookCta variant="counselling" className="px-7 py-3.5">
              Book free counselling
            </BookCta>
            <a
              href="#courses"
              className="rounded-full border border-white/15 px-6 py-3 font-semibold text-fg transition-colors hover:border-trust"
            >
              See courses that fix the gap
            </a>
          </div>
          <p className="mono mt-4 text-xs text-muted">
            Counselling is free. You leave with a written Career Analysis Report whether you join or not.
          </p>
        </ScrollReveal>

        <ScrollReveal delay={0.07}>
          <div className="home-frame rounded-[22px] border border-white/10 bg-surface/80 p-6 backdrop-blur-md md:p-7">
            <div className="flex items-center justify-between gap-3">
              <p className="kicker text-amber">What you might need to fix</p>
              <span className="mono text-[10px] uppercase tracking-[0.16em] text-muted">Sample</span>
            </div>
            <p className="mt-3 text-sm text-muted">
              Not your result. This is how a miss is written — and which live course we point you to.
            </p>
            <ul className="mt-5 divide-y divide-white/10">
              {tracks.map((track) => (
                <li key={track.slug} className="py-4 first:pt-0 last:pb-0">
                  <p className="text-sm text-fg">{track.when}</p>
                  <Link
                    href={track.href}
                    className="mono mt-1 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-sky"
                  >
                    {track.code} · {track.name} <span aria-hidden>→</span>
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        </ScrollReveal>
      </div>
    </section>
  );
}
