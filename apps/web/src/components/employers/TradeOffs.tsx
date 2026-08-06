import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Section, SectionHead } from "@/components/employers/ui";
import { advantages, limits, notForYou } from "@/content/employers";

/**
 * Advantages and limits, side by side and given the same weight.
 *
 * The limits column is the reason this section exists: radical honesty is the
 * house position, and an employer who reads a real constraint here and
 * enquires anyway is a better conversation than one who finds it in week two.
 * Neither column is coloured as good/bad — green is reserved for
 * verified/kept-promise and red for a refused one, and a trade-off is
 * neither.
 */
export function TradeOffs() {
  return (
    <Section id="trade-offs" className="bg-white">
      <SectionHead
        kicker="Straight answers"
        title="What you get, and"
        highlight="where we are limited"
        sub="Every hiring product has a shape. Here is ours, including the parts that will not suit you — stated before you spend a call finding out."
      />

      <div className="mt-12 grid gap-5 lg:grid-cols-2">
        {/* Advantages ------------------------------------------------- */}
        <ScrollReveal>
          <div className="h-full rounded-[22px] border border-line bg-paper p-6 md:p-8">
            <p className="mono text-[10px] uppercase tracking-[0.16em] text-trust">
              What the platform gives you
            </p>
            <ul className="mt-6 space-y-6">
              {advantages.map((a) => (
                <li key={a.title} className="border-l-2 border-trust pl-4">
                  <h3 className="text-[15px] font-semibold text-ink">{a.title}</h3>
                  <p className="mt-1.5 text-[14px] leading-relaxed text-ink2/70">{a.body}</p>
                </li>
              ))}
            </ul>
          </div>
        </ScrollReveal>

        {/* Limits ----------------------------------------------------- */}
        <ScrollReveal delay={0.06}>
          <div className="h-full rounded-[22px] border border-line bg-paper p-6 md:p-8">
            <p className="mono text-[10px] uppercase tracking-[0.16em] text-muted">
              Where it falls short
            </p>
            <ul className="mt-6 space-y-6">
              {limits.map((l) => (
                <li key={l.title} className="border-l-2 border-line pl-4">
                  <h3 className="text-[15px] font-semibold text-ink">{l.title}</h3>
                  <p className="mt-1.5 text-[14px] leading-relaxed text-ink2/70">{l.body}</p>
                </li>
              ))}
            </ul>
          </div>
        </ScrollReveal>
      </div>

      {/* Disqualifiers ------------------------------------------------ */}
      <ScrollReveal delay={0.1}>
        <div className="mt-5 rounded-[22px] border border-line bg-paper p-6 md:p-8">
          <p className="mono text-[10px] uppercase tracking-[0.16em] text-muted">
            Do not start here if
          </p>
          <ul className="mt-5 grid gap-3 md:grid-cols-2">
            {notForYou.map((n) => (
              <li key={n} className="flex gap-3 text-[14px] leading-relaxed text-ink2/75">
                <span aria-hidden className="mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full bg-muted" />
                {n}
              </li>
            ))}
          </ul>
        </div>
      </ScrollReveal>
    </Section>
  );
}
