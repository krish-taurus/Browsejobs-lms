/* Generated from the Apple-direction demo (scratchpad/port/gen.py), then wired by hand where marked. */
import Link from "next/link";
import type { CSSProperties } from "react";

export function STop() {
  return (
    <section className="s-black emp-hero" id="top">
  <div className="wrap center">
    <p className="eyebrow">For employers</p>
    <h1 className="h-hero"><span>Your AI</span> <span>Recruiter.</span></h1>
    <p className="lead">Hire in about <b>3 days</b> instead of <b>90.</b><sup>1</sup> You only meet people who already passed our AI interview. You still decide who gets the offer.</p>
    <p className="fine">Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.</p>
    <div className="cta-row"><a className="btn btn-primary" href="#get-started">Onboard with us</a><a className="more" href="#how">See how it works <span className="chev" aria-hidden="true">›</span></a></div>
  </div>
  <div className="eclipse" aria-hidden="true"></div>
</section>
  );
}

export function SDays() {
  return (
    <section className="s-paper scene" data-scene="days" data-len="280vh" id="days" style={{ "--pin-top": "var(--lnav-h)" } as CSSProperties}>
  <div className="scene-pin chapter">
    <div className="wrap">
      <div className="center">
        <h2 className="h-section">Where do 90 days of hiring go?</h2>
        <p className="short-answer"><b>Short answer:</b> into finding people, calls, scheduling, interviews and checks. We take that work off your team, so a hire takes about 3 days.<sup>1</sup></p>
      </div>
      
<div className="days-grid">
  <div className="days-list usual"><h3>Usually<small>About 90 days</small></h3><ul><li>Finding candidates</li><li>Screening calls</li><li>Booking interview rounds</li><li>Interviews</li><li>Background checks</li><li>The offer</li><li>People dropping out</li></ul></div>
  <div className="days-count">
    <div className="stage3d" data-3d="days" role="img" aria-label="90 cubes, one for each day of a usual hire, shrinking down to 3"><div className="stage-fallback"></div></div>
    <span className="big" aria-hidden="true">3</span><span className="unit">About 3 days</span>
  </div>
  <div className="days-list ours"><h3>With BrowseJobs<small>About 3 days</small></h3><ul><li>People who already passed our AI interview</li><li>We call and screen the shortlist</li><li>We run your first and second interview rounds (L1, L2)</li><li>Background check before joining (pre-BGV)</li><li>An offer ready for you to approve</li></ul></div>
</div>
      <div className="center"><p className="fine">1. Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.</p></div>
    </div>
  </div>
</section>
  );
}

export function SOverview() {
  return (
    <section className="s-white chapter" id="overview">
  <div className="wrap">
    <div className="center" style={{ marginBottom: "clamp(28px,4vw,48px)" } as CSSProperties}><h2 className="h-section" style={{ fontSize: "clamp(32px,4.6vw,56px)" } as CSSProperties}>What we do, and what you decide.</h2></div>
    <div className="tiles-3" data-reveal-kids="">
      <article className="tile tile-pad"><p className="eyebrow-sm num">01</p><h3 className="h-tile" style={{ marginTop: "10px" } as CSSProperties}>Who does what.</h3><p className="body">We do the work from finding candidates to background checks. You meet people who already passed. A person on your team always approves the offer.</p></article>
      <article className="tile tile-pad"><p className="eyebrow-sm num">02</p><h3 className="h-tile" style={{ marginTop: "10px" } as CSSProperties}>What you see.</h3><p className="body">One screen shows the role, the shortlist, calls, interview rounds, background checks, and the offer waiting for you. The screen on this site uses sample data.</p></article>
      <article className="tile tile-pad"><p className="eyebrow-sm num">03</p><h3 className="h-tile" style={{ marginTop: "10px" } as CSSProperties}>What you get.</h3><ul className="check-list"><li>Time saved.</li><li>Fewer interviews for your team.</li><li>Only people who already passed.</li><li>You still decide the offer.</li></ul></article>
    </div>
    <div className="cta-row" style={{ justifyContent: "center" } as CSSProperties}><Link className="btn btn-primary" href="/employers#get-started">Onboard with us</Link></div>
  </div>
</section>
  );
}

