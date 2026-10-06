"use client";

/**
 * /employers — cinematic enterprise-AI landing page for the employer side
 * (AI interviews, Jobs, Pipeline, Team, Dashboard). Self-contained page, styled
 * with a local dark/electric-blue palette (scoped CSS vars below) distinct
 * from the public site's own light --bj-* tokens, matching the brief's
 * "premium investor-ready SaaS" direction. Reuses the shared Wordmark and
 * the project's own icon set; everything else is built here.
 *
 * Every number in the product panels below is illustrative UI-mockup data
 * (the same convention as a Dribbble app screenshot), never presented as a
 * live or historical performance claim — no fabricated stats.
 */

import { useId, useState } from "react";
import Link from "next/link";
import { MotionConfig, motion } from "framer-motion";
import { Wordmark } from "@/components/brand/Wordmark";
import {
  RobotIcon,
  PipelineIcon,
  UsersIcon,
  DocumentIcon,
  TrendUpIcon,
  DocumentCheckIcon,
} from "@/components/employer/icons";

const EASE = [0.16, 1, 0.3, 1] as const;

/* ------------------------------- palette ---------------------------------
   Scoped as CSS custom properties on the page root so the whole page can
   reference var(--eb-*) via Tailwind arbitrary values without colliding
   with the public site's --bj-* token system.
----------------------------------------------------------------------- */
const PALETTE = {
  "--eb-ink": "#090C15",
  "--eb-navy": "#071329",
  "--eb-navy2": "#29334C",
  "--eb-blue": "#2472EF",
  "--eb-blue2": "#5A95F5",
  "--eb-paper": "#F6F8FD",
  "--eb-white": "#FFFFFF",
  "--eb-border": "rgba(148, 177, 226, 0.22)",
} as React.CSSProperties;

/* --------------------------------- shared --------------------------------- */

function Reveal({
  children,
  delay = 0,
  className = "",
}: {
  children: React.ReactNode;
  delay?: number;
  className?: string;
}) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 22 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-10%" }}
      transition={{ duration: 0.65, ease: EASE, delay }}
      className={className}
    >
      {children}
    </motion.div>
  );
}

function Eyebrow({ children, tone = "blue" }: { children: React.ReactNode; tone?: "blue" | "muted" }) {
  return (
    <p
      className={`font-mono text-[11px] font-semibold uppercase tracking-[0.3em] ${
        tone === "blue" ? "text-[var(--eb-blue2)]" : "text-[var(--eb-navy2)]"
      }`}
    >
      {children}
    </p>
  );
}

function initialsAvatar(name: string, i: number) {
  const tints = ["#2472EF", "#5A95F5", "#29334C", "#1b6df0", "#4d94ff"];
  const initials = name
    .split(" ")
    .map((p) => p[0])
    .join("")
    .slice(0, 2);
  return (
    <span
      className="grid size-8 shrink-0 place-items-center rounded-full text-[11px] font-bold text-white"
      style={{ background: tints[i % tints.length] }}
    >
      {initials}
    </span>
  );
}

function ScoreRing({ value = 87, size = 84, light = false }: { value?: number; size?: number; light?: boolean }) {
  const gradId = useId();
  const stroke = size < 60 ? 5 : 7;
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const offset = c - (value / 100) * c;
  return (
    <div className="relative shrink-0" style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90">
        <circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          stroke={light ? "rgba(9,12,21,0.08)" : "rgba(255,255,255,0.12)"}
          strokeWidth={stroke}
          fill="none"
        />
        <motion.circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          stroke={`url(#${gradId})`}
          strokeWidth={stroke}
          fill="none"
          strokeLinecap="round"
          strokeDasharray={c}
          initial={{ strokeDashoffset: c }}
          whileInView={{ strokeDashoffset: offset }}
          viewport={{ once: true }}
          transition={{ duration: 1.1, ease: EASE, delay: 0.2 }}
        />
        <defs>
          <linearGradient id={gradId} x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stopColor="#5A95F5" />
            <stop offset="100%" stopColor="#2472EF" />
          </linearGradient>
        </defs>
      </svg>
      <div className="absolute inset-0 grid place-items-center">
        <span className={`font-display font-bold ${size < 60 ? "text-xs" : "text-lg"} ${light ? "text-[var(--eb-ink)]" : "text-white"}`}>
          {value}
        </span>
      </div>
    </div>
  );
}

function HeroLandscape() {
  return (
    <>
      <div
        aria-hidden
        className="absolute inset-0 h-full w-full bg-[#040812]"
        style={{
          backgroundImage: "url(/employers/hero-horizon.webp)",
          backgroundSize: "cover",
          backgroundPosition: "center 68%",
        }}
      />
      {/* scrim so hero text and the product cockpit stay legible over the photo */}
      <div
        aria-hidden
        className="absolute inset-0"
        style={{
          background:
            "linear-gradient(180deg, rgba(4,8,18,.86) 0%, rgba(4,8,18,.55) 32%, rgba(4,8,18,.4) 58%, rgba(4,8,18,.88) 100%), linear-gradient(90deg, rgba(4,8,18,.7) 0%, rgba(4,8,18,.15) 45%, rgba(4,8,18,.55) 100%)",
        }}
      />
    </>
  );
}

