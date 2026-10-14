import { fees, freeLadder } from "@/content/landing";
import "@/components/ap/pages/marketing.css";

const rupee = (n: number) => "₹" + Math.round(n).toLocaleString("en-IN");

/**
 * Registration covers the course. The placement fee is three months of CTC,
 * charged in EMIs only after an accepted offer. No worked rupee example:
 * the CTC is not known until the offer exists.
 */
const INCLUDED = [
  "Live instructor-led classes",
  "All class recordings, one year",
  "AI tutor, unlimited",
  "MCQ tests and mastery tracking",
  "Your first comprehensive CV",
  "Base mock-interview quota",
  "Student support desk",
] as const;

export function FeeModel() {
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
            This covers the course. Due only after the free masterclass and the free bootcamp. {fees.guaranteeDays}
            -day money-back guarantee, any reason, in writing.
          </p>
        </div>
        <div className="pg-fee is-dark">
          <p className="eyebrow-sm">Placement fee</p>
          <p className="pg-fee-strong">
            {fees.placement.monthsOfCtc} months of the CTC on the offer you accept. Charged in EMIs only after you
            accept. If you do not accept an offer, this fee is not charged.
          </p>
        </div>
      </div>

      <div className="pg-fee">
        <h3>Included with registration</h3>
        <ul>
          {INCLUDED.map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>
      </div>
    </div>
  );
}
