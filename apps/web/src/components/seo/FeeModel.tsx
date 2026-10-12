import { fees, freeLadder, DISCLAIMER } from "@/content/landing";
import "@/components/ap/pages/marketing.css";

const rupee = (n: number) => "₹" + Math.round(n).toLocaleString("en-IN");

/**
 * Homepage worked example (₹12 LPA). Illustrative arithmetic only —
 * the placement fee is calculated from the offer the student accepts.
 * Rendered in the Apple-direction design (pg-* blocks, components/ap/pages/marketing.css).
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
    <div className="not-prose pg-fees">
      <ol className="pg-ladder">
        {freeLadder.map((step) => (
          <li key={step.step} className={step.free ? "is-free" : undefined}>
            <p className="eyebrow-sm">{step.free ? `Free · Step ${step.step}` : `Step ${step.step} · Paid`}</p>
            <p className="pg-step-title">{step.title}</p>
            <p>{step.body}</p>
          </li>
        ))}
      </ol>

      <div className="pg-fee-pair">
        <div className="pg-fee">
          <p className="eyebrow-sm">Registration</p>
          <p className="pg-price">{rupee(fees.registration)}</p>
          <p>
            Or {fees.registrationEmi.months} EMIs of {rupee(fees.registrationEmi.amount)}. Due only after the free
            masterclass and the free bootcamp. {fees.guaranteeDays}-day money-back guarantee, any reason, in writing.
          </p>
        </div>
        <div className="pg-fee is-dark">
          <p className="eyebrow-sm">Placement fee</p>
          <p className="pg-fee-strong">
            First {fees.placement.monthsOfCtc} months of the CTC on the offer you accept. Due only after you accept.
            Paid as {fees.placement.emis} monthly EMIs. The {rupee(fees.placement.adjusted)} registration is adjusted
            inside it.
          </p>
        </div>
      </div>

      <div className="pg-fee">
        <p className="eyebrow-sm">Worked example · ₹{EXAMPLE_LPA} LPA offer</p>
        <p>
          This is the same illustration as the homepage fee panel. It is not a salary we are offering you, and it is
          not a typical outcome.
        </p>
        <dl>
          <div>
            <dt>3 months of a ₹{EXAMPLE_LPA} LPA CTC</dt>
            <dd>{rupee(threeMonths)}</dd>
          </div>
          <div>
            <dt>Registration already paid</dt>
            <dd>− {rupee(fees.placement.adjusted)}</dd>
          </div>
          <div className="is-total">
            <dt>Placement fee, then {fees.placement.emis} EMIs</dt>
            <dd>
              {rupee(afterAdjustment)} · {rupee(perEmi)} / month
            </dd>
          </div>
        </dl>
        <p className="fine">{DISCLAIMER}</p>
      </div>

      <div className="pg-fee">
        <h3>Included with registration</h3>
        <ul>
          {INCLUDED.map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>
        <h3>Optional extras, priced separately</h3>
        <ul className="pg-price-list">
          {EXTRAS.map(([label, price]) => (
            <li key={label}>
              <span>{label}</span>
              <span>{price}</span>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