/* ---------------------------------- nav ----------------------------------- */

const NAV_LINKS = [
  { label: "Product", href: "#workspace" },
  { label: "Solutions", href: "#how-it-works" },
  { label: "Resources", href: "#proof" },
  { label: "Pricing", href: "#cta" },
] as const;

function Nav() {
  const [open, setOpen] = useState(false);
  return (
    <header className="fixed inset-x-0 top-0 z-50 border-b border-[var(--eb-border)] bg-[var(--eb-ink)]/75 backdrop-blur-xl">
      <div className="mx-auto flex max-w-[1320px] items-center justify-between gap-4 px-5 py-4 md:px-8">
        <Link href="/" aria-label="BrowseJobs home">
          <Wordmark tone="dark" />
        </Link>

        <nav className="hidden items-center gap-8 lg:flex">
          {NAV_LINKS.map((l) => (
            <a key={l.label} href={l.href} className="text-sm font-medium text-white/65 transition-colors hover:text-white">
              {l.label}
            </a>
          ))}
        </nav>

        <div className="hidden items-center gap-5 lg:flex">
          <Link href="/employer" className="text-sm font-medium text-white/65 transition-colors hover:text-white">
            Employer sign in
          </Link>
          <a
            href="mailto:hello@browsejobs.ai?subject=Hiring%20on%20BrowseJobs"
            className="group inline-flex items-center gap-1.5 rounded-full bg-[var(--eb-blue)] px-5 py-2.5 text-sm font-semibold text-white shadow-[0_0_0_1px_rgba(90,149,245,0.3),0_10px_30px_-6px_rgba(36,114,239,0.65)] transition hover:bg-[var(--eb-blue2)] hover:shadow-[0_0_0_1px_rgba(90,149,245,0.5),0_14px_36px_-6px_rgba(36,114,239,0.8)]"
          >
            Talk to our team
            <span className="transition-transform group-hover:translate-x-0.5">→</span>
          </a>
        </div>

        <button
          type="button"
          aria-label="Toggle menu"
          aria-expanded={open}
          aria-controls="mobile-nav"
          onClick={() => setOpen((v) => !v)}
          className="grid size-9 place-items-center rounded-full border border-[var(--eb-border)] text-white lg:hidden"
        >
          <svg viewBox="0 0 20 20" className="size-4" fill="none">
            {open ? (
              <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
            ) : (
              <path d="M3 6h14M3 10h14M3 14h14" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
            )}
          </svg>
        </button>
      </div>

      {open && (
        <div id="mobile-nav" className="border-t border-[var(--eb-border)] bg-[var(--eb-ink)] px-5 py-5 lg:hidden">
          <div className="flex flex-col gap-4">
            {NAV_LINKS.map((l) => (
              <a key={l.label} href={l.href} onClick={() => setOpen(false)} className="text-sm font-medium text-white/75">
                {l.label}
              </a>
            ))}
            <Link href="/employer" onClick={() => setOpen(false)} className="text-sm font-medium text-white/75">
              Employer sign in
            </Link>
            <a
              href="mailto:hello@browsejobs.ai?subject=Hiring%20on%20BrowseJobs"
              className="mt-1 inline-flex items-center justify-center gap-1.5 rounded-full bg-[var(--eb-blue)] px-5 py-2.5 text-sm font-semibold text-white"
            >
              Talk to our team →
            </a>
          </div>
        </div>
      )}
    </header>
  );
}

/* --------------------------------- hero ------------------------------------ */

function CockpitChatPanel() {
  return (
    <motion.div
      initial={{ opacity: 0, y: 16, rotate: -2 }}
      animate={{ opacity: 1, y: 0, rotate: -2 }}
      transition={{ duration: 0.8, ease: EASE, delay: 0.15 }}
      className="absolute left-0 top-10 z-20 w-[410px] -rotate-1 rounded-[24px] border border-[#5A95F566] bg-[#071329]/95 p-5 shadow-[0_35px_90px_-22px_rgba(0,0,0,0.85),0_0_38px_rgba(36,114,239,0.2)] backdrop-blur-xl"
    >
      <div className="flex items-center gap-2">
        <span className="grid size-6 place-items-center rounded-full bg-gradient-to-br from-[var(--eb-blue2)] to-[var(--eb-blue)] text-white">
          <RobotIcon className="size-3" />
        </span>
        <span className="text-xs font-semibold text-white">AI interview</span>
        <span className="ml-auto flex items-center gap-1 text-[10px] font-medium text-[var(--eb-blue2)]">
          <span className="size-1.5 animate-pulse rounded-full bg-[var(--eb-blue2)]" /> interview in progress
        </span>
      </div>
      <div className="mt-3 rounded-xl rounded-tl-sm bg-white/[0.06] p-2.5 text-[11px] leading-snug text-white/85">
        Can you walk me through a time you handled a complex technical trade-off?
        <div className="mt-1 text-[9px] text-white/35">10:34 AM</div>
      </div>
      <div className="mt-2 ml-auto max-w-[85%] rounded-xl rounded-tr-sm bg-[var(--eb-blue)] p-2.5 text-[11px] leading-snug text-white">
        Sure — in my last role, we had to balance performance with cost. I led a spike…
        <div className="mt-1 text-[9px] text-white/60">10:35 AM</div>
      </div>
      <div className="mt-3 flex items-center gap-2 rounded-full bg-white/[0.05] px-3 py-2">
        <div className="flex h-3.5 items-end gap-[2px]">
          {[3, 8, 5, 12, 6, 9, 4].map((h, i) => (
            <span key={i} className="w-[2px] rounded-full bg-[var(--eb-blue2)]" style={{ height: h }} />
          ))}
        </div>
        <span className="text-[10px] text-white/45">AI is listening…</span>
      </div>
      <div className="mt-3 flex flex-wrap gap-1.5">
        {["Technical depth", "Clear communication", "Structured thinking"].map((t) => (
          <span key={t} className="rounded-full border border-[var(--eb-border)] px-2 py-1 text-[9px] text-white/55">
            {t}
          </span>
        ))}
      </div>
    </motion.div>
  );
}

