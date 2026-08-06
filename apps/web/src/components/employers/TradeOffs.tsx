import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Aurora, InkPanel, Section, SectionHead } from "@/components/employers/ui";
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
    <Section id="trade-offs" className="overflow-hidden">
      <Aurora />

      <SectionHead
        kicker="Straight answers"
        title="What you get, and"
        highlight="where we are limited"
        sub="Every hiring product has a shape. Here is ours, including the parts that will not suit you — stated before you spend a call finding out."
      />

      <div className="mt-12 grid gap-4 lg:grid-cols-2">
        <ScrollReveal>
          <InkPanel accent="trust" className="h-full p-6 md:p-8">
            <p className="mono text-[10px] uppercase tracking-[0.16em] text-trust">
              What the platform gives you
            </p>
            <ul className="mt-6 space-y-6">
              {advantages.map((a) => (
                <li key={a.title} className="border-l-2 border-trust pl-4">
                  <h3 className="text-[15px] font-semibold text-white">{a.title}</h3>
                  <p className="mt-1.5 text-[14px] leading-relaxed text-white/55">{a.body}</p>
                </li>
              ))}
            </ul>
          </InkPanel>
        </ScrollReveal>

        <ScrollReveal delay={0.06}>
          <InkPanel accent="employer" glow={false} className="h-full p-6 md:p-8">
            <p className="mono text-[10px] uppercase tracking-[0.16em] text-white/45">
              Where it falls short
            </p>
            <ul className="mt-6 space-y-6">
              {limits.map((l) => (
                <li key={l.title} className="border-l-2 border-white/15 pl-4">
                  <h3 className="text-[15px] font-semibold text-white">{l.title}</h3>
                  <p className="mt-1.5 text-[14px] leading-relaxed text-white/55">{l.body}</p>
                </li>
              ))}
            </ul>
          </InkPanel>
        </ScrollReveal>
      </div>

      <ScrollReveal delay={0.1}>
        <InkPanel accent="trust" glow={false} className="mt-4 p-6 md:p-8">
          <p className="mono text-[10px] uppercase tracking-[0.16em] text-white/45">
            Do not start here if
          </p>
          <ul className="mt-5 grid gap-3 md:grid-cols-2">
            {notForYou.map((n) => (
              <li key={n} className="flex gap-3 text-[14px] leading-relaxed text-white/60">
                <span aria-hidden className="mt-[7px] h-1.5 w-1.5 shrink-0 rounded-full bg-white/30" />
                {n}
              </li>
            ))}
          </ul>
        </InkPanel>
      </ScrollReveal>
    </Section>
  );
}
