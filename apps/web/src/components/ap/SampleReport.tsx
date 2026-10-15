import Link from "next/link";
import "./sample-report.css";

const IMG = (n: number) => `/media/secure-interview/report-${n}.jpg`;

/** A captured still from a test interview. A bystander's face is blurred before publishing. */
function Thumb({ n, alt, flagged }: { n: number; alt: string; flagged?: boolean }) {
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={IMG(n)} alt={alt} width={96} height={72} loading="lazy" className={`rp-thumb${flagged ? " is-flag" : ""}`} />
  );
}

const FLAGS = [
  { at: 318, time: "5:18", label: "Possibly looking down (phone?)", img: 1, ev: "down" },
  { at: 443, time: "7:23", label: "Possibly a second person in frame", img: 2, ev: "second" },
  { at: 453, time: "7:33", label: "Possibly a second person in frame", img: 3, ev: "second" },
  { at: 496, time: "8:16", label: "Face not visible on camera", img: 4, ev: "missing" },
] as const;

const CHECKINS = [
  { at: 46, time: "0:46", img: 5 },
  { at: 136, time: "2:16", img: 6 },
  { at: 480, time: "8:00", img: 7 },
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
 * Photos are from a test interview recorded with consent; the candidate is labelled "Candidate A".
 * At rest (narrow screens, reduced motion) it shows the finished report.
 */
export function SampleReport() {
  return (
    <section className="s-black scene sreport" data-scene="report" data-len="600vh" id="sample-report" data-phase="play" data-ev="" data-img="5">
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
                <div className="rp-photos">
                  {[5, 1, 2, 3, 4, 6, 7].map((n) => (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img key={n} className={`ph p${n}`} src={IMG(n)} alt="" width={480} height={360} loading="lazy" />
                  ))}
                </div>
                <svg className="rp-ov" viewBox="0 0 480 360" aria-hidden="true">
                  <g className="rp-box">
                    <rect className="me" width="150" height="215" rx="10" />
                  </g>
                  <rect className="rp-bys" x="2" y="60" width="96" height="112" rx="10" />
                  <rect className="rp-flash" width="480" height="360" fill="#fff" />
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
                      <li key={f.at} data-at={f.at} data-flag="" data-ev={f.ev} data-img={f.img}>
                        <Thumb n={f.img} alt={`${f.label} at ${f.time}`} flagged />
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
                      <li key={c.at} data-at={c.at} data-ev="check" data-img={c.img}>
                        <Thumb n={c.img} alt={`Check-in photo at ${c.time}`} />
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
              Sample in the format of a real BrowseJobs interview report, from a test interview recorded with consent. A
              bystander&apos;s face is blurred. Lip-sync and voice analysis are not in this report yet.
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}
