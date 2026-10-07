import Link from "next/link";
import { InterviewStartForm } from "@/components/auth/InterviewStartForm";
import { MaskReveal } from "@/components/motion/MaskReveal";

export function HomeHero() {
  return (
    <section id="top" className="relative overflow-hidden px-5 pb-28 pt-4 md:pb-40 md:pt-8">
      <div aria-hidden className="home-glow pointer-events-none absolute inset-x-0 top-0 h-[28rem]" />
      <div className="relative mx-auto max-w-6xl">
        <p className="kicker text-verify">Free · no card</p>
        <h1 className="display mt-6 max-w-5xl text-[clamp(2.75rem,7.2vw,6rem)] leading-[0.9] tracking-[-0.045em] text-fg">
          <MaskReveal>Take a free AI interview.</MaskReveal>
        </h1>
        <p className="mt-6 max-w-xl text-lg leading-relaxed text-muted md:text-xl">
          It&apos;s free. Fifteen questions from your CV. A score of <span className="mono text-fg">75%</span> or more
          puts you in front of HR.
        </p>
        <InterviewStartForm />
        <Link
          href="/employers"
          className="mt-3 inline-flex items-center justify-center rounded-full border border-white/20 px-7 py-3.5 font-semibold text-fg transition-colors hover:border-trust"
        >
          Hire with BrowseJobs
        </Link>
      </div>
    </section>
  );
}
