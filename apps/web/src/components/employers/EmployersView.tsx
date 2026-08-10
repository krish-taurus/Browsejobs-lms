"use client";

/**
 * /employers — the hiring-team landing page. Keynote art direction: light page,
 * huge tracking-tight display type, and seven dark product scenes where each
 * stage of the pipeline demonstrates itself. Every CTA funnels to one form.
 *
 * Copy lives in `content/employers.ts`; motion primitives in `./primitives`.
 */

import { Fragment, useRef, useState } from "react";
import Link from "next/link";
import { motion, useScroll, useTransform } from "framer-motion";
import { Wordmark } from "@/components/brand/Wordmark";
import { Footer } from "@/components/landing/Footer";
import {
  DELIVERY_MODELS,
  EMPLOYER_DISCLAIMER,
  EMPLOYER_FAQ,
  PIPELINE,
  PRICING,
  ROADMAP,
  TAT_AFTER,
  TAT_BEFORE,
  USE_CASES,
} from "@/content/employers";
import { DEMOS } from "./demos";
import { Counter, Kicker, Magnetic, Reveal, Scene, durations, ease, useMotionOk, useSmoothScroll } from "./primitives";
import { EmployerLeadForm } from "./EmployerLeadForm";

/* ----------------------------------- nav ----------------------------------- */

const NAV_LINKS = [
  { href: "#pipeline", label: "How it works" },
  { href: "#use-cases", label: "Use cases" },
  { href: "#models", label: "Models" },
  { href: "#pricing", label: "Pricing" },
] as const;

function EmployerNav() {
  const [open, setOpen] = useState(false);
  const ok = useMotionOk();

  // Lenis swallows native hash jumps, so drive section scrolls explicitly.
  const go = (e: React.MouseEvent, href: string) => {
    e.preventDefault();
    setOpen(false);
    const el = document.querySelector(href);
    if (el) {
      requestAnimationFrame(() => el.scrollIntoView({ behavior: ok ? "smooth" : "auto", block: "start" }));
      history.replaceState(null, "", href);
    }
  };

  return (
    <header className="fixed inset-x-0 top-0 z-50 border-b border-black/[0.06] bg-white/85 backdrop-blur-xl">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3.5 md:px-6">
        <Link href="/" aria-label="BrowseJobs home" onClick={() => setOpen(false)} className="flex items-center gap-2.5">
          <Wordmark />
          <span className="kicker hidden text-trust sm:block" style={{ fontSize: "0.6rem" }}>
            for employers
          </span>
        </Link>

        <nav className="hidden items-center gap-7 md:flex">
          {NAV_LINKS.map((l) => (
            <a
              key={l.href}
              href={l.href}
              onClick={(e) => go(e, l.href)}
              className="text-sm font-medium text-muted transition-colors hover:text-ink"
            >
              {l.label}
            </a>
          ))}
          <Link href="/employer" className="text-sm font-medium text-muted transition-colors hover:text-ink">
            Employer sign in
          </Link>
          <a
            href="#start"
            onClick={(e) => go(e, "#start")}
            className="rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-deep"
          >
            Book a walkthrough
          </a>
        </nav>

        <button
          aria-label={open ? "Close menu" : "Open menu"}
          aria-expanded={open}
          onClick={() => setOpen((o) => !o)}
          className="relative z-50 flex h-10 w-10 items-center justify-center rounded-full border border-line bg-white md:hidden"
        >
          <span className="relative block h-3 w-4">
            <span
              className="absolute left-0 block h-[2px] w-full rounded-full bg-ink transition-transform"
              style={{ top: 0, transform: open ? "translateY(5px) rotate(45deg)" : "none" }}
            />
            <span
              className="absolute left-0 top-1/2 block h-[2px] w-full -translate-y-1/2 rounded-full bg-ink transition-opacity"
              style={{ opacity: open ? 0 : 1 }}
            />
            <span
              className="absolute bottom-0 left-0 block h-[2px] w-full rounded-full bg-ink transition-transform"
              style={{ transform: open ? "translateY(-5px) rotate(-45deg)" : "none" }}
            />
          </span>
        </button>
      </div>

      {open && (
        <nav className="border-t border-black/[0.06] bg-white md:hidden">
          <div className="space-y-1 px-5 py-4">
            {NAV_LINKS.map((l) => (
              <a
                key={l.href}
                href={l.href}
                onClick={(e) => go(e, l.href)}
                className="block rounded-input px-4 py-3 text-base font-semibold text-ink hover:bg-paper"
              >
                {l.label}
              </a>
            ))}
            <Link
              href="/employer"
              onClick={() => setOpen(false)}
              className="block rounded-input px-4 py-3 text-base font-semibold text-muted hover:bg-paper"
            >
              Employer sign in
            </Link>
            <a
              href="#start"
              onClick={(e) => go(e, "#start")}
              className="mt-2 block rounded-full bg-trust px-5 py-3 text-center text-base font-bold text-white"
            >
              Book a walkthrough
            </a>
          </div>
        </nav>
      )}
    </header>
  );
}

