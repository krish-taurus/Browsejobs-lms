import Link from "next/link";
import "./secure.css";

const COPY = {
  employers: {
    eyebrow: "Why teams trust it",
    heading: "An interview you can believe.",
    lead: "Scroll. Watch how a remote interview gets gamed, and the signal that exposes each move.",
  },
  home: {
    eyebrow: "Why your pass counts",
    heading: "Interviews employers trust.",
    lead: "Scroll. Every BrowseJobs interview checks the answers are yours, so a 75% means something to recruiters.",
  },
  page: {
    eyebrow: "How it catches cheating",
    heading: "Ways to cheat. Signals that catch them.",
    lead: "Scroll. Each pattern, and what the integrity layer reads to expose it.",
  },
} as const;

const STEPS = [
  { t: "Looking away to read", live: true, b: "Gaze drops to a phone or slides to a second screen. It is flagged as a possible, with a photo from that moment." },
  { t: "A second person in the room", live: true, b: "A second face in frame is flagged with a still, so your team can check it for themselves." },
  { t: "A voice that is not their face", live: false, b: "In pilot: audio that leads the mouth, a looped or synthetic video, and a second voice behind the answer." },
  { t: "Evidence, not a verdict", live: true, b: "Every flag lands in the report with its time and photo. A person decides. Nothing rejects on its own." },
] as const;

const READ_LINES = [0, 1, 2, 3, 4, 5, 6, 7];
const BARS = Array.from({ length: 44 }, (_, i) => i);

/**
 * Pinned, scroll-scrubbed story (scene handler `secure` in effects.mjs). Content is complete at rest:
 * on narrow screens and under reduced motion the scene plays once or shows its final state.
 * The demonstration is staged. Shared by home, /employers and /secure-interview so the claim cannot drift.
 */