export function SHow() {
  return (
    <section className="s-white chapter" id="how" style={{ paddingTop: "0" } as CSSProperties}>
  <div className="wrap center" style={{ marginBottom: "clamp(20px,3vw,40px)" } as CSSProperties}><h2 className="h-section" style={{ fontSize: "clamp(32px,4.6vw,56px)" } as CSSProperties}>How it works, step by step.</h2></div>
  <div className="wrap">
    <div className="floor-story" data-floor="">
      <div className="floor-text"><div className="floor-rail" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div><article className="floor-step" style={{ order: "0" } as CSSProperties}><span className="n">Step 1 of 5</span><h3 className="h-tile">Tell it the role.</h3><p className="lead" style={{ fontSize: "clamp(17px,1.8vw,21px)" } as CSSProperties}>Type the role, or say it out loud. The hiring screen starts from that one line.</p></article><article className="floor-step" style={{ order: "2" } as CSSProperties}><span className="n">Step 2 of 5</span><h3 className="h-tile">We call and screen.</h3><p className="lead" style={{ fontSize: "clamp(17px,1.8vw,21px)" } as CSSProperties}>We call the shortlisted people and screen them. You see who was called, how it went, and what they said.</p></article><article className="floor-step" style={{ order: "4" } as CSSProperties}><span className="n">Step 3 of 5</span><h3 className="h-tile">They take the AI interview first.</h3><p className="lead" style={{ fontSize: "clamp(17px,1.8vw,21px)" } as CSSProperties}>Every candidate takes a free AI interview. 75% or more counts as a pass, which we call a clear.</p><p className="fine">Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.</p></article><article className="floor-step" style={{ order: "6" } as CSSProperties}><span className="n">Step 4 of 5</span><h3 className="h-tile">First round, second round, background check.</h3><p className="lead" style={{ fontSize: "clamp(17px,1.8vw,21px)" } as CSSProperties}>We run your first and second interview rounds (L1 and L2), then a background check before joining (pre-BGV). You see the status of each one.</p></article><article className="floor-step" style={{ order: "8" } as CSSProperties}><span className="n">Step 5 of 5</span><h3 className="h-tile">You approve the offer.</h3><p className="lead" style={{ fontSize: "clamp(17px,1.8vw,21px)" } as CSSProperties}>The offer waits for you. A person must always approve and send the offer letter.</p></article></div>
      <div className="floor-stage"><div className="floor-device"><div className="floor-state" style={{ order: "1" } as CSSProperties} aria-label="Sample hiring screen, step 1"><div className="fs-bar"><span>Hiring screen</span><span className="badge">Sample data</span></div><div className="fs-body"><div className="prompt"><span className="mic"><svg viewBox="0 0 16 16" aria-hidden="true"><rect x="5.5" y="1.5" width="5" height="9" rx="2.5" fill="#fff" /><path d="M3 8a5 5 0 0 0 10 0M8 13v2" stroke="#fff" fill="none" strokeWidth="1.4" strokeLinecap="round" /></svg></span><span className="typed" data-text="Backend engineers, Hyderabad.">Backend engineers, Hyderabad.</span></div><p className="fine" style={{ margin: "0" } as CSSProperties}>Demo data. Not a live hiring desk.</p></div></div><div className="floor-state" style={{ order: "3" } as CSSProperties} aria-label="Sample hiring screen, step 2"><div className="fs-bar"><span>Hiring screen</span><span className="badge">Sample data</span></div><div className="fs-body"><div className="call-row"><span>Sample Asha Iyer</span><span className="chip done" data-final="Screened ✓" data-delay="0">Screened ✓</span></div><div className="call-row"><span>Sample Rohan Mehta</span><span className="chip done" data-final="Screened ✓" data-delay="500">Screened ✓</span></div><div className="call-row"><span>Sample Meera Nair</span><span className="chip calling" data-final="Calling…" data-delay="0">Calling…</span></div></div></div><div className="floor-state" style={{ order: "5" } as CSSProperties} aria-label="Sample hiring screen, step 3"><div className="fs-bar"><span>Hiring screen</span><span className="badge">Sample data</span></div><div className="fs-body" style={{ alignItems: "center" } as CSSProperties}><div className="score-dial" style={{ width: "min(100%,240px)" } as CSSProperties}><svg className="ring" viewBox="0 0 120 120" aria-hidden="true" style={{ "--fill": ".75" } as CSSProperties}><circle className="ring-track" cx="60" cy="60" r="54" pathLength="100" style={{ stroke: "var(--line)" } as CSSProperties} /><circle className="ring-fill" cx="60" cy="60" r="54" pathLength="100" style={{ stroke: "var(--ink)", transition: "stroke-dashoffset 1.4s var(--ease)" } as CSSProperties} /></svg><div className="score-read"><span className="big" style={{ fontSize: "56px" } as CSSProperties}>75%</span><span className="label" style={{ color: "var(--ink)" } as CSSProperties}>Clear</span></div></div></div></div><div className="floor-state" style={{ order: "7" } as CSSProperties} aria-label="Sample hiring screen, step 4"><div className="fs-bar"><span>Hiring screen</span><span className="badge">Sample data</span></div><div className="fs-body"><div className="stepper"><div><b>1</b>L1 round</div><div><b>2</b>L2 round</div><div><b>3</b>Pre-BGV</div></div></div></div><div className="floor-state" style={{ order: "9" } as CSSProperties} aria-label="Sample hiring screen, step 5"><div className="fs-bar"><span>Hiring screen</span><span className="badge">Sample data</span></div><div className="fs-body"><div className="offer"><div className="row"><span className="title">Offer letter</span><span className="chip">Waiting for you</span></div><div className="lines" aria-hidden="true"><i></i><i></i><i></i></div><p className="fine" style={{ margin: "0 0 14px" } as CSSProperties}>A person approves it. Nothing is emailed automatically.</p><button className="btn btn-primary" type="button" data-demo="Sample only. Approve never fires on its own, and nothing is emailed automatically.">Approve</button></div></div></div></div></div>
    </div>
  </div>
</section>
  );
}

