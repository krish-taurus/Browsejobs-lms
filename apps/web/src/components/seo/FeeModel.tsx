import { Disclaimer } from "@/components/brand/Disclaimer";
import { fees, freeLadder } from "@/content/landing";

const rupee = (n: number) => "₹" + Math.round(n).toLocaleString("en-IN");

/**
 * Homepage worked example (₹12 LPA). Illustrative arithmetic only —
 * the placement fee is calculated from the offer the student accepts.
 */
const EXAMPLE_LPA = 12;

const INCLUDED = [
  "Live instructor-led classes",
  "All class recordings, one year",
  "AI tutor, unlimited",
  "MCQ tests and mastery tracking",
  "Your first comprehensive CV",
  "Base mock-interview quota",
  "Student support desk",
] as const;

const EXTRAS = [
  ["Extra CV credits", "₹99 / 3"],
  ["Voice mock interview", "₹249 · ₹599 / 3"],
  ["Extra 1:1 mentor session", "₹499"],
  ["Career+ (post-placement)", "₹499 / mo"],
] as const;

export function FeeModel() {
  const annual = EXAMPLE_LPA * 100000;
  const threeMonths = annual / 4;
  const afterAdjustment = threeMonths - fees.placement.adjusted;
  const perEmi = afterAdjustment / fees.placement.emis;

  return (
    <div className="not-prose mt-8 space-y-4">
      <ol className="space-y-3">
        {freeLadder.map((step) => (
          <li
            key={step.step}
            className={`rounded-[14px] border p-5 ${step.free ? "border-verify/30 bg-verify-bg" : "border-line bg-white"}`}
          >
            <p className={`mono text-[11px] ${step.free ? "text-verify" : "text-trust"}`}>
              {step.free ? `FREE · STEP ${step.step}` : `STEP ${step.step} · PAID`}
            </p>
            <p className="mt-1 font-semibold text-ink">{step.title}</p>
            <p className="mt-1 text-sm leading-relaxed text-ink2">{step.body}</p>
          </li>
        ))}
      </ol>

      <div className="grid gap-4 md:grid-cols-2">
        <div className="rounded-[14px] border border-line bg-white p-5">
          <p className="kicker text-trust">Registration</p>
          <p className="mono mt-3 text-3xl text-ink">{rupee(fees.registration)}</p>
          <p className="mt-2 text-sm leading-relaxed text-ink2">
            Or {fees.registrationEmi.months} EMIs of {rupee(fees.registrationEmi.amount)}. Due only after the free
            masterclass and the free bootcamp. {fees.guaranteeDays}-day money-back guarantee, any reason, in writing.
          </p>
        </div>
        <div className="rounded-[14px] bg-ink p-5 text-white">
          <p className="kicker text-sky/80">Placement fee</p>
          <p className="mt-3 text-sm leading-relaxed text-sky/90">
            First {fees.placement.monthsOfCtc} months of the CTC on the offer you accept. Due only after you accept.
            Paid as {fees.placement.emis} monthly EMIs. The {rupee(fees.placement.adjusted)} registration is adjusted
            inside it.
          </p>
        </div>
      </div>

      <div className="rounded-[14px] border border-line bg-white p-5">
        <p className="kicker text-muted">Worked example · ₹{EXAMPLE_LPA} LPA offer</p>
        <p className="mt-2 text-sm leading-relaxed text-ink2">
          This is the same illustration as the homepage fee panel. It is not a salary we are offering you, and it is
          not a typical outcome.
        </p>
        <dl className="mt-4 space-y-2 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-muted">3 months of a ₹{EXAMPLE_LPA} LPA CTC</dt>
            <dd className="mono text-ink">{rupee(threeMonths)}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-muted">Registration already paid</dt>
            <dd className="mono text-ink">− {rupee(fees.placement.adjusted)}</dd>
          </div>
          <div className="flex justify-between gap-4 border-t border-line pt-2 font-semibold">
            <dt className="text-ink">Placement fee, then {fees.placement.emis} EMIs</dt>
            <dd className="mono text-ink">
              {rupee(afterAdjustment)} · {rupee(perEmi)} / month
            </dd>
          </div>
        </dl>
        <Disclaimer className="mt-4" />
      </div>

      <div className="rounded-[14px] border border-line bg-paper p-5">
        <p className="font-semibold text-ink">Included with registration</p>
        <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-ink2">
          {INCLUDED.map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>
        <p className="mt-4 font-semibold text-ink">Optional extras, priced separately</p>
        <ul className="mt-3 space-y-1 text-sm text-ink2">
          {EXTRAS.map(([label, price]) => (
            <li key={label} className="flex justify-between gap-4">
              <span>{label}</span>
              <span className="mono text-ink">{price}</span>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