/* ---------------------------------- hero ----------------------------------- */

function Hero() {
  const ref = useRef<HTMLElement>(null);
  const ok = useMotionOk();
  const { scrollYProgress } = useScroll({ target: ref, offset: ["start start", "end start"] });
  const fade = useTransform(scrollYProgress, [0, 0.7], [1, 0]);
  const lift = useTransform(scrollYProgress, [0, 1], [0, 60]);

  const line1 = ["Interview", "the", "shortlist."];

  return (
    <section ref={ref} className="relative overflow-clip px-6 pb-24 pt-36 text-center md:pb-32 md:pt-44">
      <div
        aria-hidden
        className="pointer-events-none absolute left-1/2 top-0 h-[520px] w-[820px] -translate-x-1/2 rounded-full bg-trust/10 blur-[150px]"
      />
      <motion.div style={ok ? { opacity: fade, y: lift } : undefined} className="relative">
        <Reveal>
          <Kicker color="var(--bj-trust)">BrowseJobs for employers</Kicker>
        </Reveal>

        <h1 className="display mx-auto mt-6 max-w-4xl text-[12vw] leading-[0.98] md:text-[6.5rem]">
          {/* Real spaces between the masked words so copy/paste and screen
              readers get "Interview the shortlist.", not one run-on token. */}
          {line1.map((w, i) => (
            <Fragment key={w}>
              <span className="inline-block overflow-hidden align-bottom">
                <motion.span
                  initial={ok ? { y: "105%" } : false}
                  animate={{ y: "0%" }}
                  transition={{ delay: 0.1 + i * 0.08, duration: durations.slower, ease }}
                  className="inline-block"
                >
                  {w}
                </motion.span>
              </span>{" "}
            </Fragment>
          ))}
          <br />
          <span className="inline-block overflow-hidden align-bottom">
            <motion.span
              initial={ok ? { y: "105%" } : false}
              animate={{ y: "0%" }}
              transition={{ delay: 0.36, duration: durations.slower, ease }}
              className="inline-block bg-gradient-to-r from-trust via-[#4d94ff] to-[#7c3aed] bg-clip-text text-transparent"
            >
              Not the inbox.
            </motion.span>
          </span>
        </h1>

        <motion.p
          initial={ok ? { opacity: 0, y: 18 } : false}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.6, duration: durations.slow, ease }}
          className="mx-auto mt-8 max-w-xl text-lg leading-relaxed text-muted md:text-xl"
        >
          Load a JD. The AI structures it, ranks candidates, places the screening call, runs L1 and L2, and hands your
          team a written brief on every finalist —{" "}
          <strong className="font-semibold text-ink">before your first interview is booked.</strong>
        </motion.p>

        <motion.div
          initial={ok ? { opacity: 0, y: 18 } : false}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.74, duration: durations.slow, ease }}
          className="mt-10 flex flex-wrap items-center justify-center gap-5"
        >
          <Magnetic>
            <a
              href="#start"
              className="inline-block rounded-full bg-trust px-9 py-4 text-lg font-semibold text-white shadow-[0_12px_40px_rgba(27,109,240,0.32)] transition-all hover:scale-[1.02] hover:bg-deep"
            >
              Start the 6 free months
            </a>
          </Magnetic>
          <a href="#pipeline" className="group text-lg font-semibold text-trust">
            See it run{" "}
            <span className="inline-block transition-transform group-hover:translate-x-1" aria-hidden>
              →
            </span>
          </a>
        </motion.div>

        <motion.p
          initial={ok ? { opacity: 0 } : false}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.95, duration: durations.slow }}
          className="mono mt-6 text-sm text-muted"
        >
          Free for 6 months · No card · Works on your ATS or ours
        </motion.p>
      </motion.div>
    </section>
  );
}

