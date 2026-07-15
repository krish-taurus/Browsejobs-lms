import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { PromiseCards } from "@/components/landing/PromiseCards";
import { verifyChecks } from "@/content/landing";

/**
 * The honesty doctrine, operationalised (spec §3.5): "Don't trust us. Verify
 * us." — three checks anyone can run in 15 minutes, then the green/red
 * promise cards.
 */
export function VerifyUs() {
  return (
    <section id="verify" className="mx-auto max-w-6xl px-5 py-20">
      <ScrollReveal>
        <p className="kicker text-verify">The honesty doctrine</p>
        <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-4xl">
          Don&apos;t trust us. Verify us.
        </h2>
        <p className="mt-3 max-w-2xl text-muted">
          Fifteen minutes on Naukri is enough to check everything we claim.
          Here&apos;s how.
        </p>
      </ScrollReveal>

      <div className="mt-10 grid gap-5 md:grid-cols-3">
        {verifyChecks.map((check, i) => (
          <ScrollReveal key={check.title} delay={i * 0.06}>
            <div className="h-full rounded-[14px] border border-line bg-white p-6">
              <span className="mono grid h-9 w-9 place-items-center rounded-full bg-verify-bg text-sm font-semibold text-verify">
                {String(i + 1).padStart(2, "0")}
              </span>
              <h3 className="display mt-4 text-lg text-ink">{check.title}</h3>
              <p className="mt-2 text-sm text-muted">{check.body}</p>
            </div>
          </ScrollReveal>
        ))}
      </div>

      <div className="mt-12">
        <PromiseCards />
      </div>
    </section>
  );
}
