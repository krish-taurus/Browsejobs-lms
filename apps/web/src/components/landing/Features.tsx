import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { features } from "@/content/landing";

export function Features() {
  return (
    <section id="features" className="bg-white">
      <div className="mx-auto max-w-6xl px-5 py-20">
        <ScrollReveal>
          <p className="kicker text-trust">The platform</p>
          <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-4xl">
            Everything the day-one job needs — built in
          </h2>
        </ScrollReveal>

        <div className="mt-12 grid gap-6 md:grid-cols-2">
          {features.map((f, i) => (
            <ScrollReveal key={f.kicker} delay={i * 0.06}>
              <article className="group h-full rounded-2xl border border-line bg-paper p-8 transition-all hover:-translate-y-1 hover:border-trust/40 hover:shadow-xl">
                <p className="kicker text-verify">{f.kicker}</p>
                <h3 className="display mt-3 text-2xl text-ink">{f.title}</h3>
                <p className="mt-3 text-muted">{f.body}</p>
              </article>
            </ScrollReveal>
          ))}
        </div>
      </div>
    </section>
  );
}
