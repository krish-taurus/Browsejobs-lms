import Link from "next/link";
import type { CSSProperties } from "react";
import { ApShell } from "@/components/ap/ApShell";
import { ApTaurusFilm } from "@/components/ap/ApFilm";
import { EMPLOYER_BOTS, EMPLOYER_WAYS, HIRING_JOURNEY, SAMPLE_REPORT } from "@/content/employer-landing";
import { hiringStages } from "@/content/home";
import { DISCLAIMER } from "@/content/landing";
import "./employers-sub.css";

type BotId = (typeof EMPLOYER_BOTS)[number]["id"];

/** /employers/how-it-works in the Apple-direction design: bots, the 3-day path, the labelled sample report, the stages. */
export function EmployersHowItWorksAp() {
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
                <a href="#bots">Bots</a>
              </li>
              <li>
                <a href="#journey">Journey</a>
              </li>
              <li>
                <a href="#report">Report</a>
              </li>
              <li>
                <a href="#stages">Stages</a>
              </li>
              <li>
                <a href="#taurus-demo">Demo</a>
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
          <p className="eyebrow">How the AI Recruiter works</p>
          <h1 className="h-hero">
            <span>We reverse-engineered</span> <span>hiring.</span>
          </h1>
          <p className="lead">
            From a message to about 3 days. We call and screen, run the rounds and pre-BGV, and prepare the offer. A person always releases it.
          </p>
          <p className="fine">{DISCLAIMER}</p>
          <div className="cta-row">
            <Link className="btn btn-primary" href="/employers/enquire">
              Get started
            </Link>
            <Link className="more" href="/employers/mission-control-demo">
              Watch the demo <span className="chev" aria-hidden="true">›</span>
            </Link>
          </div>
        </div>
        <div className="eclipse" aria-hidden="true"></div>
      </section>

      <section className="s-white chapter" id="bots">
        <div className="wrap">
          <div className="center ehw-head">
            <h2 className="h-section">A message. Then the bots.</h2>
            <p className="lead">Each bot has one job. You do not chase people by hand.</p>
          </div>
          <ol className="tiles-2 ehw-bots" data-reveal-kids="">
            {EMPLOYER_BOTS.map((bot) => (
              <li key={bot.id} className="tile ehw-bot">
                <div className="ehw-art" data-art="" aria-hidden="true">
                  <BotArt id={bot.id} />
                </div>
                <div className="tile-pad">
                  <p className="eyebrow-sm">Bot {bot.mark}</p>
                  <h3 className="h-tile">{bot.name}</h3>
                  <p className="body">{bot.body}</p>
                </div>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="s-paper chapter" id="journey">
        <div className="wrap">
          <div className="center ehw-head">
            <h2 className="h-section">From the message to day 3.</h2>
            <p className="lead">The old way is 90 days of manual calls. This path is about 3 days.</p>
            <p className="fine">{DISCLAIMER}</p>
          </div>
          <div className="ehw-journey">
            <ol className="ehw-steps" data-reveal-kids="">
              {HIRING_JOURNEY.map((step) => (
                <li key={step.kicker}>
                  <p className="eyebrow-sm">{step.kicker}</p>
                  <h3>{step.title}</h3>
                  <p className="body">{step.body}</p>
                </li>
              ))}
            </ol>
            <div className="tile tile-pad ehw-paths" data-reveal="">
              <p className="eyebrow-sm">Two paths</p>
              <div className="ehw-lane ehw-lane-old" style={{ marginTop: 22 }}>
                <div className="ehw-lane-head">
                  <b>The old way</b>
                  <span className="num">90 days</span>
                </div>
                <div className="ehw-bar" aria-hidden="true">
                  <i style={{ "--w": "100%" } as CSSProperties}></i>
                </div>
                <p className="body">Manual calls. Surprises.</p>
              </div>
              <div className="ehw-lane">
                <div className="ehw-lane-head">
                  <b>BrowseJobs</b>
                  <span className="num">3 days</span>
                </div>
                <div className="ehw-bar" aria-hidden="true">
                  <i style={{ "--w": "34%" } as CSSProperties}></i>
                </div>
                <p className="body">WhatsApp bots. A report before you meet.</p>
              </div>
              <p className="fine">A picture of the two paths. Not a chart of your hiring.</p>
              <p className="fine" style={{ marginTop: 8 }}>
                {DISCLAIMER}
              </p>
            </div>
          </div>
        </div>
      </section>

      <SampleReportChapter />

      <section className="s-paper chapter" id="stages">
        <div className="wrap">
          <div className="center ehw-head">
            <p className="eyebrow">The stages</p>
            <h2 className="h-section">The path we run with you.</h2>
            <p className="lead">From the AI interview through joining. A person always releases the offer letter.</p>
          </div>
          <ol className="ehw-stages" data-reveal-kids="">
            {hiringStages.map((stage, index) => (
              <li key={stage.name}>
                <span className="n">{String(index + 1).padStart(2, "0")}</span>
                <div>
                  <h3 className="h-card">{stage.name}</h3>
                  <p className="body">{stage.body}</p>
                </div>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <ApTaurusFilm />

      <section className="s-white chapter" id="get-started">
        <div className="wrap center">
          <h2 className="h-section">Onboard with us.</h2>
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
            <Link className="more" href="/employers/faq">
              Read employer questions <span className="chev" aria-hidden="true">›</span>
            </Link>
          </div>
        </div>
      </section>
    </ApShell>
  );
}

function SampleReportChapter() {
  const r = SAMPLE_REPORT;
  return (
    <section className="s-white chapter" id="report">
      <div className="wrap">
        <div className="center ehw-head">
          <p className="eyebrow">{r.label}</p>
          <h2 className="h-section">What you receive.</h2>
          <p className="lead">A performance report and the CV. The person below is an example. Not a real person.</p>
        </div>
        <div className="ehw-report">
          <article className="tile tile-pad" data-reveal="">
            <header className="ehw-rhead">
              <div>
                <span className="badge">Sample data</span>
                <h3 className="h-tile">{r.name}</h3>
                <p className="body" style={{ marginTop: 4 }}>
                  {r.role}
                </p>
              </div>
              <div className="ehw-score">
                <div className="big num">
                  {r.score}
                  <small>/{r.scoreMax}</small>
                </div>
                <span className="badge">{r.result}</span>
              </div>
            </header>
            <p className="fine">{r.note}</p>
            <p className="body">{r.barNote}</p>

            <div className="ehw-rblock">
              <h4>Skill breakdown</h4>
              <ul className="ehw-skills">
                {r.skills.map((skill) => (
                  <li key={skill.label}>
                    <div className="row">
                      <span>{skill.label}</span>
                      <span className="num">{skill.score}</span>
                    </div>
                    <div className="ehw-bar" aria-hidden="true">
                      <i style={{ "--w": `${skill.score}%` } as CSSProperties}></i>
                    </div>
                  </li>
                ))}
              </ul>
            </div>

            <div className="ehw-rblock">
              <h4>Proctoring summary</h4>
              <ul className="ticks">
                {r.proctoring.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </div>

            <div className="ehw-rblock">
              <h4>Communication notes</h4>
              <p className="body" style={{ marginTop: 0 }}>
                {r.communication}
              </p>
            </div>

            <div className="ehw-rblock ehw-pair">
              <div className="ehw-panel">
                <h4>Strengths</h4>
                <ul>
                  {r.strengths.map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
              </div>
              <div className="ehw-panel">
                <h4>Risks</h4>
                <ul>
                  {r.risks.map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
              </div>
            </div>

            <div className="ehw-rblock">
              <h4>Interview transcript excerpts</h4>
              <ul className="ehw-transcript">
                {r.transcript.map((line) => (
                  <li key={line.who} className={line.who === r.name ? "is-candidate" : undefined}>
                    <p className="who">{line.who}</p>
                    <p className="said">{line.text}</p>
                  </li>
                ))}
              </ul>
            </div>

            <div className="ehw-rblock">
              <h4>BGV status</h4>
              <p className="body" style={{ marginTop: 0 }}>
                {r.bgv}
              </p>
            </div>
          </article>

          <aside className="tile tile-pad ehw-cv" data-reveal="">
            <p className="eyebrow-sm">Mini CV preview · example</p>
            <h3 className="h-card">{r.name}</h3>
            <p className="body" style={{ marginTop: 4, color: "var(--fg)" }}>
              {r.cv.headline}
            </p>
            <p className="body">{r.cv.summary}</p>
            <div className="ehw-rblock">
              <h4>Experience</h4>
              <ul className="roles">
                {r.cv.roles.map((role) => (
                  <li key={role.company}>
                    <b>{role.title}</b>
                    <span>{role.company}</span>
                    <span className="fine" style={{ margin: 0 }}>
                      {role.when}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
            <div className="ehw-rblock">
              <h4>Skills</h4>
              <ul className="ehw-chips">
                {r.cv.skills.map((skill) => (
                  <li key={skill}>{skill}</li>
                ))}
              </ul>
            </div>
            <p className="fine">{r.note}</p>
          </aside>
        </div>
      </div>
    </section>
  );
}

/** Line drawings for the four bots, in the shell's monochrome art language (animate only while in view). */
function BotArt({ id }: { id: BotId }) {
  if (id === "screening") {
    return (
      <svg viewBox="0 0 320 150">
        {[18, 56, 94].map((y, i) => (
          <g key={y} opacity={0.4 + i * 0.3}>
            <rect className="art-bg" x="20" y={y} width="160" height="34" rx="9" />
            <rect className="art-fill" x="34" y={y + 14} width="64" height="6" rx="3" />
            <rect className="art-soft" x="106" y={y + 14} width="54" height="6" rx="3" />
          </g>
        ))}
        <path className="art-soft" d="M180 111 C 205 111, 210 75, 232 75" />
        <circle className="art-fill flow-dot" r="3.5" style={{ offsetPath: "path('M180 111 C 205 111, 210 75, 232 75')" }} />
        <rect className="art-bg" x="232" y="52" width="68" height="46" rx="12" />
        <path className="ehw-check" d="M252 75 l9 9 18-19" />
      </svg>
    );
  }
  if (id === "interview") {
    const bars = [34, 58, 26, 66, 44];
    return (
      <svg viewBox="0 0 320 150">
        {bars.map((h, i) => (
          <rect key={i} className="art-fill bar-rise" x={30 + i * 26} y={124 - h} width="12" height={h} rx="6" />
        ))}
        <line className="art-soft" x1="20" y1="132" x2="170" y2="132" />
        <g>
          <circle className="ehw-ring-track" cx="246" cy="75" r="42" pathLength="100" />
          <circle className="ehw-ring ehw-ring-fill" cx="246" cy="75" r="42" pathLength="100" />
          <text className="ehw-art-num" x="246" y="80" textAnchor="middle">
            75%
          </text>
        </g>
      </svg>
    );
  }
  if (id === "bgv") {
    return (
      <svg viewBox="0 0 320 150">
        <rect className="art-soft" x="24" y="34" width="84" height="84" rx="14" />
        <rect className="art-soft" x="40" y="54" width="52" height="6" rx="3" />
        <rect className="art-soft" x="40" y="70" width="40" height="6" rx="3" />
        <rect className="art-soft" x="40" y="86" width="46" height="6" rx="3" />
        <path className="art-bg" d="M160 14 L214 36 V74 C214 104 190 122 160 132 C130 122 106 104 106 74 V36 Z" />
        <path className="ehw-check" d="M142 74 l12 12 26-28" />
        <rect className="art-soft" x="230" y="44" width="66" height="22" rx="11" />
        <rect className="art-soft" x="230" y="84" width="52" height="22" rx="11" />
      </svg>
    );
  }
  return (
    <svg viewBox="0 0 320 150">
      <rect className="art-bg" x="24" y="14" width="168" height="30" rx="15" />
      <rect className="art-fill type-line" x="40" y="26" width="120" height="6" rx="3" />
      <g className="type-reply">
        <rect className="art-fill" x="128" y="58" width="168" height="30" rx="15" />
        <rect className="ehw-art-on" x="146" y="70" width="104" height="6" rx="3" />
      </g>
      <rect className="art-bg" x="24" y="102" width="140" height="30" rx="15" />
      <rect className="art-fill type-line" x="40" y="114" width="96" height="6" rx="3" />
    </svg>
  );
}
