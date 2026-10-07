import { Disclaimer } from "@/components/brand/Disclaimer";
import { BookCta } from "@/components/landing/BookCta";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { recruiterFaqs } from "@/content/home";
import { faqs, freeLadder, promisesKept, promisesNever } from "@/content/landing";

export function HomeClose() {
  return (
    <>
      <section id="verify" className="scroll-mt-28 border-t border-white/10">
        <div className="mx-auto max-w-6xl px-5 py-28 md:py-40">
          <ScrollReveal>
            <p className="kicker text-trust">In writing</p>
            <h2 className="display mt-6 max-w-3xl text-[clamp(2.5rem,6vw,4.5rem)] leading-[0.95] tracking-[-0.04em] text-fg">
              What we promise — and what we never will
            </h2>
          </ScrollReveal>
          <div className="mt-10 grid gap-4 md:grid-cols-2">
            <ScrollReveal>
              <div className="h-full rounded-[22px] border border-verify/30 bg-verify-bg p-7 md:p-8">
                <p className="kicker text-verify">What we promise in writing</p>
                <ul className="mt-6 space-y-4">
                  {promisesKept.map((item) => (
                    <li key={item} className="text-sm leading-relaxed text-fg">
                      {item}
                    </li>
                  ))}
                </ul>
              </div>
            </ScrollReveal>
            <ScrollReveal delay={0.07}>
              <div className="h-full rounded-[22px] border border-warn/30 bg-warn/10 p-7 md:p-8">
                <p className="kicker text-warn">What we will never tell you</p>
                <ul className="mt-6 space-y-4">
                  {promisesNever.map((item) => (
                    <li key={item} className="text-sm leading-relaxed text-fg">
                      {item}
                    </li>
                  ))}
                </ul>
              </div>
            </ScrollReveal>
          </div>
        </div>
      </section>

      <section id="free-steps" className="scroll-mt-28 border-t border-white/10">
        <div className="mx-auto max-w-6xl px-5 py-28 md:py-40">
          <ScrollReveal>
            <p className="kicker text-verify">Three free steps first</p>
            <h2 className="display mt-6 max-w-3xl text-[clamp(2.5rem,6vw,4.5rem)] leading-[0.95] tracking-[-0.04em] text-fg">
              You pay nothing until you have seen the work.
            </h2>
          </ScrollReveal>
          <ol className="mt-10 grid gap-4 lg:grid-cols-4">
            {freeLadder.map((rung, i) => (
              <ScrollReveal as="li" key={rung.step} delay={i * 0.07}>
                <div
                  className={`flex h-full flex-col rounded-[14px] border p-6 ${
                    rung.free ? "border-verify/30 bg-verify-bg" : "border-trust/40 bg-trust/10"
                  }`}
                >
                  <span className={`mono text-sm font-semibold ${rung.free ? "text-verify" : "text-sky"}`}>
                    {rung.free ? "FREE" : `STEP ${rung.step}`}
                  </span>
                  <h3 className="display mt-3 text-lg leading-snug text-fg">{rung.title}</h3>
                  <p className="mt-2 text-sm leading-relaxed text-muted">{rung.body}</p>
                </div>
              </ScrollReveal>
            ))}
          </ol>
        </div>
      </section>

      <section id="faq" className="scroll-mt-28 border-t border-white/10">
        <div className="mx-auto max-w-3xl px-5 py-28 md:py-40">
          <p className="kicker text-trust">Questions</p>
          <h2 className="display mt-6 text-[clamp(2.75rem,7vw,6rem)] leading-[0.92] tracking-[-0.04em] text-fg">Straight answers.</h2>
          <div className="mt-8 divide-y divide-white/10 border-y border-white/10">
            {recruiterFaqs.map((item) => (
              <details key={item.q} className="group py-5">
                <summary className="cursor-pointer list-none text-lg font-semibold text-fg [&::-webkit-details-marker]:hidden">
                  <span className="flex items-start justify-between gap-4">
                    {item.q}
                    <span aria-hidden className="mono inline-block text-muted transition-transform group-open:rotate-45">
                      +
                    </span>
                  </span>
                </summary>
                <p className="mt-3 text-base leading-relaxed text-muted">{item.a}</p>
              </details>
            ))}
          </div>
          <Disclaimer className="mt-4" />
          <div className="mt-2 divide-y divide-white/10 border-b border-white/10">
            {faqs.map((item) => (
              <details key={item.q} className="group py-4">
                <summary className="cursor-pointer list-none text-base font-semibold text-fg [&::-webkit-details-marker]:hidden">
                  <span className="flex items-start justify-between gap-4">
                    {item.q}
                    <span aria-hidden className="mono inline-block text-muted transition-transform group-open:rotate-45">
                      +
                    </span>
                  </span>
                </summary>
                <p className="mt-3 text-sm leading-relaxed text-muted">{item.a}</p>
              </details>
            ))}
          </div>
          <Disclaimer className="mt-6" />
        </div>
      </section>

      <section id="close" className="relative scroll-mt-28 overflow-hidden border-t border-white/10">
        <div aria-hidden className="home-glow pointer-events-none absolute inset-0" />
        <div className="relative mx-auto max-w-4xl px-5 py-28 text-center md:py-40">
          <ScrollReveal>
            <h2 className="display text-[clamp(2.75rem,7vw,6rem)] leading-[0.92] tracking-[-0.04em] text-fg">
              Start with the interview.
            </h2>
            <p className="mx-auto mt-4 max-w-md text-lg text-muted">The course can wait until you know what to fix.</p>
            <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
              <a
                href="#interview-start"
                className="inline-flex items-center justify-center rounded-full bg-trust px-8 py-3.5 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep"
              >
                Take your free AI interview
              </a>
              <BookCta ghost variant="masterclass">
                Book the free masterclass
              </BookCta>
            </div>
          </ScrollReveal>
        </div>
      </section>
    </>
  );
}