export function SFaq() {
  return (
    <section className="s-paper chapter" id="faq">
  <div className="wrap center">
    <h2 className="h-section">Questions employers ask.</h2>
    <div className="faq"><details><summary><h3 className="q">How does hiring go from 90 days to about 3 days?</h3></summary><p className="answer">You share the role on WhatsApp on day 0. Screening, a proctored AI interview, a report, your interview with people who already passed, a background check and the offer all follow on one automated path. That path takes about 3 days. The usual way takes about 90 days of manual calls.</p></details><details><summary><h3 className="q">What does a clear score mean?</h3></summary><p className="answer">A clear means the candidate scored 75% or more in a proctored AI interview. You only interview candidates who cleared. Anyone below 75% is not sent to you as a clear.</p></details><details><summary><h3 className="q">Can we hire with you, or use the tool ourselves?</h3></summary><p className="answer">Both. You can make BrowseJobs your hiring partner and we run the path with you, or your team can use the same tool for your own hiring.</p></details></div><p style={{ marginTop: "24px" } as CSSProperties}><Link className="more" href="/employers/faq">Read all employer questions <span className="chev" aria-hidden="true">›</span></Link></p>
  </div>
</section>
  );
}

export function SGetStarted() {
  return (
    <section className="s-black chapter" id="get-started">
  <div className="wrap center">
    <h2 className="h-hero" style={{ fontSize: "clamp(44px,7vw,80px)" } as CSSProperties}>Onboard with us.</h2>
    <p className="lead">Choose how you want to work with us. We call you back either way.</p>
    <div className="choice-cards">
      <Link className="choice" href="/employers/enquire?path=partner"><h3 className="h-card">We hire for you</h3><p className="body">Make BrowseJobs your hiring partner. You share the role on WhatsApp. Our bots screen, interview and check candidates, and keep each one updated. You meet people who already passed.</p><span className="more">Choose this <span className="chev" aria-hidden="true">›</span></span></Link>
      <Link className="choice" href="/employers/enquire?path=tool"><h3 className="h-card">You use our tool</h3><p className="body">Your own team runs the same bots on the roles you hire for. You stay in charge. The interview, the report and the WhatsApp updates still happen.</p><span className="more">Choose this <span className="chev" aria-hidden="true">›</span></span></Link>
    </div>
  </div>
</section>
  );
}
