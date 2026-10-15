import Link from "next/link";
import "./sample-report.css";

type Kind = "ok" | "down" | "second" | "missing";

/** One captured still: a flat silhouette. The report shows real camera photos; these are staged. */
function Thumb({ kind }: { kind: Kind }) {
  return (
    <svg viewBox="0 0 96 64" className={`rp-thumb k-${kind}`} aria-hidden="true">
      <rect width="96" height="64" rx="6" className="bg" />
      {kind !== "missing" && (
        <g className="me" transform={kind === "down" ? "translate(0 5) rotate(8 44 36)" : undefined}>
          <path d="M20 64 q24 -30 48 0z" />
          <circle cx="44" cy="30" r="14" />
        </g>
      )}
      {kind === "second" && (
        <g className="you">
          <path d="M62 64 q16 -22 32 0z" />
          <circle cx="78" cy="38" r="9" />
        </g>
      )}
      {kind === "missing" && <path className="empty" d="M0 52 H96" />}
    </svg>
  );
}

const FLAGS = [
  { at: 318, time: "5:18", label: "Possibly looking down (phone?)", kind: "down", ev: "down" },
  { at: 443, time: "7:23", label: "Possibly a second person in frame", kind: "second", ev: "second" },
  { at: 453, time: "7:33", label: "Possibly a second person in frame", kind: "second", ev: "second" },
  { at: 496, time: "8:16", label: "Face not visible on camera", kind: "missing", ev: "missing" },
] as const;

const CHECKINS = [
  { at: 46, time: "0:46" },
  { at: 136, time: "2:16" },
  { at: 480, time: "8:00" },
] as const;

const SKILLS: [string, number][] = [
  ["ETL", 62],
  ["SQL", 35],
  ["Spark", 68],
  ["Data quality", 52],
  ["Data modeling", 45],
  ["Query optimisation", 60],
];

const TOTAL = 540;
const pct = (s: number) => `${((s / TOTAL) * 100).toFixed(2)}%`;

/**
 * Pinned scene (handler `report` in effects.mjs): the interview plays, flags land in the report as they
 * happen, then grading fills the scores. Mirrors the structure of a real BrowseJobs interview report.
 * Staged data, anonymised candidate. At rest (narrow screens, reduced motion) it shows the finished report.
 */
export function SampleReport() {
  return (
    <section className="s-black scene sreport" data-scene="report" data-len="600vh" id="sample-report" data-phase="play" data-ev="">
      <div className="scene-pin chapter">
        <div className="wrap-wide">
          <div className="center rp-head">
            <p className="eyebrow">Sample report</p>
            <h2 className="h-section">How the report writes itself.</h2>
            <p className="lead">Scroll. The interview plays, flags land as they happen, and grading fills the scores last.</p>
          </div>

          <div className="rp-grid">
            <div className="rp-cam">
              <div className="rp-screen">
                <span className="rp-rec">
                  <i /> REC · <b className="rp-time">9:00 / 9:00</b>
                </span>
                <svg viewBox="0 0 320 200" aria-hidden="true">
                  <rect width="320" height="200" fill="#0a0a0b" />
                  <g className="rp-ghost">
                    <path d="M232 200 q40 -64 80 0z" />
                    <circle cx="272" cy="118" r="26" />
                  </g>
                  <g className="rp-me">
                    <path d="M92 200 q68 -78 136 0z" className="sh" />
                    <g className="rp-headg">
                      <circle cx="160" cy="98" r="46" className="skin" />
                      <path d="M114 90 q46 -62 92 0 q-8 -26 -46 -28 q-38 2 -46 28z" className="hair" />
                      <ellipse cx="144" cy="94" rx="8" ry="4.5" fill="#fff" />
                      <ellipse cx="176" cy="94" rx="8" ry="4.5" fill="#fff" />
                      <circle className="rp-pupil" cx="144" cy="94" r="3" />
                      <circle className="rp-pupil" cx="176" cy="94" r="3" />
                      <ellipse cx="160" cy="124" rx="11" ry="3" className="mouth" />
                    </g>
                  </g>
                  <g className="rp-box">
                    <path d="M104 44 v-14 h14 M216 44 v-14 h-14 M104 156 v14 h14 M216 156 v14 h-14" />
                  </g>
                  <rect className="rp-flash" width="320" height="200" fill="#fff" />
                </svg>
                <div className="rp-labels">
                  <span className="rp-lab ok">Monitoring · all clear</span>
                  <span className="rp-lab down">Possibly looking down</span>
                  <span className="rp-lab second">Possibly a second person</span>
                  <span className="rp-lab missing">Face not visible</span>
                  <span className="rp-lab check">Check-in photo taken</span>
                </div>
              </div>

              <div className="rp-line" aria-hidden="true">
                <div className="rp-track">
                  {CHECKINS.map((c) => (
                    <i key={c.at} className="tick chk" style={{ left: pct(c.at) }} />
                  ))}
                  {FLAGS.map((f) => (
                    <i key={f.at} className="tick flag" style={{ left: pct(f.at) }} />
                  ))}
                  <b className="rp-play" />
                </div>
                <div className="rp-axis">
                  <span>0:00</span>
                  <span>9:00</span>
                </div>
              </div>
              <p className="rp-key">
                <i className="chk" /> Random check-in photo <i className="flag" /> Flag
              </p>
            </div>

            <article className="rp-paper" aria-label="Sample interview report">
              <header>
                <p className="k">Interview report</p>
                <h3>Candidate A</h3>
                <p className="sub">Data Engineer · L1 role fit</p>
              </header>

              <div className="rp-cols">
                <div className="rp-left">
                  <div className="rp-score">
                    <b data-rscore>55</b>
                    <span>/100</span>
                    <em className="below">Below pass mark 60</em>
                  </div>
                  <ul className="rp-skills">
                    {SKILLS.map(([n, v]) => (
                      <li key={n} style={{ ["--v" as string]: v / 100 }}>
                        <span>{n}</span>
                        <i>
                          <u />
                        </i>
                        <b>{v}</b>
                      </li>
                    ))}
                  </ul>
                  <p className="rp-note">Graded after the interview, against the role&apos;s rubric. Scores stay empty until grading finishes.</p>
                </div>

                <div className="rp-right">
                  <p className="k">
                    Integrity <span className="rp-count" data-rcount>4</span> flags
                  </p>
                  <ol className="rp-flags">
                    {FLAGS.map((f) => (
                      <li key={f.at} data-at={f.at} data-flag="" data-ev={f.ev}>
                        <Thumb kind={f.kind} />
                        <div>
                          <b>{f.time} in</b>
                          <span>{f.label}</span>
                        </div>
                      </li>
                    ))}
                  </ol>
                  <p className="k" style={{ marginTop: 14 }}>
                    Check-in photos
                  </p>
                  <ul className="rp-checks">
                    {CHECKINS.map((c) => (
                      <li key={c.at} data-at={c.at} data-ev="check">
                        <Thumb kind="ok" />
                        <span>{c.time}</span>
                      </li>
                    ))}
                  </ul>
                  <p className="rp-note">
                    Camera estimates. Check the photo: they can misfire on posters, reflections or glasses. Flags are noted, not
                    punished.
                  </p>
                </div>
              </div>
            </article>
          </div>

          <div className="center rp-foot">
            <div className="cta-row">
              <Link className="btn btn-primary" href="/employers#get-started">
                Get reports like this
              </Link>
            </div>
            <p className="fine">
              Staged sample in the format of a real BrowseJobs interview report. Candidate anonymised. Lip-sync and voice analysis are
              not in this report yet.
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}