function CockpitScorePanel() {
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.8, ease: EASE, delay: 0.3 }}
      className="absolute left-[372px] top-0 z-30 w-[310px] rounded-[24px] border border-[#5A95F566] bg-[#071329] p-5 shadow-[0_40px_90px_-20px_rgba(0,0,0,0.8),0_0_42px_rgba(36,114,239,0.24)]"
    >
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold text-white/70">Candidate score</span>
      </div>
      <div className="mt-3 flex items-center gap-4">
        <ScoreRing value={87} />
        <div>
          <div className="font-display text-2xl font-bold text-white">87</div>
          <div className="text-xs font-medium text-[var(--eb-blue2)]">Great fit</div>
        </div>
      </div>
      <div className="mt-4 space-y-2">
        {[
          ["Technical skills", 92],
          ["Problem solving", 85],
          ["Communication", 83],
          ["Role alignment", 88],
        ].map(([label, val]) => (
          <div key={label as string} className="flex items-center gap-2 text-[11px]">
            <span className="w-24 shrink-0 text-white/55">{label}</span>
            <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-white/10">
              <motion.div
                className="h-full rounded-full bg-gradient-to-r from-[var(--eb-blue2)] to-[var(--eb-blue)]"
                initial={{ width: 0 }}
                whileInView={{ width: `${val}%` }}
                viewport={{ once: true }}
                transition={{ duration: 0.9, ease: EASE, delay: 0.4 }}
              />
            </div>
            <span className="w-6 shrink-0 text-right font-medium text-white/70">{val}</span>
          </div>
        ))}
      </div>
      <div className="mt-4 border-t border-[var(--eb-border)] pt-3">
        <div className="flex items-center justify-between text-[10px] text-white/45">
          <span>Live transcript</span>
          <span className="text-[var(--eb-blue2)]">View all</span>
        </div>
        <div className="mt-1.5 space-y-1 text-[10px] leading-snug text-white/55">
          <div>10:34 — Can you walk through a time…</div>
          <div>10:35 — Sure, in my last role…</div>
        </div>
      </div>
    </motion.div>
  );
}

function CockpitShortlistPanel() {
  const candidates = [
    { name: "Alex Chen", tag: "Strong fit", score: 87 },
    { name: "Priya Sharma", tag: "Strong fit", score: 83 },
    { name: "Daniel Kim", tag: "Good fit", score: 78 },
    { name: "Maria Lopez", tag: "Good fit", score: 76 },
  ];
  return (
    <motion.div
      initial={{ opacity: 0, y: 16, rotate: 2 }}
      animate={{ opacity: 1, y: 0, rotate: 2 }}
      transition={{ duration: 0.8, ease: EASE, delay: 0.45 }}
      className="absolute right-0 top-8 z-40 w-[230px] rotate-1 rounded-[22px] border border-[#5A95F577] bg-[#071329]/95 p-4 shadow-[0_30px_80px_-18px_rgba(0,0,0,0.8),0_0_38px_rgba(36,114,239,0.22)] backdrop-blur-xl"
    >
      <div className="text-xs font-semibold text-white">Shortlist</div>
      <div className="mt-3 space-y-2.5">
        {candidates.map((c, i) => (
          <div key={c.name} className="flex items-center gap-2">
            {initialsAvatar(c.name, i)}
            <div className="min-w-0 flex-1">
              <div className="truncate text-[11px] font-medium text-white/85">{c.name}</div>
              <div className="text-[9px] text-white/40">
                {c.score} · {c.tag}
              </div>
            </div>
          </div>
        ))}
      </div>
      <div className="mt-3 border-t border-[var(--eb-border)] pt-2.5 text-[10px] font-medium text-[var(--eb-blue2)]">
        View all candidates →
      </div>
    </motion.div>
  );
}

