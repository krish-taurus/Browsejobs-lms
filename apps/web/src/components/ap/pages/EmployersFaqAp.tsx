import Link from "next/link";
import { ApShell } from "@/components/ap/ApShell";
import { EMPLOYER_FAQ, EMPLOYER_WAYS } from "@/content/employer-landing";
import { DISCLAIMER } from "@/content/landing";
import "./employers-sub.css";

const MORE = [
  { label: "How the AI Recruiter works", href: "/employers/how-it-works" },
  { label: "See a sample report", href: "/employers/how-it-works#report" },
  { label: "Watch the demo", href: "/employers/mission-control-demo" },
  { label: "Taurus AI, the engine behind it", href: "/taurusai" },
] as const;

/** /employers/faq in the Apple-direction design. The questions are EMPLOYER_FAQ, the same list as the FAQPage JSON-LD. */
export function EmployersFaqAp() {
  return (
    <ApShell current="employers" staticNav cta={{ label: "Onboard", href: "/employers/enquire" }}>
      <nav className="lnav lnav--dark" aria-label="AI Recruiter">
        <div className="lnav-inner">
          <span className="lnav-title">
            BrowseJobs AI Recruiter<small>Powered by Taurus AI</small>
          </span>
          <div className="lnav-right">
            <ul className="lnav-links">
              <li>
                <a href="#questions">Questions</a>
              </li>
              <li>
                <a href="#more">Learn more</a>
              </li>
              <li>
                <a href="#get-started">Get started</a>
              </li>
            </ul>
            <Link className="btn btn-primary" href="/employers/enquire">
              Onboard with us
            </Link>
          </div>
        </div>
      </nav>

      <section className="s-black ehw-hero" id="top">
        <div className="wrap center">
          <p className="eyebrow">Employer questions</p>
          <h1 className="h-hero">Questions.</h1>
          <p className="lead">
            Straight answers on the 3-day path, the 75% clear mark, the four bots, and what BrowseJobs will not promise. Nobody can guarantee a hire.
          </p>
          <p className="fine">{DISCLAIMER}</p>
        </div>
      </section>

      <section className="s-white chapter" id="questions">
        <div className="wrap center">
          <h2 className="h-section" style={{ fontSize: "clamp(32px,4.6vw,56px)" }}>
            What employers ask us.
          </h2>
          <div className="faq efq-list">
            {EMPLOYER_FAQ.map((item) => (
              <details key={item.q}>
                <summary>
                  <h3 className="q">{item.q}</h3>
                </summary>
                <p className="answer">{item.a}</p>
              </details>
            ))}
          </div>
          <p className="fine">{DISCLAIMER}</p>
        </div>
      </section>

      <section className="s-paper chapter" id="more">
        <div className="wrap center">
          <h2 className="h-section" style={{ fontSize: "clamp(32px,4.6vw,56px)" }}>
            See it for yourself.
          </h2>
          <p className="lead">The bots, the path, a labelled sample report with an example candidate, and the demo.</p>
          <ul className="rows" style={{ textAlign: "left" }}>
            {MORE.map((row) => (
              <li key={row.href}>
                <Link href={row.href}>
                  {row.label} <span className="chev" aria-hidden="true">›</span>
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section className="s-black chapter" id="get-started">
        <div className="wrap center">
          <h2 className="h-hero" style={{ fontSize: "clamp(44px,7vw,80px)" }}>
            Onboard with us.
          </h2>
          <p className="lead">Choose how you want to work with us. We call you back either way.</p>
          <div className="choice-cards">
            {EMPLOYER_WAYS.map((way) => (
              <Link key={way.id} className="choice" href={way.href}>
                <h3 className="h-card">{way.title}</h3>
                <p className="body">{way.body}</p>
                <span className="more">
                  Choose this <span className="chev" aria-hidden="true">›</span>
                </span>
              </Link>
            ))}
          </div>
          <div className="cta-row">
            <Link className="btn btn-primary" href="/employers/enquire">
              Get started
            </Link>
          </div>
        </div>
      </section>
    </ApShell>
  );
}
