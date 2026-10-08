import Link from "next/link";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { ScrollReveal } from "@/components/motion/ScrollReveal";

/** Employer story: one claim, then the floor. */
export function EmployerBand() {
  return (
    <section id="for-employers" className="scroll-mt-28 border-t border-white/10">
      <div className="mx-auto max-w-6xl px-5 py-28 md:py-40">
        <ScrollReveal>
          <p className="kicker text-trust">For employers</p>
          <h2 className="display mt-6 max-w-5xl text-[clamp(2.75rem,7vw,6rem)] leading-[0.92] tracking-[-0.04em] text-fg">
            From <span className="mono">90</span> days to <span className="mono">3</span>.
          </h2>
          <p className="mt-6 max-w-2xl text-lg leading-relaxed text-muted md:text-xl">
            Tell the BrowseJobs AI Recruiter the role. It walks the floor. You meet people who already cleared.
          </p>
          <Disclaimer className="mt-5 max-w-2xl" />
          <div className="mt-10 flex flex-wrap gap-3">
            <a
              href="#ai-recruiter"
              className="inline-flex items-center justify-center rounded-full bg-trust px-7 py-3.5 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep"
            >
              Watch the floor
            </a>
            <Link
              href="/employers"
              className="inline-flex items-center justify-center rounded-full border border-white/20 px-7 py-3.5 font-semibold text-fg transition-colors hover:border-trust"
            >
              Hire with BrowseJobs
            </Link>
          </div>
        </ScrollReveal>
      </div>
    </section>
  );
}
