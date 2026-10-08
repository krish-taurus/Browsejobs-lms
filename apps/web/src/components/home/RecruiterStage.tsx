import { RecruiterPreview } from "@/components/home/RecruiterPreview";

/**
 * Cinematic product section. The floor itself is a separate client chunk.
 */
export function RecruiterStage() {
  return (
    <section id="ai-recruiter" className="scroll-mt-28 border-t border-white/10">
      <div className="mx-auto max-w-6xl px-5 pb-10 pt-28 md:pb-12 md:pt-40">
        <p className="kicker text-trust">BrowseJobs AI Recruiter</p>
        <h2 className="display mt-6 max-w-4xl text-[clamp(2.75rem,7vw,6rem)] leading-[0.92] tracking-[-0.04em] text-fg">
          Watch every stage.
        </h2>
        <p className="mt-6 max-w-2xl text-lg leading-relaxed text-muted md:text-xl">
          Candidates take a free AI interview first and get a score. Employers tell the recruiter the role and watch the floor move, from the shortlist to the offer.
        </p>
        <p className="mt-4 max-w-2xl text-sm leading-relaxed text-muted">
          Sample data. Fictional names. A person always releases the offer.
        </p>
      </div>
      <div className="mx-auto max-w-6xl px-5 pb-20 md:pb-32">
        <RecruiterPreview />
      </div>
    </section>
  );
}
