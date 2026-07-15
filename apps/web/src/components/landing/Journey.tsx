import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { journey } from "@/content/landing";

export function Journey() {
  return (
    <section id="journey" className="mx-auto max-w-6xl px-5 py-20">
      <ScrollReveal>
        <p className="kicker text-trust">Your journey</p>
        <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-4xl">
          From curious to placed, one honest step at a time
        </h2>
      </ScrollReveal>

      <ol className="mt-12 grid gap-6 md:grid-cols-2">
        {journey.map((s, i) => (
          <ScrollReveal as="li" key={s.step} delay={i * 0.06}>
            <div className="relative h-full rounded-2xl border border-line bg-white p-7 transition-shadow hover:shadow-lg">
              <span className="mono text-sm text-trust">{s.step}</span>
              <div className="mt-2 h-px w-10 bg-trust" />
              <h3 className="display mt-4 text-xl text-ink">{s.title}</h3>
              <p className="mt-2 text-muted">{s.body}</p>
            </div>
          </ScrollReveal>
        ))}
      </ol>
    </section>
  );
}
