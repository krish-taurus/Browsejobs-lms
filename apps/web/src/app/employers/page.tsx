import { ApShell } from "@/components/ap/ApShell";
import { ApTaurusFilm } from "@/components/ap/ApFilm";
import { SDays, SFaq, SGetStarted, SHow, SOverview, STop } from "@/components/ap/generated/employers";
import employersLd from "@/components/ap/ld/employers.json";

/** For employers, in the approved Apple-direction design (Oct 2026). Metadata lives in layout.tsx. */
export default function EmployersRoute() {
  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(employersLd) }} />
      <ApShell current="employers" staticNav cta={{ label: "Onboard", href: "#get-started" }}>
        <nav className="lnav lnav--dark" aria-label="AI Recruiter">
          <div className="lnav-inner">
            <span className="lnav-title">
              BrowseJobs AI Recruiter<small>Powered by Taurus AI</small>
            </span>
            <div className="lnav-right">
              <ul className="lnav-links">
                <li>
                  <a href="#overview">Overview</a>
                </li>
                <li>
                  <a href="#how">How it works</a>
                </li>
                <li>
                  <a href="#taurus-demo">Demo</a>
                </li>
                <li>
                  <a href="#faq">FAQ</a>
                </li>
              </ul>
              <a className="btn btn-primary" href="#get-started">
                Onboard with us
              </a>
            </div>
          </div>
        </nav>
        <STop />
        <SDays />
        <SOverview />
        <SHow />
        <ApTaurusFilm />
        <SFaq />
        <SGetStarted />
      </ApShell>
    </>
  );
}