export function SecureTrust({ audience }: { audience: keyof typeof COPY }) {
  const c = COPY[audience];
  return (
    <section className="s-black scene secure" data-scene="secure" data-len="640vh" id="secure" data-step="0">
      <div className="scene-pin chapter">
        <div className="wrap-wide">
          <div className="center sec-head">
            <p className="eyebrow">{c.eyebrow}</p>
            <h2 className="h-section">{c.heading}</h2>
            <p className="lead">{c.lead}</p>
          </div>

          <div className="sec-grid">
            <ol className="sec-steps">
              {STEPS.map((s, i) => (
                <li key={s.t} data-s={i} className={i === 0 ? "is-on" : undefined}>
                  <span className="sec-n">0{i + 1}</span>
                  <div>
                    <h3>
                      {s.t} <em className={s.live ? "tag live" : "tag"}>{s.live ? "Live today" : "In pilot"}</em>
                    </h3>
                    <p>{s.b}</p>
                    <i className="sec-bar" aria-hidden="true" />
                  </div>
                </li>
              ))}
            </ol>

            <div className="sec-stage" role="img" aria-label="Staged demonstration: the integrity layer flags gaze, lip-sync drift and a second voice during an interview, then reports the evidence.">
              <div className="sec-hud">
                <span className="sec-live">
                  <i /> LIVE · Integrity layer
                </span>
                <span className="sec-score">
                  <small>Integrity</small>
                  <b data-score>100</b>
                </span>
              </div>

              <svg className="sec-svg" viewBox="0 0 640 400" aria-hidden="true">
                <defs>
                  <linearGradient id="sec-scan" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0" stopColor="#30d158" stopOpacity="0" />
                    <stop offset="1" stopColor="#30d158" stopOpacity=".35" />
                  </linearGradient>
                </defs>
                <g className="sec-grid-bg">
                  {Array.from({ length: 9 }, (_, i) => (
                    <path key={i} d={`M0 ${i * 50} H640`} />
                  ))}
                  {Array.from({ length: 13 }, (_, i) => (
                    <path key={i} d={`M${i * 50} 0 V400`} />
                  ))}
                </g>

                {/* second screen the candidate reads from */}
                <g className="sec-panel">
                  <rect x="436" y="78" width="176" height="150" rx="10" />
                  {READ_LINES.map((i) => (
                    <rect key={i} className="sec-ln" data-i={i} x="450" y={92 + i * 16} width={118 - ((i * 23) % 40)} height="5" rx="2.5" />
                  ))}
                  <rect className="sec-hl" x="444" y="86" width="160" height="14" rx="4" />
                </g>

                {/* off-camera second person */}
                <g className="sec-ghost">
                  <circle cx="64" cy="236" r="26" />
                  <path d="M20 330 q44 -64 88 0z" />
                  <circle className="sec-ring" cx="64" cy="236" r="26" />
                  <circle className="sec-ring r2" cx="64" cy="236" r="26" />
                </g>

                {/* candidate */}
                <g className="sec-person">
                  <path d="M170 360 q92 -96 184 0z" className="sec-shoulders" />
                  <circle cx="262" cy="212" r="78" className="sec-skull" />
                  <path d="M186 200 q76 -92 152 0 q-10 -40 -76 -44 q-66 4 -76 44z" className="sec-hair" />
                  <ellipse cx="236" cy="208" rx="11" ry="6" fill="#fff" />
                  <ellipse cx="288" cy="208" rx="11" ry="6" fill="#fff" />
                  <circle className="sec-pupil" cx="236" cy="208" r="4" />
                  <circle className="sec-pupil" cx="288" cy="208" r="4" />
                  <ellipse className="sec-mouth" cx="262" cy="252" rx="16" ry="4" />
                  <rect className="sec-scanline" x="180" y="134" width="164" height="38" fill="url(#sec-scan)" />
                </g>

                {/* tracking box + gaze beam */}
                <g className="sec-track">
                  <path d="M178 140 v-18 h18 M346 140 v-18 h-18 M178 290 v18 h18 M346 290 v18 h-18" />
                </g>
                <path className="sec-beam" d="M288 208 L436 156" pathLength="1" />

                {/* audio vs mouth */}
                <g className="sec-wave">
                  <text x="0" y="338">AUDIO</text>
                  <text x="0" y="366">MOUTH</text>
                  <g className="sec-row a">
                    {BARS.map((i) => (
                      <rect key={i} x={60 + i * 12.6} y="326" width="7" height="16" rx="3" style={{ animationDelay: `${(i % 9) * 60}ms` }} />
                    ))}
                  </g>
                  <g className="sec-row m">
                    {BARS.map((i) => (
                      <rect key={i} x={60 + i * 12.6} y="354" width="7" height="16" rx="3" style={{ animationDelay: `${(i % 9) * 60}ms` }} />
                    ))}
                  </g>
                </g>
              </svg>

              <div className="sec-chips">
                <span className="chip c0">GAZE · possibly looking down</span>
                <span className="chip c1">SECOND PERSON · possibly in frame</span>
                <span className="chip c2">
                  LIP-SYNC · drift <b className="sec-delta">0 ms</b> · in pilot
                </span>
              </div>

              <div className="sec-report">
                <p className="eyebrow-sm">Integrity report</p>
                <ul>
                  <li>
                    <i className="c0" /> Possibly looking down · 5:18
                  </li>
                  <li>
                    <i className="c1" /> Possibly a second person · 7:23
                  </li>
                  <li>
                    <i className="c1" /> Face not visible · 8:16
                  </li>
                </ul>
                <p className="sec-decide">Photos attached. Decision: your team.</p>
              </div>
            </div>
          </div>

          <div className="center sec-foot">
            <div className="cta-row">
              <Link className="btn btn-primary" href="/secure-interview">
                How we keep it honest
              </Link>
            </div>
            <p className="fine">
              Staged demonstration. Tab, paste, face and gaze checks run in interviews today; they are camera estimates and can misfire on glasses or reflections. Lip-sync and voice analysis are in pilot. A flag sends the interview to a person and never rejects anyone on its own.
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}