/* --------------------------- before / after the TAT ------------------------- */

function TurnaroundSection() {
  return (
    <section className="mx-auto max-w-5xl px-6 pb-24 md:pb-32">
      <Reveal>
        <h2 className="display mx-auto max-w-3xl text-center text-3xl leading-[1.1] md:text-5xl">
          The work that eats your hiring time
          <br />
          <span className="text-muted">happens before anyone is interviewed.</span>
        </h2>
      </Reveal>

      <div className="mt-14 grid gap-4 md:grid-cols-2 md:gap-6">
        <Reveal className="rounded-panel border border-line bg-white p-6 md:p-8">
          <Kicker color="var(--bj-muted)">Today</Kicker>
          <ul className="mt-5 space-y-3.5">
            {TAT_BEFORE.map((t) => (
              <li key={t} className="flex items-start gap-3 text-[15px] leading-relaxed text-muted">
                <span aria-hidden className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-line" />
                {t}
              </li>
            ))}
          </ul>
        </Reveal>

        <Reveal delay={0.08} className="rounded-panel border border-trust/25 bg-sky/40 p-6 md:p-8">
          <Kicker color="var(--bj-trust)">With BrowseJobs</Kicker>
          <ul className="mt-5 space-y-3.5">
            {TAT_AFTER.map((t) => (
              <li key={t} className="flex items-start gap-3 text-[15px] font-medium leading-relaxed text-ink">
                <svg viewBox="0 0 24 24" fill="none" className="mt-0.5 h-4.5 w-4.5 shrink-0 text-trust">
                  <path
                    d="M5 12.5l4.5 4.5L19 7.5"
                    stroke="currentColor"
                    strokeWidth="2.4"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
                {t}
              </li>
            ))}
          </ul>
        </Reveal>
      </div>

      <Reveal delay={0.14}>
        <p className="mono mx-auto mt-8 max-w-2xl text-center text-xs leading-relaxed text-muted">
          Your panel&rsquo;s first hour is spent on a candidate who has already been ranked, screened, interviewed and
          background-checked.
        </p>
      </Reveal>
    </section>
  );
}

/* --------------------------- pipeline overview strip ------------------------ */

function PipelineOverview() {
  return (
    <section id="pipeline" className="border-y border-line bg-paper px-6 py-20 md:py-24">
      <div className="mx-auto max-w-6xl">
        <Reveal>
          <Kicker color="var(--bj-trust)">The pipeline</Kicker>
          <h2 className="display mt-4 max-w-2xl text-3xl leading-[1.1] md:text-5xl">Seven stages. One handover.</h2>
        </Reveal>

        <div className="mt-12 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {PIPELINE.map((s, i) => (
            <Reveal
              key={s.id}
              delay={i * 0.05}
              className="group relative overflow-hidden rounded-card border border-line bg-white p-5 transition-transform hover:-translate-y-1"
            >
              <a href={`#stage-${s.id}`} className="block">
                <span
                  aria-hidden
                  className="mono absolute -right-1 -top-2 text-5xl font-bold opacity-[0.07]"
                  style={{ color: s.accent }}
                >
                  {s.step}
                </span>
                <span
                  className="mono inline-grid h-7 w-7 place-items-center rounded-full text-[11px] font-semibold"
                  style={{ background: `${s.accent}18`, color: s.accent }}
                >
                  {s.step}
                </span>
                <p className="mt-3 text-[15px] font-semibold text-ink">{s.kicker}</p>
                <p className="mt-1.5 text-[13px] leading-snug text-muted">{s.title}</p>
                <span
                  className="mt-4 block h-1 rounded-full transition-all group-hover:w-16"
                  style={{ background: s.accent, width: "2rem" }}
                />
              </a>
            </Reveal>
          ))}

          <Reveal delay={PIPELINE.length * 0.05} className="rounded-card border border-trust bg-trust p-5 text-white">
            <p className="display text-3xl">
              <Counter to={7} />
            </p>
            <p className="mt-2 text-[13px] leading-snug text-white/80">
              stages run before your team spends an hour on a candidate.
            </p>
          </Reveal>
        </div>
      </div>
    </section>
  );
}

