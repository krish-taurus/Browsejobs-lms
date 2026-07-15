import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { BookCta } from "@/components/landing/BookCta";
import { freeLadder } from "@/content/landing";

/**
 * The free ladder (spec §2.3): 3 green FREE rungs + 1 blue STEP 04 paid rung —
 * the visual spine of the funnel. Green is reserved for the free steps; the
 * paid rung is blue (never green).
 */
export function FreeLadder() {
  return (
    <section id="free-steps" className="bg-white">
      <div className="mx-auto max-w-6xl px-5 py-20">
        <ScrollReveal>
          <p className="kicker text-verify">Three free steps first</p>
          <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-4xl">
            You pay nothing until you&apos;ve seen everything
          </h2>
        </ScrollReveal>

        <ol className="mt-12 grid gap-5 lg:grid-cols-4">
          {freeLadder.map((rung, i) => (
            <ScrollReveal as="li" key={rung.step} delay={i * 0.07}>
              <div
                className={`relative flex h-full flex-col rounded-[14px] border p-6 transition-transform hover:-translate-y-1 ${
                  rung.free
                    ? "border-verify/30 bg-verify-bg"
                    : "border-trust/40 bg-sky"
                }`}
                style={{
                  // Stepped rise on desktop — the "ladder" silhouette.
                  marginTop: `calc(${(freeLadder.length - 1 - i) * 14}px)`,
                }}
              >
                <div className="flex items-center justify-between">
                  <span
                    className={`mono text-sm font-semibold ${
                      rung.free ? "text-verify" : "text-trust"
                    }`}
                  >
                    {rung.free ? "FREE" : `STEP ${rung.step}`}
                  </span>
                  <span className="mono text-xs text-muted">{rung.step}</span>
                </div>
                <h3 className="display mt-3 text-lg leading-snug text-ink">
                  {rung.title}
                </h3>
                <p className="mt-2 flex-1 text-sm text-ink2/80">{rung.body}</p>
              </div>
            </ScrollReveal>
          ))}
        </ol>

        <ScrollReveal delay={0.2}>
          <div className="mt-10 text-center">
            <BookCta className="px-8 py-3.5">
              Start with step 01 — it&apos;s free
            </BookCta>
          </div>
        </ScrollReveal>
      </div>
    </section>
  );
}
