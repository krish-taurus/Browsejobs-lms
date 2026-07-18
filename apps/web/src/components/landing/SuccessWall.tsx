import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Kicker } from "@/components/brand/Kicker";
import { ReviewWall } from "@/components/reviews/ReviewWall";

/**
 * S8 — the success wall. Renders ONLY real seeded reviews via ReviewWall (never
 * fabricated); the wall degrades to nothing if there are none, so the section is honest
 * whether the review corpus is loaded or not.
 */
export function SuccessWall() {
  return (
    <section className="mx-auto max-w-6xl px-5 py-20 md:py-28">
      <ScrollReveal className="mb-12 text-center">
        <Kicker>In their words</Kicker>
        <h2 className="display mx-auto mt-3 max-w-2xl text-3xl text-ink md:text-5xl">
          The people who did it, on the record
        </h2>
        <p className="mx-auto mt-4 max-w-xl text-ink2/70">
          Real reviews from Google, WhatsApp and verified students — never a fabricated one.
        </p>
      </ScrollReveal>
      <ReviewWall />
    </section>
  );
}
