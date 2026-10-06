"use client";

import { motion, useReducedMotion } from "framer-motion";
import { SAMPLE_REPORT } from "@/content/employer-landing";
import { durations, ease, stagger } from "@/lib/motion";

export function SampleReport() {
  const reduce = useReducedMotion();
  const report = SAMPLE_REPORT;

  return (
    <div className="mt-10 grid items-start gap-5 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,0.75fr)]">
      <article className="overflow-hidden rounded-[22px] border border-line bg-white shadow-soft">
        <header className="flex flex-wrap items-start justify-between gap-4 border-b border-line bg-paper px-5 py-5 md:px-7">
          <div>
            <p className="kicker text-trust">{report.label}</p>
            <h3 className="display mt-2 text-2xl text-ink md:text-3xl">{report.name}</h3>
            <p className="mt-1 text-sm text-muted">{report.role}</p>
          </div>
          <div className="text-right">
            <p className="mono text-4xl text-ink">
              {report.score}
              <span className="text-lg text-muted">/{report.scoreMax}</span>
            </p>
            <p className="mono mt-1 text-sm font-semibold text-verify">{report.result}</p>
          </div>
        </header>

        <div className="space-y-7 px-5 py-6 md:px-7">
          <p className="mono text-[11px] leading-relaxed text-muted">{report.note}</p>
          <p className="text-sm text-ink2">{report.barNote}</p>

          <div>
            <h4 className="kicker text-trust">Skill breakdown</h4>
            <ul className="mt-4 space-y-3">
              {report.skills.map((skill, index) => (
                <li key={skill.label}>
                  <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
                    <span className="text-ink">{skill.label}</span>
                    <span className="mono text-ink2">{skill.score}</span>
                  </div>
                  <motion.div
                    className="h-1.5 overflow-hidden rounded-full bg-paper"
                    initial="hidden"
                    whileInView="show"
                    viewport={{ once: true }}
                  >
                    <motion.div
                      className="h-full origin-left rounded-full bg-trust"
                      style={{ width: `${skill.score}%` }}
                      variants={{
                        hidden: { scaleX: reduce ? 1 : 0 },
                        show: {
                          scaleX: 1,
                          transition: {
                            duration: reduce ? 0 : durations.slower,
                            ease,
                            delay: reduce ? 0 : index * stagger,
                          },
                        },
                      }}
                    />
                  </motion.div>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h4 className="kicker text-trust">Proctoring summary</h4>
            <ul className="mt-3 grid gap-2 sm:grid-cols-2">
              {report.proctoring.map((item) => (
                <li key={item} className="flex items-center gap-2 rounded-[10px] bg-verify-bg px-3 py-2 text-sm text-ink">
                  <span aria-hidden className="mono text-verify">
                    ✓
                  </span>
                  {item}
                </li>
              ))}
            </ul>
          </div>

          <div className="border-l-2 border-amber pl-3">
            <h4 className="kicker text-amber">Communication notes</h4>
            <p className="mt-2 text-sm leading-relaxed text-ink2">{report.communication}</p>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="rounded-[14px] border border-verify/30 bg-verify-bg p-4">
              <h4 className="kicker text-verify">Strengths</h4>
              <ul className="mt-2 space-y-2 text-sm leading-relaxed text-ink">
                {report.strengths.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </div>
            <div className="rounded-[14px] border border-line p-4">
              <h4 className="kicker text-ink2">Risks</h4>
              <ul className="mt-2 space-y-2 text-sm leading-relaxed text-ink2">
                {report.risks.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            </div>
          </div>

          <div>
            <h4 className="kicker text-trust">Interview transcript excerpts</h4>
            <ul className="mt-3 space-y-3">
              {report.transcript.map((line) => (
                <li key={line.who} className="rounded-[14px] bg-paper px-4 py-3">
                  <p className="mono text-[10px] uppercase tracking-[0.14em] text-muted">{line.who}</p>
                  <p className="mt-1 text-sm leading-relaxed text-ink">{line.text}</p>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h4 className="kicker text-trust">BGV status</h4>
            <p className="mt-2 text-sm leading-relaxed text-ink2">{report.bgv}</p>
          </div>
        </div>
      </article>

      <aside className="rounded-[22px] border border-line bg-white p-5 shadow-soft md:p-6">
        <p className="kicker text-muted">Mini CV preview · example</p>
        <h3 className="display mt-3 text-2xl text-ink">{report.name}</h3>
        <p className="mono mt-1 text-sm text-trust">{report.cv.headline}</p>
        <p className="mt-4 text-sm leading-relaxed text-ink2">{report.cv.summary}</p>
        <h4 className="kicker mt-6 text-muted">Experience</h4>
        <ul className="mt-3 space-y-4">
          {report.cv.roles.map((role) => (
            <li key={role.company}>
              <p className="font-semibold text-ink">{role.title}</p>
              <p className="text-sm text-ink2">{role.company}</p>
              <p className="mono mt-1 text-[11px] text-muted">{role.when}</p>
            </li>
          ))}
        </ul>
        <h4 className="kicker mt-6 text-muted">Skills</h4>
        <ul className="mt-3 flex flex-wrap gap-2">
          {report.cv.skills.map((skill) => (
            <li key={skill} className="rounded-full border border-line px-3 py-1 text-sm text-ink">
              {skill}
            </li>
          ))}
        </ul>
        <p className="mono mt-6 text-[11px] leading-relaxed text-muted">{report.note}</p>
      </aside>
    </div>
  );
}