function Hero() {
  return (
    <section className="relative isolate overflow-hidden bg-[var(--eb-ink)] px-6 pb-16 pt-32 md:pb-20 md:pt-36">
      {/* atmosphere */}
      <div aria-hidden className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
        <HeroLandscape />
      </div>

      <div className="mx-auto grid max-w-[1320px] items-center gap-8 lg:grid-cols-[.9fr_1.1fr] lg:gap-6 xl:gap-10">
        <div className="relative z-10 min-w-0 text-center lg:text-left">
          <Reveal>
            <Eyebrow>For employers</Eyebrow>
          </Reveal>
          <Reveal delay={0.08}>
            <h1
              className="font-display mx-auto mt-5 max-w-[620px] font-bold leading-[.98] tracking-[-0.045em] text-white lg:mx-0"
              style={{ fontSize: "clamp(3rem, 2.2rem + 2.8vw, 4.8rem)" }}
            >
              Hire from <span className="text-[var(--eb-blue2)]">real interviews</span>, not a résumé pile.
            </h1>
          </Reveal>
          <Reveal delay={0.16}>
            <p className="mx-auto mt-6 max-w-md text-base leading-relaxed text-white/55 md:text-lg lg:mx-0">
              Every candidate completes a scored mock interview for your exact role. You review the
              transcript and the score before you ever decide who to talk to.
            </p>
          </Reveal>
          <Reveal delay={0.24} className="mt-9 flex flex-wrap items-center justify-center gap-3 lg:justify-start">
            <a
              href="mailto:hello@browsejobs.ai?subject=Hiring%20on%20BrowseJobs"
              className="group inline-flex items-center gap-1.5 rounded-full bg-[var(--eb-blue)] px-6 py-3 text-sm font-semibold text-white shadow-[0_0_0_1px_rgba(90,149,245,0.3),0_16px_40px_-10px_rgba(36,114,239,0.7)] transition hover:-translate-y-0.5 hover:bg-[var(--eb-blue2)]"
            >
              Talk to our team
              <span className="transition-transform group-hover:translate-x-0.5">→</span>
            </a>
            <a
              href="#workspace"
              className="inline-flex items-center gap-2 rounded-full border border-[var(--eb-border)] px-6 py-3 text-sm font-semibold text-white/85 transition hover:border-white/40 hover:text-white"
            >
              <span className="grid size-5 place-items-center rounded-full bg-white/10">
                <svg viewBox="0 0 10 10" className="size-2.5 translate-x-[1px]" fill="currentColor">
                  <path d="M1 1l8 4-8 4V1z" />
                </svg>
              </span>
              Watch product tour
            </a>
          </Reveal>
          <Reveal delay={0.32} className="mt-14 flex items-center justify-center gap-7 lg:justify-start">
            {[
              ["10x", "faster hiring"],
              ["Higher", "quality shortlists"],
              ["Built for", "modern teams"],
            ].map(([label, sub], i) => (
              <div key={label} className={`text-left ${i > 0 ? "border-l border-[var(--eb-border)] pl-8" : ""}`}>
                <div className="text-sm font-bold text-white">{label}</div>
                <div className="mt-0.5 text-[11px] text-white/40">{sub}</div>
              </div>
            ))}
          </Reveal>
        </div>

        {/* cockpit */}
        <Reveal delay={0.2} className="relative mx-auto h-[320px] w-full min-w-0 overflow-hidden sm:h-[390px] lg:h-[430px] xl:overflow-visible">
          <div
            aria-hidden
            className="absolute inset-x-[10%] top-[8%] -z-10 h-[70%] rounded-full opacity-90 blur-3xl"
            style={{ background: "radial-gradient(ellipse at center,rgba(36,114,239,.58),transparent 70%)" }}
          />
          <div className="absolute left-1/2 top-0 h-[440px] w-[860px] origin-top -translate-x-1/2 scale-[.42] min-[480px]:scale-[.55] sm:scale-[.68] md:scale-[.82] lg:scale-[.9] xl:scale-100">
            <CockpitChatPanel />
            <CockpitScorePanel />
            <CockpitShortlistPanel />
          </div>
        </Reveal>
      </div>
    </section>
  );
}

/* ------------------------------ workspace bento ---------------------------- */

function BentoShell({
  className = "",
  dark = false,
  children,
}: {
  className?: string;
  dark?: boolean;
  children: React.ReactNode;
}) {
  return (
    <div
      className={`relative overflow-hidden rounded-[22px] border p-5 ${
        dark
          ? "border-white/10 bg-[var(--eb-ink)] text-white"
          : "border-[var(--eb-navy)]/10 bg-white text-[var(--eb-ink)]"
      } ${className}`}
    >
      {children}
    </div>
  );
}

function IconBadge({ children, dark = false }: { children: React.ReactNode; dark?: boolean }) {
  return (
    <span
      className="grid size-9 place-items-center rounded-xl"
      style={{
        background: dark ? "rgba(90,149,245,0.16)" : "rgba(36,114,239,0.1)",
        color: "var(--eb-blue)",
      }}
    >
      {children}
    </span>
  );
}