/* -------------------------------- use cases -------------------------------- */

function UseCasesSection() {
  return (
    <section id="use-cases" className="mx-auto max-w-6xl px-6 py-24 md:py-32">
      <Reveal>
        <Kicker color="var(--bj-trust)">Use cases</Kicker>
        <h2 className="display mt-4 max-w-2xl text-3xl leading-[1.1] md:text-5xl">
          Three ways teams put it to work.
        </h2>
      </Reveal>

      <div className="mt-14 grid gap-5 lg:grid-cols-3">
        {USE_CASES.map((u, i) => (
          <Reveal
            key={u.title}
            delay={i * 0.08}
            className="flex flex-col rounded-panel border border-line bg-white p-6 transition-shadow hover:shadow-soft md:p-7"
          >
            <span className="inline-block self-start rounded-full px-3 py-1 text-xs font-semibold" style={{ background: `${u.accent}16`, color: u.accent }}>
              {u.title}
            </span>
            <p className="mt-4 text-[15px] leading-relaxed text-ink">{u.scenario}</p>

            <ol className="mt-5 space-y-2.5 border-l-2 pl-4" style={{ borderColor: `${u.accent}33` }}>
              {u.flow.map((f, fi) => (
                <li key={f} className="relative text-[13.5px] leading-snug text-muted">
                  <span
                    aria-hidden
                    className="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full"
                    style={{ background: u.accent }}
                  />
                  <span className="mono mr-1.5 text-[11px]" style={{ color: u.accent }}>
                    {String(fi + 1).padStart(2, "0")}
                  </span>
                  {f}
                </li>
              ))}
            </ol>

            <p className="mt-auto pt-5 text-[13.5px] font-medium leading-relaxed text-ink">{u.outcome}</p>
          </Reveal>
        ))}
      </div>

      <Reveal delay={0.1}>
        <p className="mono mt-8 text-xs leading-relaxed text-muted">{EMPLOYER_DISCLAIMER}</p>
      </Reveal>
    </section>
  );
}

/* ----------------------------- delivery models ----------------------------- */

function ModelsSection() {
  return (
    <section id="models" className="border-y border-line bg-paper px-6 py-24 md:py-32">
      <div className="mx-auto max-w-6xl">
        <Reveal>
          <Kicker color="var(--bj-trust)">Your data or ours</Kicker>
          <h2 className="display mt-4 max-w-2xl text-3xl leading-[1.1] md:text-5xl">
            Two ways to run it. Same pipeline.
          </h2>
        </Reveal>

        <div className="mt-14 grid gap-5 md:grid-cols-2">
          {DELIVERY_MODELS.map((m, i) => (
            <Reveal key={m.id} delay={i * 0.08} className="rounded-panel border border-line bg-white p-7 md:p-9">
              <span
                className="mono inline-block rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em]"
                style={{ background: `${m.accent}16`, color: m.accent }}
              >
                {m.label}
              </span>
              <h3 className="display mt-5 text-2xl md:text-3xl">{m.title}</h3>
              <p className="mt-3 text-[15px] leading-relaxed text-muted">{m.body}</p>
              <ul className="mt-6 space-y-3">
                {m.points.map((p) => (
                  <li key={p} className="flex items-start gap-3 text-sm leading-relaxed text-ink">
                    <svg viewBox="0 0 24 24" fill="none" className="mt-0.5 h-4 w-4 shrink-0" style={{ color: m.accent }}>
                      <path
                        d="M5 12.5l4.5 4.5L19 7.5"
                        stroke="currentColor"
                        strokeWidth="2.4"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      />
                    </svg>
                    {p}
                  </li>
                ))}
              </ul>
            </Reveal>
          ))}
        </div>
      </div>
    </section>
  );
}

/* --------------------------------- roadmap --------------------------------- */

