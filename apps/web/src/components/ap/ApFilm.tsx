import Link from "next/link";
import { InViewVideo } from "@/components/argus/InViewVideo";

/** The 41-second candidate-journey film (Higgsfield renders + HyperFrames), as a light chapter. */
export function ApJourneyFilm() {
  return (
    <section className="s-paper chapter" id="journey-film">
      <div className="wrap center">
        <p className="eyebrow">Watch it</p>
        <h2 className="h-section">Your path to an interview call, in 41 seconds.</h2>
        <div className="film-tile">
          <InViewVideo
            src="/media/journey/candidate-journey.mp4"
            poster="/media/journey/candidate-journey-poster.jpg"
            label="How BrowseJobs works for candidates: share your CV, take the free AI interview, get your score, and clear 75% to reach recruiters"
          />
        </div>
        <ul className="film-steps">
          <li>
            <b>01</b> Share your CV
          </li>
          <li>
            <b>02</b> About 15 questions
          </li>
          <li>
            <b>03</b> Score out of 100
          </li>
          <li>
            <b>04</b> 75% puts you in front of HR
          </li>
          <li>
            <b>05</b> Below 75%? Free help and a retake
          </li>
        </ul>
      </div>
    </section>
  );
}

/** The Taurus hiring story, recorded from /taurusai/hiring-demo, as a dark chapter for employers. */
export function ApTaurusFilm() {
  return (
    <section className="s-black chapter" id="taurus-demo">
      <div className="wrap center">
        <p className="eyebrow">Powered by Taurus AI</p>
        <h2 className="h-section">Hire over WhatsApp. Watch every step.</h2>
        <p className="lead">
          Ask for the people you need in a WhatsApp message. Taurus screens, calls and interviews candidates, and checks with you in the chat before every
          step that matters.
        </p>
        <div className="film-tile" style={{ background: "var(--black-3)" }}>
          <InViewVideo
            src="/media/taurus/taurus-hiring-story.mp4"
            poster="/media/taurus/taurus-hiring-story-poster.jpg"
            label="Taurus hiring demo: a role filled through WhatsApp, from request to offer"
          />
        </div>
        <ul className="film-steps">
          <li>WhatsApp request</li>
          <li>CV screening</li>
          <li>AI screening calls</li>
          <li>Shortlist on WhatsApp</li>
          <li>L1 and L2</li>
          <li>Optional pre-BGV</li>
          <li>Your interview</li>
          <li>
            <b>Offer only on your approval</b>
          </li>
        </ul>
        <div className="cta-row" style={{ justifyContent: "center", marginTop: 26 }}>
          <Link className="btn btn-primary" href="/taurusai/hiring-demo">
            Try the interactive demo
          </Link>
          <Link className="more" href="/taurusai/recruitment">
            How Taurus hiring works <span className="chev" aria-hidden="true">›</span>
          </Link>
        </div>
        <p className="fine">An illustration with sample numbers, recorded from the live Taurus demo.</p>
      </div>
    </section>
  );
}