function Workspace() {
  return (
    <section id="workspace" className="bg-[var(--eb-paper)] px-6 py-14 md:py-20">
      <div className="mx-auto max-w-[1320px]">
        <Reveal className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <Eyebrow tone="muted">The hiring workspace</Eyebrow>
            <h2 className="font-display mt-3 text-3xl font-bold tracking-[-0.02em] text-[var(--eb-ink)] md:text-[2.75rem]">
              Everything you need, in one place.
            </h2>
          </div>
          <p className="max-w-xs text-sm leading-relaxed text-[var(--eb-navy2)]">
            From interview to offer, BrowseJobs keeps your hiring focused, fair, and fast.
          </p>
        </Reveal>

        <div className="mt-8 grid gap-4 lg:grid-cols-12">
          {/* AI interviews — large */}
          <Reveal className="lg:col-span-6">
            <BentoShell className="h-full">
              <div className="grid h-full gap-5 sm:grid-cols-[.78fr_1.22fr] sm:items-center">
                <div>
                  <IconBadge>
                    <RobotIcon className="size-4" />
                  </IconBadge>
                  <h3 className="font-display mt-3 text-xl font-bold tracking-tight">AI interviews</h3>
                  <p className="mt-1 max-w-sm text-sm leading-relaxed text-[var(--eb-navy2)]">
                    AI conducts realistic, role-specific interviews with every applicant.
                  </p>
                  <Link
                    href="/employer"
                    className="mt-4 inline-flex items-center gap-1.5 rounded-full bg-[var(--eb-blue)] px-4 py-2 text-xs font-semibold text-white shadow-[0_10px_25px_-10px_rgba(36,114,239,.8)]"
                  >
                    See how it works →
                  </Link>
                </div>

                <div className="relative overflow-hidden rounded-2xl border border-[var(--eb-blue)]/20 bg-[#edf4ff] p-4 shadow-[inset_0_0_50px_rgba(36,114,239,.12)]">
                  <div aria-hidden className="absolute -right-8 -top-10 size-36 rounded-full bg-[var(--eb-blue)]/25 blur-2xl" />
                  <div className="relative flex items-center gap-2">
                    <span className="grid size-6 place-items-center rounded-full bg-gradient-to-br from-[var(--eb-blue2)] to-[var(--eb-blue)] text-white">
                      <RobotIcon className="size-3" />
                    </span>
                    <span className="text-xs font-semibold text-[var(--eb-ink)]">AI interview</span>
                    <span className="ml-auto size-1.5 rounded-full bg-[#24c78f]" />
                  </div>
                  <div className="relative mt-3 max-w-[88%] rounded-xl rounded-tl-sm bg-white p-3 text-[12px] leading-snug text-[var(--eb-ink)] shadow-sm">
                    How would you design a scalable notification system?
                  </div>
                  <div className="relative mt-3 flex items-center gap-2 rounded-full bg-white px-3 py-2">
                    <div className="flex h-3.5 items-end gap-[2px]">
                      {[3, 7, 4, 10, 5, 8].map((h, i) => (
                        <span key={i} className="w-[2px] rounded-full bg-[var(--eb-blue)]" style={{ height: h }} />
                      ))}
                    </div>
                    <span className="text-[10px] text-[var(--eb-navy2)]">Listening…</span>
                  </div>
                </div>
              </div>
            </BentoShell>
          </Reveal>

          {/* Jobs */}
          <Reveal className="lg:col-span-3">
            <BentoShell className="h-full">
              <IconBadge>
                <DocumentIcon className="size-4" />
              </IconBadge>
              <h3 className="font-display mt-4 text-lg font-bold tracking-tight">Jobs</h3>
              <p className="mt-1.5 text-sm leading-relaxed text-[var(--eb-navy2)]">
                Create roles, set interview criteria, and start receiving AI-interviewed candidates.
              </p>
              <div className="mt-4 flex items-center justify-between rounded-xl border border-[var(--eb-navy)]/10 bg-[var(--eb-paper)] p-3">
                <div>
                  <div className="text-[12px] font-semibold text-[var(--eb-ink)]">Senior Data Analyst</div>
                  <div className="text-[10px] text-[var(--eb-navy2)]">24 candidates · Live now</div>
                </div>
                <span className="grid h-5 w-9 shrink-0 items-center rounded-full bg-[var(--eb-blue)] px-0.5">
                  <span className="size-4 translate-x-4 rounded-full bg-white" />
                </span>
              </div>
            </BentoShell>
          </Reveal>

          {/* Team */}
          <Reveal delay={0.06} className="lg:col-span-3">
            <BentoShell className="h-full">
              <IconBadge>
                <UsersIcon className="size-4" />
              </IconBadge>
              <h3 className="font-display mt-4 text-lg font-bold tracking-tight">Team</h3>
              <p className="mt-1.5 text-sm leading-relaxed text-[var(--eb-navy2)]">
                Invite teammates, assign interview rounds, and collaborate on candidates.
              </p>
              <div className="mt-4 flex items-center justify-between">
                <div className="flex -space-x-2">
                  {["Ravi Kumar", "Anita Rao", "Sam Iyer"].map((n, i) => (
                    <span key={n} className="ring-2 ring-white">
                      {initialsAvatar(n, i)}
                    </span>
                  ))}
                  <span className="grid size-8 place-items-center rounded-full bg-[var(--eb-navy)]/10 text-[10px] font-bold text-[var(--eb-navy2)] ring-2 ring-white">
                    +3
                  </span>
                </div>
                <span className="rounded-full border border-[var(--eb-navy)]/15 px-3 py-1.5 text-[11px] font-semibold text-[var(--eb-ink)]">
                  Invite
                </span>
              </div>
            </BentoShell>
          </Reveal>

          {/* Pipeline */}
          <Reveal className="lg:col-span-6">
            <BentoShell className="h-full">
              <div className="grid gap-4 sm:grid-cols-[.75fr_1.25fr] sm:items-center">
                <div>
                  <IconBadge>
                    <PipelineIcon className="size-4" />
                  </IconBadge>
                  <h3 className="font-display mt-3 text-lg font-bold tracking-tight">Pipeline</h3>
                  <p className="mt-1 max-w-sm text-sm leading-relaxed text-[var(--eb-navy2)]">
                    Track every applicant, from interview to offer — all in one clean workspace.
                  </p>
                </div>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                {[
                  ["AI interview", "152"],
                  ["In review", "28"],
                  ["Shortlisted", "12"],
                  ["Offer", "3"],
                ].map(([label, val]) => (
                  <div key={label} className="rounded-xl border border-[var(--eb-navy)]/10 bg-[var(--eb-paper)] p-3">
                    <div className="font-display text-lg font-bold text-[var(--eb-ink)]">{val}</div>
                    <div className="text-[10px] text-[var(--eb-navy2)]">{label}</div>
                  </div>
                ))}
                </div>
              </div>
            </BentoShell>
          </Reveal>

          {/* Dashboard — dark large */}
          <Reveal delay={0.08} className="lg:col-span-6">
            <BentoShell dark className="h-full">
              <div
                aria-hidden
                className="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full opacity-30 blur-3xl"
                style={{ background: "#2472EF" }}
              />
              <div className="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                <div>
                  <IconBadge dark>
                    <TrendUpIcon className="size-4" />
                  </IconBadge>
                  <h3 className="font-display mt-4 text-xl font-bold tracking-tight text-white">Dashboard</h3>
                  <p className="mt-1.5 max-w-xs text-sm leading-relaxed text-white/50">
                    A real-time view of your hiring health and top talent.
                  </p>
                  <div className="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    {[
                      ["324", "Total applicants"],
                      ["24", "Shortlisted"],
                      ["8", "Offers"],
                      ["12 days", "Avg. time to hire"],
                    ].map(([val, label]) => (
                      <div key={label}>
                        <div className="font-display text-lg font-bold text-white">{val}</div>
                        <div className="text-[10px] text-white/40">{label}</div>
                      </div>
                    ))}
                  </div>
                </div>
                <div className="flex h-28 items-end gap-2 sm:w-56">
                  {[35, 55, 40, 70, 50, 85, 60].map((h, i) => (
                    <motion.div
                      key={i}
                      className="flex-1 rounded-t-md bg-gradient-to-t from-[var(--eb-blue)] to-[var(--eb-blue2)]"
                      style={{ height: `${h}%` }}
                      initial={{ scaleY: 0 }}
                      whileInView={{ scaleY: 1 }}
                      viewport={{ once: true }}
                      transition={{ duration: 0.6, ease: EASE, delay: 0.1 + i * 0.06 }}
                    />
                  ))}
                </div>
              </div>
            </BentoShell>
          </Reveal>
        </div>
      </div>
    </section>
  );
}

