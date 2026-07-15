import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { promisesKept, promisesNever } from "@/content/landing";

function Check() {
  return (
    <svg viewBox="0 0 20 20" className="mt-0.5 h-5 w-5 shrink-0" fill="none">
      <circle cx="10" cy="10" r="10" fill="var(--bj-verify)" opacity="0.15" />
      <path
        d="M6 10.5l2.5 2.5L14 7.5"
        stroke="var(--bj-verify)"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function Cross() {
  return (
    <svg viewBox="0 0 20 20" className="mt-0.5 h-5 w-5 shrink-0" fill="none">
      <circle cx="10" cy="10" r="10" fill="var(--bj-warn)" opacity="0.15" />
      <path
        d="M7 7l6 6M13 7l-6 6"
        stroke="var(--bj-warn)"
        strokeWidth="2"
        strokeLinecap="round"
      />
    </svg>
  );
}

export function PromiseCards() {
  return (
    <section className="mx-auto max-w-6xl px-5 py-20">
      <ScrollReveal>
        <p className="kicker text-trust">Our promise</p>
        <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-4xl">
          What we&apos;ll always do — and never do
        </h2>
      </ScrollReveal>

      <div className="mt-12 grid gap-6 md:grid-cols-2">
        <ScrollReveal>
          <div className="h-full rounded-2xl border border-verify/30 bg-verify/5 p-8">
            <p className="kicker text-verify">Always</p>
            <ul className="mt-5 space-y-4">
              {promisesKept.map((p) => (
                <li key={p} className="flex gap-3 text-ink">
                  <Check />
                  <span>{p}</span>
                </li>
              ))}
            </ul>
          </div>
        </ScrollReveal>

        <ScrollReveal delay={0.08}>
          <div className="h-full rounded-2xl border border-warn/30 bg-warn/5 p-8">
            <p className="kicker text-warn">Never</p>
            <ul className="mt-5 space-y-4">
              {promisesNever.map((p) => (
                <li key={p} className="flex gap-3 text-ink">
                  <Cross />
                  <span>{p}</span>
                </li>
              ))}
            </ul>
          </div>
        </ScrollReveal>
      </div>
    </section>
  );
}
