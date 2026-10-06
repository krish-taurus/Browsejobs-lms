import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { ScreenFrame } from "@/components/home/ScreenFrame";
import { fees } from "@/content/landing";

const rupee = (n: number) => "₹" + n.toLocaleString("en-IN");

export function ProofAndPay() {
  return (
    <section id="proof" className="scroll-mt-28 border-t border-white/10">
      <div className="mx-auto grid max-w-6xl items-center gap-12 px-5 py-16 md:py-24 lg:grid-cols-2">
        <ScrollReveal>
          <p className="kicker text-verify">Proof before HR</p>
          <h2 className="display mt-3 text-4xl text-fg md:text-6xl">
            HR sees your scored interview. Not a CV on its own.
          </h2>
          <p className="mt-4 text-lg leading-relaxed text-muted">
            We put you in front of HR only after you clear. If you don&apos;t, you stay with what you
            need to fix. Nobody can guarantee employment — the market decides. What we put in writing:
            the process.
          </p>
          <ul className="mt-6 space-y-3 text-sm text-fg">
            <li className="flex gap-3">
              <span className="mono text-verify">01</span>
              You clear, and we put you in front of HR with your score.
            </li>
            <li className="flex gap-3">
              <span className="mono text-verify">02</span>
              They see the scored interview, not a cold CV.
            </li>
            <li className="flex gap-3">
              <span className="mono text-amber">03</span>
              You don&apos;t clear, and we don&apos;t send your profile and hope.
            </li>
          </ul>
        </ScrollReveal>
        <ScrollReveal delay={0.07}>
          <ScreenFrame mode="hr" />
        </ScrollReveal>
      </div>

      <div id="fees" className="scroll-mt-28 border-t border-white/10">
        <div className="mx-auto grid max-w-6xl gap-4 px-5 py-16 md:grid-cols-2 md:py-24">
          <ScrollReveal>
            <div className="home-frame h-full rounded-[22px] border border-white/10 bg-surface/70 p-7 backdrop-blur-md md:p-8">
              <p className="kicker text-trust">Registration</p>
              <p className="mono mt-4 text-5xl font-semibold text-fg">{rupee(fees.registration)}</p>
              <p className="mono mt-2 text-sm text-muted">
                or {fees.registrationEmi.months} × {rupee(fees.registrationEmi.amount)}
              </p>
              <p className="mt-4 text-sm leading-relaxed text-muted">
                You pay this only after the free masterclass and the free 7-hour bootcamp. Or split
                it: {fees.registrationEmi.months} × {rupee(fees.registrationEmi.amount)}.
              </p>
            </div>
          </ScrollReveal>
          <ScrollReveal delay={0.07}>
            <div className="home-frame h-full rounded-[22px] border border-trust/30 bg-trust/10 p-7 md:p-8">
              <p className="kicker text-sky">Placement fee</p>
              <p className="display mt-4 text-3xl text-fg md:text-4xl">After you accept an offer.</p>
              <p className="mt-4 text-sm leading-relaxed text-ink2">
                This is your first {fees.placement.monthsOfCtc} months&apos; salary, and you pay it
                only after you take the offer. Split across {fees.placement.emis} months. The{" "}
                {rupee(fees.placement.adjusted)} you already paid comes off that bill.{" "}
                {fees.guaranteeDays}-day money-back — any reason, in writing.
              </p>
            </div>
          </ScrollReveal>
        </div>
      </div>
    </section>
  );
}