/**
 * Deliberately styled as a dashed, muted band — nothing here ships today, and
 * the section has to read as "not yet" at a glance, not as another feature list.
 */
function RoadmapSection() {
  return (
    <section className="mx-auto max-w-6xl px-6 pb-24 md:pb-32">
      <Reveal>
        <Kicker color="var(--bj-muted)">On the roadmap — not available yet</Kicker>
        <h2 className="display mt-4 max-w-2xl text-2xl leading-[1.15] text-muted md:text-3xl">
          Being built. We&rsquo;ll tell you where it stands on the call.
        </h2>
      </Reveal>

      <div className="mt-10 grid gap-4 md:grid-cols-3">
        {ROADMAP.map((r, i) => (
          <Reveal
            key={r.title}
            delay={i * 0.06}
            className="rounded-card border border-dashed border-line bg-paper/60 p-6"
          >
            <span className="mono inline-block rounded-full border border-line px-2.5 py-0.5 text-[10px] uppercase tracking-[0.14em] text-muted">
              Planned
            </span>
            <h3 className="mt-4 text-[15px] font-semibold text-ink/70">{r.title}</h3>
            <p className="mt-2 text-[13.5px] leading-relaxed text-muted">{r.body}</p>
          </Reveal>
        ))}
      </div>
    </section>
  );
}

/* --------------------------------- pricing --------------------------------- */

function PricingSection() {
  return (
    <section id="pricing" className="mx-auto max-w-6xl px-6 py-24 md:py-32">
      <Reveal>
        <Kicker color="var(--bj-verify)">Pricing</Kicker>
        <h2 className="display mt-4 max-w-2xl text-3xl leading-[1.1] md:text-5xl">
          Six months free. Then you pick.
        </h2>
      </Reveal>

      <div className="mt-14 grid gap-5 lg:grid-cols-3">
        {/* Free period — green, because it is genuinely free (semantic colour rule). */}
        <Reveal className="rounded-panel border-2 border-verify bg-verify-bg p-7 md:p-8">
          <Kicker color="var(--bj-verify)">{PRICING.free.label}</Kicker>
          <p className="display mt-4 text-5xl text-verify">{PRICING.free.price}</p>
          <p className="mt-4 text-[15px] leading-relaxed text-ink">{PRICING.free.body}</p>
          <ul className="mt-6 space-y-2.5">
            {PRICING.free.points.map((p) => (
              <li key={p} className="flex items-start gap-2.5 text-sm text-ink">
                <svg viewBox="0 0 24 24" fill="none" className="mt-0.5 h-4 w-4 shrink-0 text-verify">
                  <path
                    d="M5 12.5l4.5 4.5L19 7.5"
                    stroke="currentColor"
                    strokeWidth="2.4"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
                {p}
              </li>
            ))}
          </ul>
        </Reveal>

        {PRICING.paid.options.map((o, i) => (
          <Reveal key={o.title} delay={0.08 + i * 0.08} className="flex flex-col rounded-panel border border-line bg-white p-7 md:p-8">
            <Kicker color="var(--bj-muted)">{PRICING.paid.label}</Kicker>
            <h3 className="display mt-4 text-2xl">{o.title}</h3>
            <p className="display mono mt-3 text-3xl" style={{ color: o.accent }}>
              {o.headline}
            </p>
            <p className="mt-4 text-[15px] leading-relaxed text-muted">{o.body}</p>
            <p className="mono mt-auto pt-6 text-[11.5px] leading-relaxed text-muted">{o.note}</p>
          </Reveal>
        ))}
      </div>

      <Reveal delay={0.16}>
        <p className="mt-6 text-center text-[15px] leading-relaxed text-muted">{PRICING.paid.body}</p>
      </Reveal>

      <Reveal delay={0.2} className="mt-8 rounded-panel border border-line bg-paper p-7 md:p-8">
        <h3 className="display text-xl md:text-2xl">{PRICING.crm.title}</h3>
        <p className="mt-3 max-w-3xl text-[15px] leading-relaxed text-muted">{PRICING.crm.body}</p>
      </Reveal>
    </section>
  );
}

/* ----------------------------------- FAQ ----------------------------------- */

