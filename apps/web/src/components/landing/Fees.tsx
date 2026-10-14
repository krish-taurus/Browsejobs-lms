import { Disclaimer } from "@/components/brand/Disclaimer";
import { Kicker } from "@/components/brand/Kicker";
import { BookCta } from "@/components/landing/BookCta";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { fees } from "@/content/landing";

const INCLUDED = [
  "Live instructor-led classes",
  "All class recordings, one year",
  "AI tutor, unlimited",
  "MCQ tests & mastery tracking",
  "Your first comprehensive CV",
  "Base mock-interview quota",
  "Student support desk",
];

const rupee = (n: number) => "₹" + Math.round(n).toLocaleString("en-IN");

/**
 * Registration covers the course. The placement fee is three months of CTC,
 * charged in EMIs only after an accepted offer. No worked example: the CTC
 * is not known until the offer exists.
 */
export function Fees() {
  return (
    <section id="fees" className="bg-white">
      <div className="mx-auto max-w-6xl px-5 py-20">
        <ScrollReveal>
          <Kicker>The fee structure</Kicker>
          <h2 className="display mt-3 max-w-3xl text-3xl text-ink md:text-5xl">
            Two fees. Both in writing. One only after you&apos;re hired.
          </h2>
        </ScrollReveal>

        <div className="mt-10 grid gap-6 lg:grid-cols-2">
          <ScrollReveal>
            <div className="flex h-full flex-col rounded-[22px] border border-line bg-paper p-8">
              <p className="kicker text-trust">01 · Registration</p>
              <div className="mt-4 flex items-baseline gap-3">
                <span className="mono text-4xl font-semibold text-ink">{rupee(fees.registration)}</span>
              </div>
              <p className="mt-2 text-sm text-muted">
                This covers the course. Payable only <span className="font-semibold text-ink">after</span> the free
                masterclass and the free 7-hour bootcamp.
              </p>
              <div className="mt-5 flex items-center gap-2 rounded-[10px] bg-verify-bg px-4 py-3">
                <svg viewBox="0 0 20 20" className="h-5 w-5 shrink-0" fill="none">
                  <circle cx="10" cy="10" r="9" stroke="var(--bj-verify)" strokeWidth="1.6" />
                  <path d="M6.5 10.5l2.3 2.3L13.5 8" stroke="var(--bj-verify)" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
                <p className="text-sm text-ink">
                  <span className="font-semibold">{fees.guaranteeDays}-day money-back guarantee</span> — any reason,
                  full refund, in writing.
                </p>
              </div>
            </div>
          </ScrollReveal>

          <ScrollReveal delay={0.08}>
            <div className="flex h-full flex-col rounded-[22px] border border-line bg-paper p-8">
              <p className="kicker text-trust">02 · Placement fee</p>
              <p className="mt-4 text-ink">
                <span className="mono font-semibold">{fees.placement.monthsOfCtc} months of the CTC</span> on the
                offer you accept, due <span className="font-semibold">only after you accept an offer</span>, charged
                in EMIs after that.
              </p>
              <p className="mt-4 text-sm text-muted">
                The fee follows the offer. This page does not print a rupee example, because the CTC is not known
                until you accept one.
              </p>
              <div className="mt-4">
                <Disclaimer />
              </div>
              <div className="mt-auto pt-6">
                <BookCta className="w-full py-3 text-center" />
              </div>
            </div>
          </ScrollReveal>
        </div>

        <ScrollReveal delay={0.05}>
          <div className="mt-6 rounded-[22px] border border-line bg-paper p-8 md:p-10">
            <Kicker tone="verify">Included with registration</Kicker>
            <ul className="mt-5 grid gap-3 md:grid-cols-2">
              {INCLUDED.map((item) => (
                <li key={item} className="flex items-center gap-2.5 text-sm text-ink">
                  <span className="grid h-4 w-4 shrink-0 place-items-center rounded-full bg-verify-bg">
                    <span className="h-1.5 w-1.5 rounded-full bg-verify" />
                  </span>
                  {item}
                </li>
              ))}
            </ul>
          </div>
        </ScrollReveal>
      </div>
    </section>
  );
}
