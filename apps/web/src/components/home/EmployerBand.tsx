import Link from "next/link";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { ScrollReveal } from "@/components/motion/ScrollReveal";

const POINTS = [
  "Pre-vetted, pre-interviewed candidates, each with a full performance report.",
  "Every interview is fully proctored.",
  "WhatsApp bots keep candidates in the loop until they join, and after they join.",
] as const;

/** Short employer pitch, directly under the candidate steps. */
export function EmployerBand() {
  return (
    <section id="for-employers" className="scroll-mt-28 border-t border-white/10">
      <div className="mx-auto max-w-6xl px-5 py-16 md:py-24">
        <ScrollReveal>
          <p className="kicker text-trust">For employers</p>
          <h2 className="display mt-3 max-w-3xl text-4xl text-fg md:text-6xl">
            Cut hiring time from <span className="mono">90</span> days to <span className="mono">3</span> days.
          </h2>
          <p className="mt-4 max-w-2xl text-lg leading-relaxed text-muted">
            Share the role on WhatsApp. The bots take over. You meet people who already cleared.
          </p>
          <Disclaimer className="mt-4 max-w-2xl" />
          <ul className="mt-8 max-w-2xl space-y-3">
            {POINTS.map((point) => (
              <li key={point} className="flex gap-3 text-base leading-relaxed text-fg">
                <span aria-hidden className="mt-2.5 h-1.5 w-1.5 shrink-0 rounded-full bg-trust" />
                <span>{point}</span>
              </li>
            ))}
          </ul>
          <Link
            href="/employers"
            className="mt-8 inline-flex items-center justify-center rounded-full bg-trust px-7 py-3.5 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep"
          >
            Hire with BrowseJobs
          </Link>
        </ScrollReveal>
      </div>
    </section>
  );
}