function FaqSection() {
  const [open, setOpen] = useState<number | null>(0);
  return (
    <section className="border-t border-line bg-paper px-6 py-24 md:py-32">
      <div className="mx-auto max-w-3xl">
        <Reveal>
          <Kicker color="var(--bj-trust)">Questions</Kicker>
          <h2 className="display mt-4 text-3xl leading-[1.1] md:text-5xl">Before you ask.</h2>
        </Reveal>

        <div className="mt-12 divide-y divide-line border-y border-line">
          {EMPLOYER_FAQ.map((f, i) => (
            <div key={f.q}>
              <button
                onClick={() => setOpen(open === i ? null : i)}
                aria-expanded={open === i}
                className="flex w-full items-start justify-between gap-6 py-5 text-left"
              >
                <span className="text-[16px] font-semibold text-ink">{f.q}</span>
                <span
                  aria-hidden
                  className="mt-1 shrink-0 text-xl leading-none text-trust transition-transform"
                  style={{ transform: open === i ? "rotate(45deg)" : "none" }}
                >
                  +
                </span>
              </button>
              {open === i && <p className="-mt-1 pb-5 pr-10 text-[15px] leading-relaxed text-muted">{f.a}</p>}
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

/* ---------------------------------- the CTA -------------------------------- */

function StartSection() {
  return (
    <section id="start" className="relative overflow-clip bg-ink px-6 py-24 text-white md:py-32">
      <div
        aria-hidden
        className="pointer-events-none absolute left-1/2 top-0 h-[420px] w-[720px] -translate-x-1/2 rounded-full bg-trust/25 blur-[150px]"
      />
      <div className="relative mx-auto grid max-w-6xl gap-12 lg:grid-cols-2 lg:gap-16">
        <div>
          <Reveal>
            <Kicker color="var(--bj-verify)">Free for 6 months</Kicker>
          </Reveal>
          <Reveal delay={0.06}>
            <h2 className="display mt-5 text-3xl leading-[1.06] md:text-5xl">
              Bring one open role.
              <br />
              <span className="text-white/45">We&rsquo;ll run it end to end.</span>
            </h2>
          </Reveal>
          <Reveal delay={0.12}>
            <p className="mt-6 max-w-md text-base leading-relaxed text-white/60 md:text-lg">
              Tell us what you&rsquo;re hiring for. We&rsquo;ll set up the onboarding call, run your JD through the
              pipeline, and show you the candidate brief your HR team would have received.
            </p>
          </Reveal>

          <Reveal delay={0.18}>
            <ul className="mt-8 space-y-3">
              {[
                "Onboarding call within two working days",
                "Your first role live in the pipeline",
                "Commercials in writing before anything is charged",
              ].map((p) => (
                <li key={p} className="flex items-start gap-3 text-sm text-white/70">
                  <svg viewBox="0 0 24 24" fill="none" className="mt-0.5 h-4 w-4 shrink-0 text-verify">
                    <path
                      d="M5 12.5l4.5 4.5L19 7.5"
                      stroke="currentColor"
                      strokeWidth="2.4"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    />
                  </svg>
                  {p}
                </li>
              ))}
            </ul>
          </Reveal>
        </div>

        <Reveal delay={0.1}>
          <EmployerLeadForm />
        </Reveal>
      </div>
    </section>
  );
}

/* ---------------------------------- page ----------------------------------- */

export function EmployersView() {
  useSmoothScroll();

  return (
    <main className="bg-white font-body text-ink antialiased selection:bg-trust selection:text-white">
      <EmployerNav />
      <Hero />
      <TurnaroundSection />
      <PipelineOverview />

      {PIPELINE.map((stage, i) => {
        const Demo = DEMOS[stage.demo];
        return (
          <Scene
            key={stage.id}
            id={`stage-${stage.id}`}
            step={stage.step}
            kicker={stage.kicker}
            title={stage.title}
            body={stage.body}
            points={stage.points}
            accent={stage.accent}
            flip={i % 2 === 1}
            demo={<Demo accent={stage.accent} />}
          />
        );
      })}

      <UseCasesSection />
      <ModelsSection />
      <RoadmapSection />
      <PricingSection />
      <FaqSection />
      <StartSection />
      <Footer />
    </main>
  );
}