/* -------------------------------- how it works ----------------------------- */

const STEPS = [
  { n: "01", icon: DocumentIcon, title: "Post the role", body: "Tell us about the role — skills, experience, and what good looks like." },
  { n: "02", icon: RobotIcon, title: "Real interviews", body: "Every applicant takes a job-specific, AI-led interview with tailored questions." },
  { n: "03", icon: TrendUpIcon, title: "Score & transcript", body: "Get a clear score, full transcript, and skill breakdown for every candidate." },
  { n: "04", icon: UsersIcon, title: "You decide", body: "Review the ranked shortlist and move forward with confidence." },
] as const;

function HowItWorks() {
  return (
    <section id="how-it-works" className="relative overflow-hidden bg-[#040812] px-6 py-16 md:py-20">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0"
        style={{
          backgroundImage: "url(/employers/earth-horizon.webp)",
          backgroundSize: "cover",
          backgroundPosition: "center 20%",
          opacity: 0.55,
        }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0"
        style={{
          background:
            "linear-gradient(180deg, rgba(4,8,18,.9) 0%, rgba(4,8,18,.55) 40%, rgba(4,8,18,.92) 100%), radial-gradient(800px 360px at 18% 0%,rgba(36,114,239,.14),transparent 70%)",
        }}
      />
      <div className="relative mx-auto max-w-[1320px]">
        <Reveal className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <Eyebrow>How it works</Eyebrow>
            <h2 className="font-display mt-3 text-3xl font-bold tracking-[-0.02em] text-white md:text-[2.75rem]">
              From job title to a ranked shortlist.
            </h2>
          </div>
          <p className="max-w-xs text-sm leading-relaxed text-white/40">
            Four simple steps. Real candidates. Real insight. Better hires.
          </p>
        </Reveal>

        <div className="relative mt-10">
          <svg
            aria-hidden
            viewBox="0 0 1200 60"
            preserveAspectRatio="none"
            className="absolute left-0 right-0 top-6 hidden h-[60px] w-full md:block"
          >
            <path
              d="M70 28 C 250 -25, 340 75, 520 22 S 780 -5, 910 30 S 1050 60, 1140 18"
              fill="none"
              stroke="url(#pathGrad)"
              strokeWidth="2.2"
              strokeLinecap="round"
              style={{ filter: "drop-shadow(0 0 8px #2472EF)" }}
            />
            <defs>
              <linearGradient id="pathGrad" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stopColor="#2472EF" stopOpacity="0" />
                <stop offset="15%" stopColor="#5A95F5" stopOpacity="0.8" />
                <stop offset="85%" stopColor="#5A95F5" stopOpacity="0.8" />
                <stop offset="100%" stopColor="#2472EF" stopOpacity="0" />
              </linearGradient>
            </defs>
          </svg>

          <div className="relative grid gap-8 md:grid-cols-4">
            {STEPS.map((s, i) => {
              const Icon = s.icon;
              return (
                <Reveal key={s.n} delay={i * 0.1}>
                  <div className="relative">
                    <span className="grid size-12 place-items-center rounded-2xl border border-[var(--eb-border)] bg-[var(--eb-navy)] text-[var(--eb-blue2)] shadow-[0_0_0_6px_rgba(9,12,21,1)]">
                      <Icon className="size-5" />
                    </span>
                    <div className="mt-4 font-mono text-[11px] font-semibold text-white/30">{s.n}</div>
                    <h3 className="font-display mt-1 text-base font-bold tracking-tight text-white">{s.title}</h3>
                    <p className="mt-1.5 text-[13px] leading-relaxed text-white/50">{s.body}</p>
                  </div>
                </Reveal>
              );
            })}
          </div>
        </div>
      </div>
    </section>
  );
}

/* ---------------------------------- proof ---------------------------------- */

function Proof() {
  return (
    <section id="proof" className="bg-[var(--eb-paper)] px-6 py-14 md:py-16">
      <div className="mx-auto max-w-[1320px]">
        <Reveal className="grid items-center gap-10 lg:grid-cols-[.8fr_1.45fr]">
          <div>
            <Eyebrow tone="muted">Why it&apos;s different</Eyebrow>
            <h2 className="font-display mt-3 text-3xl font-bold tracking-[-0.02em] text-[var(--eb-ink)] md:text-[2.5rem]">
              A score is either real, or we say so.
            </h2>
            <p className="mt-4 max-w-md text-base leading-relaxed text-[var(--eb-navy2)]">
              If an interview hasn&apos;t been graded yet, the pipeline says &ldquo;not scored&rdquo; — never a
              fabricated number. What you see is what actually happened, not a polished guess.
            </p>
            <div className="mt-8 grid grid-cols-3 gap-4 border-t border-[var(--eb-navy)]/10 pt-6">
              {[
                ["98%", "Interviews completed without human intervention"],
                ["3×", "Higher quality shortlists"],
                ["12 days", "Average time to hire"],
              ].map(([label, sub]) => (
                <div key={label}>
                  <div className="font-display text-2xl font-bold tracking-tight text-[var(--eb-blue)] md:text-3xl">{label}</div>
                  <div className="mt-1 text-[11px] leading-snug text-[var(--eb-navy2)]">{sub}</div>
                </div>
              ))}
            </div>
          </div>

          <div className="grid gap-3 md:grid-cols-[.9fr_1.1fr_.9fr]">
            <div className="rounded-[22px] border border-[var(--eb-navy)]/10 bg-white p-5 shadow-[0_20px_60px_-30px_rgba(9,12,21,0.3)]">
              <div className="flex items-center gap-3">
                {initialsAvatar("Alex Chen", 0)}
                <div>
                  <div className="text-[13px] font-semibold text-[var(--eb-ink)]">Alex Chen</div>
                  <div className="text-[11px] text-[var(--eb-navy2)]">Data Analyst</div>
                </div>
                <div className="ml-auto">
                  <ScoreRing value={87} size={48} light />
                </div>
              </div>
              <div className="mt-4 space-y-1.5">
                {[
                  ["Technical skills", 92],
                  ["Problem solving", 85],
                  ["Communication", 83],
                  ["Role alignment", 88],
                ].map(([label, val]) => (
                  <div key={label as string} className="flex items-center justify-between text-[11px]">
                    <span className="flex items-center gap-1.5 text-[var(--eb-navy2)]">
                      <DocumentCheckIcon className="size-3 text-[var(--eb-blue)]" />
                      {label}
                    </span>
                    <span className="font-semibold text-[var(--eb-ink)]">{val}</span>
                  </div>
                ))}
              </div>
            </div>

            <div className="rounded-[22px] border border-[var(--eb-navy)]/10 bg-white p-5 shadow-[0_20px_60px_-30px_rgba(9,12,21,0.3)]">
              <div className="text-[11px] font-semibold text-[var(--eb-navy2)]">Transcript excerpt</div>
              <div className="mt-3 rounded-xl bg-[var(--eb-paper)] p-3">
                <div className="flex items-center gap-2 text-[10px] font-medium text-[var(--eb-blue)]">
                  <span className="grid size-5 place-items-center rounded-full bg-[var(--eb-blue)]/10">AI</span>
                  10:34 AM
                </div>
                <p className="mt-2 text-[11px] leading-relaxed text-[var(--eb-ink)]/70">
                  &ldquo;Can you walk me through a time you handled a complex technical trade-off?&rdquo;
                </p>
              </div>
              <p className="mt-2 text-[11px] leading-relaxed text-[var(--eb-ink)]/70">
                &ldquo;In my last role, we had to balance performance with cost — I led a spike, evaluated a few
                approaches including caching and query optimization…&rdquo;
              </p>
            </div>

            <div className="rounded-[22px] border border-[var(--eb-navy)]/10 bg-white p-5 shadow-[0_20px_60px_-30px_rgba(9,12,21,0.3)]">
              <ul className="space-y-2 text-[11px] text-[var(--eb-ink)]">
                {[
                  "Scored against a real rubric",
                  "Every stage move recorded",
                  "No score without evidence",
                  "Empty stays empty, honestly",
                ].map((line) => (
                  <li key={line} className="flex items-start gap-1.5">
                    <svg viewBox="0 0 12 12" className="mt-0.5 size-3 shrink-0 text-verify" style={{ color: "#0ba860" }} fill="none">
                      <path d="M2 6.2l2.6 2.6L10 3.2" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                    <span className="leading-snug">{line}</span>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}

/* ---------------------------------- final cta ------------------------------- */

function FinalCta() {
  return (
    <section id="cta" className="relative isolate overflow-hidden bg-[#040812] px-6 py-14 text-center md:py-16">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 -z-20"
        style={{
          backgroundImage: "url(/employers/mountain-horizon.webp)",
          backgroundSize: "cover",
          backgroundPosition: "center 30%",
        }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 -z-10"
        style={{
          background:
            "linear-gradient(180deg, rgba(4,8,18,.85) 0%, rgba(4,8,18,.4) 45%, rgba(4,8,18,.9) 100%)",
        }}
      />
      <Reveal className="relative">
        <Eyebrow>Ready to hire smarter</Eyebrow>
        <h2 className="font-display mx-auto mt-3 max-w-xl text-3xl font-bold tracking-[-0.02em] text-white md:text-4xl">
          Ready to see it on your next role?
        </h2>
        <p className="mx-auto mt-3 max-w-md text-sm text-white/50">
          Tell us what you&apos;re hiring for and we&apos;ll set your workspace up.
        </p>
        <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
          <a
            href="mailto:hello@browsejobs.ai?subject=Hiring%20on%20BrowseJobs"
            className="group inline-flex items-center gap-1.5 rounded-full bg-[var(--eb-blue)] px-7 py-3.5 text-sm font-semibold text-white shadow-[0_0_0_1px_rgba(90,149,245,0.35),0_20px_50px_-10px_rgba(36,114,239,0.8)] transition hover:-translate-y-0.5 hover:bg-[var(--eb-blue2)]"
          >
            Talk to our team
            <span className="transition-transform group-hover:translate-x-0.5">→</span>
          </a>
        </div>
      </Reveal>
    </section>
  );
}

/* ----------------------------------- footer --------------------------------- */

const FOOTER_COLS = [
  {
    title: "Product",
    links: [
      { label: "AI interviews", href: "#workspace" },
      { label: "Jobs", href: "/jobs" },
      { label: "Pipeline", href: "#workspace" },
    ],
  },
  {
    title: "Company",
    links: [
      { label: "Reviews", href: "/reviews" },
      { label: "Student sign in", href: "/student" },
      { label: "Employer sign in", href: "/employer" },
    ],
  },
  {
    title: "Resources",
    links: [
      { label: "Privacy Policy", href: "/privacy-policy" },
      { label: "Terms", href: "/terms" },
      { label: "Refund Policy", href: "/refund-policy" },
    ],
  },
] as const;

function PageFooter() {
  return (
    <footer className="bg-[#060a12] px-6 py-10">
      <div className="mx-auto max-w-[1320px]">
        <div className="grid gap-8 md:grid-cols-[1.3fr_repeat(3,1fr)]">
          <div>
            <Wordmark tone="dark" />
            <p className="mt-3 max-w-[220px] text-[13px] leading-relaxed text-white/40">
              Real interviews. Real people. A brighter tomorrow.
            </p>
          </div>
          {FOOTER_COLS.map((col) => (
            <div key={col.title}>
              <div className="text-[11px] font-semibold uppercase tracking-[0.2em] text-white/35">{col.title}</div>
              <ul className="mt-3 space-y-2">
                {col.links.map((l) => (
                  <li key={l.label}>
                    <Link href={l.href} className="text-[13px] text-white/55 transition-colors hover:text-white">
                      {l.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
        <div className="mt-7 border-t border-[var(--eb-border)] pt-5 text-[12px] text-white/35">
          © {new Date().getFullYear()} IBrowseJobs Technologies Pvt Ltd. All rights reserved.
        </div>
      </div>
    </footer>
  );
}

/* ----------------------------------- page ------------------------------------ */

export default function EmployersLanding() {
  return (
    <MotionConfig reducedMotion="user">
      <div style={PALETTE} className="min-h-screen bg-[var(--eb-ink)]">
        <Nav />
        <Hero />
        <Workspace />
        <HowItWorks />
        <Proof />
        <FinalCta />
        <PageFooter />
      </div>
    </MotionConfig>
  );
}
