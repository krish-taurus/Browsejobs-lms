"use client";

import { useState } from "react";

const PHASES = ["Interview", "Score", "HR queue"] as const;
type Phase = 0 | 1 | 2;

/** Sample rubric rows. A real score stays empty until graded. */
const BARS = [
  { label: "Technical depth", amount: 0.86 },
  { label: "Communication", amount: 0.74 },
  { label: "Evidence of experience", amount: 0.9 },
] as const;

/**
 * The /get-hired hero sample screen in the Apple-direction design: interview
 * → rubric → the card lands in the HR priority queue. Light, static frames
 * (no autoplay); the tabs switch the frame. Opens on the finished frame.
 */
export function GetHiredScreen() {
  const [phase, setPhase] = useState<Phase>(2);
  const scored = phase >= 1;
  const queued = phase >= 2;

  return (
    <figure className="gh-screen" data-reveal="">
      <figcaption className="eyebrow-sm">Sample screen</figcaption>
      <div className="gh-phases" role="tablist" aria-label="Interview sequence">
        {PHASES.map((label, index) => (
          <button
            key={label}
            type="button"
            role="tab"
            aria-selected={phase === index}
            className={phase === index ? "is-on" : undefined}
            onClick={() => setPhase(index as Phase)}
          >
            {label}
          </button>
        ))}
      </div>
      <div className="gh-screen-grid">
        <div className="gh-card">
          <div className="gh-card-head">
            <span>AI interview</span>
            <span className="gh-muted">{phase === 0 ? "Live" : "Graded"}</span>
          </div>
          <p className="gh-quote">“Walk me through how you would find a duplicate row without guessing.”</p>
          <ul className="gh-bars">
            {BARS.map((bar) => (
              <li key={bar.label}>
                <div className="gh-bar-label">
                  <span>{bar.label}</span>
                  <span>{scored ? "Sample" : "—"}</span>
                </div>
                <div className="gh-bar" aria-hidden="true">
                  <i style={{ transform: `scaleX(${scored ? bar.amount : 0.08})` }} />
                </div>
              </li>
            ))}
          </ul>
          <div className="gh-card-foot">
            {!queued && <YouCard />}
            {scored && <span className="gh-pass">Cleared · sample</span>}
          </div>
        </div>
        <div className="gh-card gh-queue">
          <p className="gh-card-head">
            <span>In front of HR</span>
          </p>
          <p className="gh-muted">Ranked on skills and the mock. Sample rows — not people.</p>
          <ul>
            {queued ? (
              <li className="is-you">
                <span>
                  <b>You</b>
                  <small>Skills + mock</small>
                </span>
                <span className="gh-priority">Priority</span>
              </li>
            ) : (
              <li>Waiting on a grade</li>
            )}
            <li>Not yet interviewed</li>
            <li>Inbox — not a ranking</li>
          </ul>
        </div>
      </div>
      <p className="fine">Sample sequence. A real score stays empty until your screen is graded. Tap a step to hold the frame.</p>
    </figure>
  );
}

function YouCard() {
  return (
    <div className="gh-you">
      <b>You</b>
      <small>Skills + mock</small>
    </div>
  );
}
