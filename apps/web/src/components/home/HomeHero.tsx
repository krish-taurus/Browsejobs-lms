import { InterviewStartForm } from "@/components/auth/InterviewStartForm";
import { KineticPortrait } from "@/components/home/KineticPortrait";
import { MaskReveal } from "@/components/motion/MaskReveal";
import { HOME_EXPLAINER_EMBED_URL, safeExplainerEmbed } from "@/content/home";

export function HomeHero() {
  const embedSrc = safeExplainerEmbed(
    process.env.NEXT_PUBLIC_HOME_EXPLAINER_EMBED?.trim() || HOME_EXPLAINER_EMBED_URL,
  );

  return (
    <section id="top" className="relative overflow-hidden px-5 pb-16 pt-6 md:pb-24 md:pt-10">
      <div aria-hidden className="home-grid pointer-events-none absolute inset-0 opacity-80" />
      <div aria-hidden className="home-glow pointer-events-none absolute inset-0" />
      <div className="relative mx-auto grid max-w-6xl items-center gap-10 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.85fr)] lg:gap-8">
        <div>
          <p className="kicker text-verify">Free · no card</p>
          <h1 className="display mt-5 text-[clamp(2.5rem,6.2vw,5.2rem)] leading-[0.92] tracking-[-0.045em] text-fg">
            <MaskReveal>Take a free </MaskReveal>
            <MaskReveal index={1}>AI interview.</MaskReveal>
          </h1>
          <p className="mt-6 max-w-xl text-lg leading-relaxed text-muted">
            It&apos;s free. Fifteen questions from your CV, scored out of 100. A score of{" "}
            <span className="mono text-fg">75%</span> or more counts as clear and puts you in front of HR with your
            score.
          </p>
          <InterviewStartForm />
        </div>
        <div className="hidden lg:block lg:pt-4">
          <KineticPortrait src={embedSrc} />
        </div>
      </div>
    </section>
  );
}
