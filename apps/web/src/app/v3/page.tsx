"use client";

/**
 * /v3 — "keynote" landing template demo. Apple-grade art direction: huge
 * tracking-tight type on white, scroll-scrubbed word reveals, and sticky dark
 * product scenes where each feature demonstrates itself (auto-typing AI chat,
 * live voice-call waveform, sweeping job radar). Isolated from the live site.
 */

import { Fragment, useEffect, useRef, useState } from "react";
import Link from "next/link";
import Lenis from "lenis";
import { HomeJobs } from "@/components/landing/HomeJobs";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { LoginMenu } from "@/components/landing/LoginMenu";
import { Wordmark } from "@/components/brand/Wordmark";
import { Footer } from "@/components/landing/Footer";
import { LeadModal } from "@/components/landing/LeadModal";
import { StickyCta } from "@/components/landing/StickyCta";
import { openLeadModal } from "@/components/landing/leadModalBus";
import {
  AnimatePresence,
  animate,
  motion,
  useInView,
  useMotionValue,
  useScroll,
  useSpring,
  useTransform,
  type MotionValue,
} from "framer-motion";

/* --------------------------------- shared --------------------------------- */

const EASE = [0.16, 1, 0.3, 1] as const;

function useLenis() {
  useEffect(() => {
    const lenis = new Lenis({ lerp: 0.08 });
    let raf = 0;
    const loop = (t: number) => { lenis.raf(t); raf = requestAnimationFrame(loop); };
    raf = requestAnimationFrame(loop);
    return () => { cancelAnimationFrame(raf); lenis.destroy(); };
  }, []);
}

function Magnetic({ children }: { children: React.ReactNode }) {
  const ref = useRef<HTMLDivElement>(null);
  const x = useMotionValue(0);
  const y = useMotionValue(0);
  const sx = useSpring(x, { stiffness: 200, damping: 15 });
  const sy = useSpring(y, { stiffness: 200, damping: 15 });
  return (
    <motion.div
      ref={ref}
      style={{ x: sx, y: sy }}
      onMouseMove={(e) => {
        const r = ref.current!.getBoundingClientRect();
        x.set((e.clientX - r.left - r.width / 2) * 0.3);
        y.set((e.clientY - r.top - r.height / 2) * 0.3);
      }}
      onMouseLeave={() => { x.set(0); y.set(0); }}
      className="inline-block"
    >
      {children}
    </motion.div>
  );
}

function Counter({ to, suffix = "", decimals = 0 }: { to: number; suffix?: string; decimals?: number }) {
  const ref = useRef<HTMLSpanElement>(null);
  const inView = useInView(ref, { once: true, margin: "-80px" });
  useEffect(() => {
    if (!inView || !ref.current) return;
    const c = animate(0, to, {
      duration: 2, ease: EASE,
      onUpdate: (v) => { ref.current!.textContent = v.toFixed(decimals) + suffix; },
    });
    return () => c.stop();
  }, [inView, to, suffix, decimals]);
  return <span ref={ref}>0{suffix}</span>;
}

/* Apple-style scrubbed paragraph: words ignite as the scrollbar passes them. */
function Word({ children, progress, range }: { children: string; progress: MotionValue<number>; range: [number, number] }) {
  const opacity = useTransform(progress, range, [0.12, 1]);
  // Real space after each word so selection/copy and screen readers get spaces.
  return (
    <>
      <motion.span style={{ opacity }} className="inline-block">
        {children}
      </motion.span>{" "}
    </>
  );
}

function ScrubText({ text, className = "" }: { text: string; className?: string }) {
  const ref = useRef<HTMLParagraphElement>(null);
  const { scrollYProgress } = useScroll({ target: ref, offset: ["start 0.85", "start 0.3"] });
  const words = text.split(" ");
  return (
    <p ref={ref} className={className}>
      {words.map((w, i) => (
        <Word key={i} progress={scrollYProgress} range={[i / words.length, Math.min(1, (i + 1.5) / words.length)]}>
          {w}
        </Word>
      ))}
    </p>
  );
}

/* --------------------------------- manifesto -------------------------------- */

const MACHINE_VERBS = [
  { icon: "🎧", verb: "Studies", body: "~50 real interview transcripts, every day", accent: "#1b6df0" },
  { icon: "🎯", verb: "Teaches", body: "exactly what companies ask right now", accent: "#7c3aed" },
  { icon: "🥊", verb: "Drills", body: "you until pressure feels normal", accent: "#f59e0b" },
  { icon: "📡", verb: "Hunts", body: "jobs with you until you sign an offer", accent: "#0ba860" },
];

function ManifestoSection() {
  return (
    <section className="mx-auto max-w-5xl px-6 py-32 md:py-44">
      <ScrubText
        className="font-display text-4xl font-bold leading-[1.12] tracking-tight text-ink md:text-6xl"
        text="Colleges hand you a degree. Nobody hands you a career."
      />
      <motion.p
        initial={{ opacity: 0, y: 24 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true, margin: "-80px" }}
        transition={{ duration: 0.9, ease: EASE }}
        className="font-display mt-6 text-4xl font-bold leading-[1.12] tracking-tight md:text-6xl"
      >
        <span className="bg-gradient-to-r from-[#1b6df0] via-[#4d94ff] to-[#7c3aed] bg-clip-text text-transparent">
          So we built a machine.
        </span>
      </motion.p>

      <div className="mt-14 grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
        {MACHINE_VERBS.map((m, i) => (
          <motion.div
            key={m.verb}
            initial={{ opacity: 0, y: 40, scale: 0.94 }}
            whileInView={{ opacity: 1, y: 0, scale: 1 }}
            viewport={{ once: true, margin: "-60px" }}
            transition={{ delay: 0.15 + i * 0.12, duration: 0.7, ease: EASE }}
            whileHover={{ y: -6 }}
            className="relative overflow-hidden rounded-2xl border p-5 md:p-6"
            style={{ background: `linear-gradient(160deg, ${m.accent}10, #ffffff 60%)`, borderColor: `${m.accent}30` }}
          >
            <span aria-hidden className="pointer-events-none absolute -right-2 -top-4 font-display text-6xl font-bold" style={{ color: `${m.accent}12` }}>
              {`0${i + 1}`}
            </span>
            <motion.span
              initial={{ scale: 0, rotate: -15 }}
              whileInView={{ scale: 1, rotate: 0 }}
              viewport={{ once: true }}
              transition={{ delay: 0.3 + i * 0.12, type: "spring", stiffness: 280, damping: 14 }}
              className="grid h-10 w-10 place-items-center rounded-xl text-lg"
              style={{ background: `${m.accent}16` }}
            >
              {m.icon}
            </motion.span>
            <p className="font-display mt-3 text-xl font-bold md:text-2xl" style={{ color: m.accent }}>
              {m.verb}
            </p>
            <p className="mt-1 text-xs leading-snug text-fg/55 md:text-sm">{m.body}</p>
            <motion.div
              initial={{ width: 0 }}
              whileInView={{ width: "34%" }}
              viewport={{ once: true }}
              transition={{ delay: 0.5 + i * 0.12, duration: 0.7, ease: EASE }}
              className="mt-4 h-1 rounded-full"
              style={{ background: m.accent }}
            />
          </motion.div>
        ))}
      </div>
    </section>
  );
}

/* ---------------------------------- navbar --------------------------------- */

const NAV_LINKS = [
  { href: "#path", label: "How it works" },
  { href: "#programs", label: "Programs" },
  { href: "#fees", label: "Fees" },
  { href: "#verify", label: "Verify us" },
  { href: "#reviews", label: "Reviews" },
];

function SiteNav() {
  const [open, setOpen] = useState(false);

  // Lenis swallows native hash jumps, so drive section scrolls explicitly.
  const go = (e: React.MouseEvent, href: string) => {
    e.preventDefault();
    setOpen(false);
    const el = document.querySelector(href);
    if (el) {
      requestAnimationFrame(() => el.scrollIntoView({ behavior: "smooth", block: "start" }));
      history.replaceState(null, "", href);
    }
  };

  return (
    <motion.header
      initial={{ y: -60, opacity: 0 }} animate={{ y: 0, opacity: 1 }} transition={{ duration: 0.9, ease: EASE }}
      className="fixed inset-x-0 top-0 z-50 border-b border-fg/[0.06] bg-surface/80 backdrop-blur-xl"
    >
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3.5 md:px-6">
        <Link href="/" aria-label="BrowseJobs home" onClick={() => setOpen(false)}>
          <Wordmark />
        </Link>

        {/* Desktop menu */}
        <nav className="hidden items-center gap-7 md:flex">
          {NAV_LINKS.map((l) => (
            <a key={l.href} href={l.href} onClick={(e) => go(e, l.href)} className="text-sm font-medium text-fg/50 transition-colors hover:text-ink">
              {l.label}
            </a>
          ))}
          <Link href="/jobs" className="text-sm font-medium text-fg/50 transition-colors hover:text-ink">
            Jobs
          </Link>
          <Link href="/employers" className="text-sm font-medium text-fg/50 transition-colors hover:text-ink">
            For employers
          </Link>
          <LoginMenu />
        </nav>

        {/* Hamburger (mobile only) */}
        <button
          aria-label={open ? "Close menu" : "Open menu"}
          aria-expanded={open}
          onClick={() => setOpen((o) => !o)}
          className="relative z-50 flex h-10 w-10 items-center justify-center rounded-full border border-fg/10 bg-surface md:hidden"
        >
          <span className="relative block h-3.5 w-4.5">
            <motion.span
              animate={open ? { rotate: 45, y: 6 } : { rotate: 0, y: 0 }}
              transition={{ duration: 0.25, ease: EASE }}
              className="absolute left-0 top-0 block h-[2px] w-full rounded-full bg-[#0a1220]"
            />
            <motion.span
              animate={open ? { opacity: 0, x: 8 } : { opacity: 1, x: 0 }}
              transition={{ duration: 0.2 }}
              className="absolute left-0 top-1/2 block h-[2px] w-full -translate-y-1/2 rounded-full bg-[#0a1220]"
            />
            <motion.span
              animate={open ? { rotate: -45, y: -6 } : { rotate: 0, y: 0 }}
              transition={{ duration: 0.25, ease: EASE }}
              className="absolute bottom-0 left-0 block h-[2px] w-full rounded-full bg-[#0a1220]"
            />
          </span>
        </button>
      </div>

      {/* Mobile menu panel */}
      <AnimatePresence>
        {open && (
          <motion.nav
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: "auto" }}
            exit={{ opacity: 0, height: 0 }}
            transition={{ duration: 0.35, ease: EASE }}
            className="overflow-hidden border-t border-fg/[0.06] bg-surface md:hidden"
          >
            <div className="space-y-1 px-5 py-4">
              {NAV_LINKS.map((l, i) => (
                <motion.a
                  key={l.href}
                  href={l.href}
                  onClick={(e) => go(e, l.href)}
                  initial={{ opacity: 0, x: -16 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: 0.06 + i * 0.05, duration: 0.3, ease: EASE }}
                  className="block rounded-xl px-4 py-3 text-base font-semibold text-ink transition-colors hover:bg-paper"
                >
                  {l.label}
                </motion.a>
              ))}
              <motion.div
                initial={{ opacity: 0, x: -16 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: 0.06 + NAV_LINKS.length * 0.05, duration: 0.3, ease: EASE }}
              >
                <Link
                  href="/jobs"
                  onClick={() => setOpen(false)}
                  className="block rounded-xl px-4 py-3 text-base font-semibold text-fg/50 transition-colors hover:bg-paper"
                >
                  Jobs
                </Link>
                <Link
                  href="/employers"
                  onClick={() => setOpen(false)}
                  className="block rounded-xl px-4 py-3 text-base font-semibold text-fg/50 transition-colors hover:bg-paper"
                >
                  For employers
                </Link>
                <Link
                  href="/employer"
                  onClick={() => setOpen(false)}
                  className="block rounded-xl px-4 py-3 text-base font-semibold text-fg/50 transition-colors hover:bg-paper"
                >
                  Employer sign in
                </Link>
                <Link
                  href="/student"
                  onClick={() => setOpen(false)}
                  className="block rounded-xl px-4 py-3 text-base font-semibold text-fg/50 transition-colors hover:bg-paper"
                >
                  Student sign in
                </Link>
              </motion.div>
              <motion.button
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.1 + NAV_LINKS.length * 0.05, duration: 0.3, ease: EASE }}
                onClick={() => { setOpen(false); openLeadModal({ variant: "masterclass" }); }}
                className="mt-2 block w-full rounded-full bg-[#1b6df0] px-5 py-3 text-center text-base font-bold text-white shadow-[0_8px_28px_rgba(27,109,240,0.35)]"
              >
                Book Free Masterclass
              </motion.button>
            </div>
          </motion.nav>
        )}
      </AnimatePresence>
    </motion.header>
  );
}

/* ------------------------------ product demos ------------------------------ */

const CHAT_SCRIPT = [
  { from: "you", text: "Why does my SQL join return duplicate rows? 😩" },
  { from: "ai", text: "Your LEFT JOIN matches multiple rows in `orders` per customer. Add a GROUP BY, or pre-aggregate orders in a CTE first." },
  { from: "ai", text: "Here's the fixed query for your exact schema — try it 👇" },
  { from: "you", text: "It worked!! You explained it better than YouTube 😭" },
] as const;

/** Phone running the AI tutor — messages type themselves in when visible. */
function ChatDemo() {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { once: true, margin: "-15%" });
  const [shown, setShown] = useState(0);
  const [typing, setTyping] = useState(false);
  useEffect(() => {
    if (!inView) return;
    let alive = true;
    (async () => {
      for (let i = 0; i < CHAT_SCRIPT.length; i++) {
        if (!alive) return;
        setTyping(true);
        await new Promise((r) => setTimeout(r, CHAT_SCRIPT[i].from === "ai" ? 1300 : 800));
        if (!alive) return;
        setTyping(false);
        setShown(i + 1);
        await new Promise((r) => setTimeout(r, 500));
      }
    })();
    return () => { alive = false; };
  }, [inView]);
  return (
    <div ref={ref} className="mx-auto w-[300px] rounded-[42px] border border-white/15 bg-[#0a0f1c] p-3 shadow-[0_40px_120px_rgba(27,109,240,0.25)]">
      <div className="rounded-[32px] bg-[#0d1424] px-4 pb-5 pt-4">
        <div className="mb-4 flex items-center gap-2.5 border-b border-white/5 pb-3">
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-[#1b6df0] to-[#7c3aed] text-sm">🧠</div>
          <div>
            <p className="text-xs font-semibold text-white">AI Tutor</p>
            <p className="text-[10px] text-emerald-400">● replies in seconds</p>
          </div>
        </div>
        <div className="flex min-h-[290px] flex-col justify-end gap-2.5">
          {CHAT_SCRIPT.slice(0, shown).map((m, i) => (
            <motion.div
              key={i}
              initial={{ opacity: 0, y: 14, scale: 0.92 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              transition={{ duration: 0.45, ease: EASE }}
              className={`max-w-[85%] rounded-2xl px-3.5 py-2.5 text-[11.5px] leading-snug ${
                m.from === "you" ? "self-end rounded-br-md bg-[#1b6df0] text-white" : "self-start rounded-bl-md bg-surface/8 text-white/85"
              }`}
            >
              {m.text}
            </motion.div>
          ))}
          {typing && (
            <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="flex gap-1 self-start rounded-2xl rounded-bl-md bg-surface/8 px-4 py-3">
              {[0, 1, 2].map((d) => (
                <motion.span key={d} animate={{ y: [0, -4, 0] }} transition={{ duration: 0.6, repeat: Infinity, delay: d * 0.15 }} className="h-1.5 w-1.5 rounded-full bg-surface/50" />
              ))}
            </motion.div>
          )}
        </div>
      </div>
    </div>
  );
}

/** Live mock-interview call: waveform + scoring ticks up. */
function CallDemo() {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { margin: "-15%" });
  return (
    <div ref={ref} className="mx-auto w-[340px] rounded-3xl border border-white/15 bg-[#120d24] p-6 shadow-[0_40px_120px_rgba(124,58,237,0.3)]">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <motion.div
            animate={inView ? { scale: [1, 1.12, 1] } : {}}
            transition={{ duration: 1.6, repeat: Infinity }}
            className="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-[#7c3aed] to-[#d946ef] text-lg"
          >
            🎙️
          </motion.div>
          <div>
            <p className="text-sm font-semibold text-white">AI Interviewer</p>
            <p className="text-[11px] text-white/50">Technical round · 07:42</p>
          </div>
        </div>
        <span className="flex items-center gap-1.5 rounded-full bg-red-500/15 px-2.5 py-1 text-[10px] font-semibold text-red-400">
          <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-red-400" /> LIVE
        </span>
      </div>
      <div className="mt-6 flex h-16 items-end justify-center gap-[3px]">
        {Array.from({ length: 32 }).map((_, i) => (
          <motion.span
            key={i}
            animate={inView ? { height: ["18%", `${25 + Math.abs(Math.sin(i * 1.7)) * 70}%`, "18%"] } : { height: "18%" }}
            transition={{ duration: 0.9 + (i % 5) * 0.14, repeat: Infinity, ease: "easeInOut", delay: (i % 7) * 0.08 }}
            className="w-[5px] rounded-full bg-gradient-to-t from-[#7c3aed] to-[#d946ef]"
          />
        ))}
      </div>
      <p className="mt-5 rounded-xl bg-surface/5 px-4 py-3 text-[11.5px] italic leading-snug text-white/70">
        “Walk me through how you would optimise a slow API endpoint in production…”
      </p>
      <div className="mt-5 space-y-2.5">
        {[
          { k: "Communication", v: 88 },
          { k: "Technical depth", v: 92 },
          { k: "Confidence", v: 84 },
        ].map((s) => (
          <div key={s.k}>
            <div className="mb-1 flex justify-between text-[10px] text-white/50">
              <span>{s.k}</span><span className="font-semibold text-white/80">{s.v}/100</span>
            </div>
            <div className="h-1 overflow-hidden rounded-full bg-surface/10">
              <motion.div
                initial={{ width: 0 }}
                animate={inView ? { width: `${s.v}%` } : { width: 0 }}
                transition={{ duration: 1.4, ease: EASE, delay: 0.3 }}
                className="h-full rounded-full bg-gradient-to-r from-[#7c3aed] to-[#d946ef]"
              />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

/** Radar sweep discovering live openings. */
function RadarDemo() {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { once: true, margin: "-15%" });
  const jobs = [
    { role: "Data Analyst", co: "Zoho · Chennai", pay: "₹6.2 LPA", d: 0.6 },
    { role: "React Developer", co: "Freshworks · Remote", pay: "₹8.5 LPA", d: 1.4 },
    { role: "Cloud Engineer", co: "HCLTech · Bengaluru", pay: "₹7.1 LPA", d: 2.2 },
  ];
  return (
    <div ref={ref} className="relative mx-auto w-[340px]">
      <div className="relative mx-auto h-[240px] w-[240px]">
        {[0, 1, 2].map((r) => (
          <div key={r} className="absolute rounded-full border border-emerald-400/20" style={{ inset: r * 40 }} />
        ))}
        <div className="absolute inset-0 overflow-hidden rounded-full">
          <motion.div
            animate={{ rotate: 360 }}
            transition={{ duration: 3.2, repeat: Infinity, ease: "linear" }}
            className="absolute inset-0"
            style={{ background: "conic-gradient(from 0deg, rgba(16,185,129,0.5) 0deg, transparent 70deg)" }}
          />
        </div>
        {[{ t: "14%", l: "62%" }, { t: "48%", l: "20%" }, { t: "68%", l: "72%" }].map((p, i) => (
          <motion.span
            key={i}
            animate={{ scale: [1, 1.9, 1], opacity: [1, 0.3, 1] }}
            transition={{ duration: 2, repeat: Infinity, delay: i * 0.65 }}
            className="absolute h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_14px_rgba(16,185,129,0.9)]"
            style={{ top: p.t, left: p.l }}
          />
        ))}
        <div className="absolute left-1/2 top-1/2 h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-surface" />
      </div>
      <div className="mt-6 space-y-2.5">
        {jobs.map((j) => (
          <motion.div
            key={j.role}
            initial={{ opacity: 0, x: 40 }}
            animate={inView ? { opacity: 1, x: 0 } : {}}
            transition={{ delay: j.d, duration: 0.6, ease: EASE }}
            className="flex items-center justify-between rounded-xl border border-white/10 bg-surface/[0.05] px-4 py-3 backdrop-blur"
          >
            <div>
              <p className="text-xs font-semibold text-white">{j.role}</p>
              <p className="text-[10px] text-white/50">{j.co}</p>
            </div>
            <span className="rounded-full bg-emerald-400/15 px-2.5 py-1 text-[10px] font-bold text-emerald-400">{j.pay}</span>
          </motion.div>
        ))}
      </div>
    </div>
  );
}

/* ------------------------------ bento tiles -------------------------------- */

/** PRI ring + daily next-best-action — the AI personal coach. */
function CoachTile() {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { once: true, margin: "-15%" });
  return (
    <div ref={ref} className="flex h-full flex-col justify-between gap-6 sm:flex-row sm:items-center">
      <div>
        <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#1b6df0]">AI personal coach</p>
        <h3 className="font-display mt-3 text-2xl font-bold md:text-3xl">A coach that reads your data — every single day.</h3>
        <p className="mt-3 max-w-sm text-fg/50">Your Placement Readiness Index, what went well, what needs work, and exactly one next best action. No dashboards to decode.</p>
      </div>
      <div className="flex shrink-0 items-center gap-6">
        <div className="relative h-28 w-28">
          <svg viewBox="0 0 100 100" className="h-full w-full -rotate-90">
            <circle cx="50" cy="50" r="42" fill="none" stroke="#e7f1fe" strokeWidth="10" />
            <motion.circle
              cx="50" cy="50" r="42" fill="none" stroke="#1b6df0" strokeWidth="10" strokeLinecap="round"
              strokeDasharray={264}
              initial={{ strokeDashoffset: 264 }}
              animate={inView ? { strokeDashoffset: 264 * (1 - 0.87) } : {}}
              transition={{ duration: 1.8, ease: EASE, delay: 0.3 }}
            />
          </svg>
          <div className="absolute inset-0 grid place-items-center">
            <div className="text-center">
              <p className="font-display text-2xl font-bold"><Counter to={87} /></p>
              <p className="text-[9px] uppercase tracking-widest text-fg/40">PRI</p>
            </div>
          </div>
        </div>
        <motion.div
          initial={{ opacity: 0, x: 24 }}
          animate={inView ? { opacity: 1, x: 0 } : {}}
          transition={{ delay: 1.2, duration: 0.7, ease: EASE }}
          className="w-44 rounded-2xl bg-sky p-4"
        >
          <p className="text-[9px] font-bold uppercase tracking-widest text-[#0e3fa9]">Next best action</p>
          <p className="mt-1.5 text-xs font-medium leading-snug text-ink">Re-attempt the SQL window-functions mock — you&apos;re 1 score away from green.</p>
        </motion.div>
      </div>
    </div>
  );
}

/** ATS CV score gauge. */
function CvTile() {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { once: true, margin: "-15%" });
  return (
    <div ref={ref}>
      <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#7c3aed]">ATS CV generator</p>
      <h3 className="font-display mt-3 text-xl font-bold">Past the machine, past the manager.</h3>
      <div className="mt-5 rounded-2xl bg-[#0a0f1c] p-4 text-white">
        <div className="flex items-center justify-between text-[10px] text-white/50">
          <span>ATS compatibility</span>
          <motion.span
            initial={{ opacity: 0, scale: 0.6 }}
            animate={inView ? { opacity: 1, scale: 1 } : {}}
            transition={{ delay: 1.6, type: "spring", stiffness: 300, damping: 12 }}
            className="rounded-full bg-emerald-400/15 px-2 py-0.5 font-bold text-emerald-400"
          >
            PASS ✓
          </motion.span>
        </div>
        <p className="font-display mt-1 text-3xl font-bold"><Counter to={94} suffix="/100" /></p>
        <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-surface/10">
          <motion.div
            initial={{ width: 0 }}
            animate={inView ? { width: "94%" } : {}}
            transition={{ duration: 1.6, ease: EASE, delay: 0.2 }}
            className="h-full rounded-full bg-gradient-to-r from-[#7c3aed] to-[#1b6df0]"
          />
        </div>
        <p className="mt-3 text-[10px] leading-snug text-white/40">Assembled from your real projects. Brand-free, employer-ready.</p>
      </div>
    </div>
  );
}

/** Live classes + recordings library. */
function ClassesTile() {
  const rows = [
    { t: "SQL Window Functions — live", live: true, p: 0 },
    { t: "Spark Partitioning deep-dive", live: false, p: 100 },
    { t: "Airflow DAG design", live: false, p: 64 },
  ];
  return (
    <div>
      <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#d64545]">Live classes + recordings</p>
      <h3 className="font-display mt-3 text-xl font-bold">Every class live. Every class yours, forever.</h3>
      <div className="mt-5 space-y-2">
        {rows.map((r, i) => (
          <motion.div
            key={r.t}
            initial={{ opacity: 0, y: 14 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.3 + i * 0.15, duration: 0.6, ease: EASE }}
            className="flex items-center gap-3 rounded-xl border border-fg/[0.06] bg-surface px-3.5 py-2.5"
          >
            <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-sky text-[10px]">▶</span>
            <div className="min-w-0 flex-1">
              <p className="truncate text-xs font-semibold">{r.t}</p>
              {r.live ? (
                <p className="flex items-center gap-1 text-[9px] font-bold text-[#d64545]">
                  <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-[#d64545]" /> STREAMING NOW
                </p>
              ) : (
                <div className="mt-1 h-1 overflow-hidden rounded-full bg-fg/[0.07]">
                  <motion.div
                    initial={{ width: 0 }} whileInView={{ width: `${r.p}%` }} viewport={{ once: true }}
                    transition={{ delay: 0.6 + i * 0.15, duration: 1, ease: EASE }}
                    className="h-full rounded-full bg-[#1b6df0]"
                  />
                </div>
              )}
            </div>
          </motion.div>
        ))}
      </div>
    </div>
  );
}

/** Application tracker: a pill that climbs the pipeline. */
function TrackerTile() {
  const stages = ["Applied", "Shortlisted", "Interview", "Offer 🎉"];
  const [stage, setStage] = useState(0);
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { margin: "-15%" });
  useEffect(() => {
    if (!inView) return;
    const id = setInterval(() => setStage((s) => (s + 1) % stages.length), 1600);
    return () => clearInterval(id);
  }, [inView, stages.length]);
  return (
    <div ref={ref}>
      <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#0ba860]">Deployment</p>
      <h3 className="font-display mt-3 text-xl font-bold">Matched, tracked, placed.</h3>
      <div className="mt-5 rounded-2xl border border-fg/[0.06] bg-surface p-4">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-xs font-semibold">Zoho — Data Analyst</p>
            <p className="text-[10px] text-fg/40">Applied via BrowseJobs referral</p>
          </div>
          <motion.span
            key={stage}
            initial={{ opacity: 0, y: 10, scale: 0.8 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            transition={{ type: "spring", stiffness: 400, damping: 18 }}
            className={`rounded-full px-3 py-1 text-[10px] font-bold ${stage === 3 ? "bg-verify-bg text-[#0ba860]" : "bg-sky text-[#0e3fa9]"}`}
          >
            {stages[stage]}
          </motion.span>
        </div>
        <div className="mt-3 flex gap-1.5">
          {stages.map((_, i) => (
            <div key={i} className={`h-1 flex-1 rounded-full transition-colors duration-500 ${i <= stage ? "bg-[#0ba860]" : "bg-fg/[0.08]"}`} />
          ))}
        </div>
        <p className="mt-3 text-[10px] text-fg/40">A placement team works every application until you accept.</p>
      </div>
    </div>
  );
}

/** Coding labs terminal. */
function LabsTile() {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { once: true, margin: "-15%" });
  const [line, setLine] = useState(0);
  useEffect(() => {
    if (!inView) return;
    const id = setInterval(() => setLine((l) => Math.min(l + 1, 3)), 700);
    return () => clearInterval(id);
  }, [inView]);
  const lines = [
    "$ run tests — sql_capstone.py",
    "→ checking joins, windows, CTEs…",
    "✓ 12/12 tests passed (1.8s)",
    "★ Project marked CV-ready",
  ];
  return (
    <div ref={ref}>
      <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#f5a623]">Coding labs</p>
      <h3 className="font-display mt-3 text-xl font-bold">Real code, graded in the browser.</h3>
      <div className="mt-5 rounded-2xl bg-[#0a0f1c] p-4 font-mono text-[11px] leading-relaxed">
        {lines.slice(0, line + 1).map((l, i) => (
          <motion.p
            key={i}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className={i >= 2 ? "text-emerald-400" : "text-white/60"}
          >
            {l}
          </motion.p>
        ))}
        <motion.span animate={{ opacity: [1, 0, 1] }} transition={{ duration: 0.9, repeat: Infinity }} className="inline-block h-3.5 w-1.5 bg-surface/70 align-middle" />
      </div>
    </div>
  );
}

/** Four live tracks. */
function TracksTile() {
  const tracks = [
    { name: "Data Engineering", tools: "Python · SQL · Spark · AWS · Databricks · Airflow" },
    { name: "DevOps & Cloud", tools: "Docker · Kubernetes · Terraform · Jenkins · AWS" },
    { name: "Data Analytics", tools: "Excel · SQL · Python · Power BI · DAX · Pandas" },
    { name: "Python Backend", tools: "APIs, databases, and production Python" },
  ];
  return (
    <div>
      <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#1b6df0]">Programs</p>
      <h3 className="font-display mt-3 text-xl font-bold">Four live tracks. Rebuilt monthly from real interviews.</h3>
      <div className="mt-5 space-y-2">
        {tracks.map((t, i) => (
          <motion.div
            key={t.name}
            initial={{ opacity: 0, x: -20 }}
            whileInView={{ opacity: 1, x: 0 }}
            viewport={{ once: true }}
            transition={{ delay: 0.2 + i * 0.12, duration: 0.6, ease: EASE }}
            whileHover={{ x: 6 }}
            className="flex items-center justify-between rounded-xl border border-fg/[0.06] bg-surface px-4 py-3"
          >
            <div>
              <p className="text-sm font-bold">{t.name}</p>
              <p className="text-[10px] text-fg/40">{t.tools}</p>
            </div>
            <span className="text-[10px] font-bold text-[#0ba860]">● LIVE</span>
          </motion.div>
        ))}
      </div>
      <div className="mt-3 flex flex-wrap gap-1.5">
        {["Agentic AI", "Cyber Security", "ServiceNow"].map((w) => (
          <span key={w} className="rounded-full border border-fg/10 px-2.5 py-1 text-[10px] font-semibold text-fg/40">
            {w} · Waitlist
          </span>
        ))}
      </div>
    </div>
  );
}

/** QR-verified certificate with shine sweep. */
function CertTile() {
  return (
    <div>
      <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#0ba860]">Verified certificates</p>
      <h3 className="font-display mt-3 text-xl font-bold">Scan the QR. Check us.</h3>
      <div className="relative mt-5 overflow-hidden rounded-2xl border border-fg/[0.06] bg-surface p-5">
        <motion.div
          aria-hidden
          animate={{ x: ["-120%", "220%"] }}
          transition={{ duration: 2.4, repeat: Infinity, repeatDelay: 1.6, ease: "easeInOut" }}
          className="absolute inset-y-0 w-1/3 -skew-x-12 bg-gradient-to-r from-transparent via-[#1b6df0]/10 to-transparent"
        />
        <div className="flex items-center gap-4">
          <div className="grid h-16 w-16 shrink-0 grid-cols-4 gap-0.5 rounded-lg border border-fg/10 p-1.5">
            {[1,0,1,1,0,1,0,1,1,0,1,0,1,1,0,1].map((b, i) => (
              <span key={i} className={`rounded-[2px] ${b ? "bg-[#0a1220]" : "bg-transparent"}`} />
            ))}
          </div>
          <div>
            <p className="text-xs font-bold">Certificate #BJ-2026-4817</p>
            <p className="text-[10px] text-fg/40">Data Analytics · Distinction</p>
            <motion.p
              initial={{ opacity: 0, scale: 0.7 }}
              whileInView={{ opacity: 1, scale: 1 }}
              viewport={{ once: true }}
              transition={{ delay: 0.8, type: "spring", stiffness: 300, damping: 14 }}
              className="mt-1.5 inline-block rounded-full bg-verify-bg px-2.5 py-0.5 text-[10px] font-bold text-[#0ba860]"
            >
              ✓ Verified — recruiters can check this
            </motion.p>
          </div>
        </div>
      </div>
    </div>
  );
}

/** Free ladder: three free steps check themselves off. */
function FreeLadderTile() {
  const steps = ["Free counselling + written career report", "Free live masterclass", "Free 7-hour bootcamp"];
  return (
    <div>
      <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#1b6df0]">Start free</p>
      <h3 className="font-display mt-3 text-xl font-bold">Three free steps before a single rupee.</h3>
      <div className="mt-5 space-y-2.5">
        {steps.map((s, i) => (
          <div key={s} className="flex items-center gap-3">
            <motion.span
              initial={{ scale: 0, rotate: -90 }}
              whileInView={{ scale: 1, rotate: 0 }}
              viewport={{ once: true }}
              transition={{ delay: 0.4 + i * 0.35, type: "spring", stiffness: 320, damping: 16 }}
              className="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-[#0ba860] text-[10px] font-bold text-white"
            >
              ✓
            </motion.span>
            <p className="text-sm font-semibold">{s}</p>
          </div>
        ))}
      </div>
      <p className="mt-4 text-[11px] text-fg/40">Then decide. Pay-after-placement paths available on every track.</p>
    </div>
  );
}

/** Mentors + syllabus rebuild loop. */
function MentorsTile() {
  const words = ["LISTEN", "EXTRACT", "REBUILD"] as const;
  const [w, setW] = useState(0);
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { margin: "-15%" });
  useEffect(() => {
    if (!inView) return;
    const id = setInterval(() => setW((x) => (x + 1) % words.length), 1400);
    return () => clearInterval(id);
  }, [inView, words.length]);
  return (
    <div ref={ref}>
      <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#7c3aed]">Humans + the loop</p>
      <h3 className="font-display mt-3 text-xl font-bold">1:1 mentors, and a syllabus that never goes stale.</h3>
      <div className="mt-5 flex items-center gap-3">
        <div className="flex -space-x-2.5">
          {["#1b6df0", "#7c3aed", "#0ba860", "#f5a623"].map((c) => (
            <span key={c} className="grid h-9 w-9 place-items-center rounded-full border-2 border-white text-[10px] font-bold text-white" style={{ background: c }}>
              {c === "#f5a623" ? "+9" : "👤"}
            </span>
          ))}
        </div>
        <p className="text-[11px] text-fg/45">Real engineers review your work weekly</p>
      </div>
      <div className="mt-4 rounded-2xl bg-[#0a0f1c] px-4 py-3">
        <p className="text-[9px] uppercase tracking-widest text-white/35">~50 real interviews monitored daily</p>
        <motion.p
          key={w}
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4, ease: EASE }}
          className="font-mono mt-1 text-lg font-bold tracking-[0.3em] text-[#4d94ff]"
        >
          {words[w]}<span className="text-white/30"> →</span>
        </motion.p>
      </div>
    </div>
  );
}

/** MCQ tests + mastery map: weak topics turning green. */
function MasteryTile() {
  const rows = [
    { t: "SQL & databases", v: 92, c: "#0ba860" },
    { t: "Python", v: 84, c: "#0ba860" },
    { t: "Spark tuning", v: 61, c: "#f5a623" },
  ];
  return (
    <div>
      <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#1b6df0]">MCQ tests & mastery map</p>
      <h3 className="font-display mt-3 text-xl font-bold">Watch weak topics turn green.</h3>
      <div className="mt-5 space-y-3">
        {rows.map((r, i) => (
          <div key={r.t}>
            <div className="mb-1 flex items-center justify-between text-[11px]">
              <span className="font-semibold text-fg/60">{r.t}</span>
              <span className="font-mono font-bold" style={{ color: r.c }}>
                <Counter to={r.v} suffix="%" />
              </span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-fg/[0.07]">
              <motion.div
                initial={{ width: 0 }}
                whileInView={{ width: `${r.v}%` }}
                viewport={{ once: true }}
                transition={{ delay: 0.25 + i * 0.15, duration: 1.1, ease: EASE }}
                className="h-full rounded-full"
                style={{ background: r.c }}
              />
            </div>
          </div>
        ))}
      </div>
      <p className="mt-3 text-[10px] text-fg/40">Every MCQ updates your map — the coach targets the amber.</p>
    </div>
  );
}

/** WhatsApp nudges: reminder bubbles popping in. */
function WhatsAppTile() {
  const msgs = [
    "⏰ SQL windows class in 2 hours — join link inside",
    "🎙️ Mock scored 92/100. Review your gap report",
    "🎉 Your CV cleared ATS — 3 new matches waiting",
  ];
  return (
    <div>
      <p className="text-xs font-bold uppercase tracking-[0.2em] text-[#128c4b]">WhatsApp nudges</p>
      <h3 className="font-display mt-3 text-xl font-bold">The platform that pings you first.</h3>
      <div className="mt-5 space-y-2">
        {msgs.map((m, i) => (
          <motion.div
            key={m}
            initial={{ opacity: 0, y: 16, scale: 0.9 }}
            whileInView={{ opacity: 1, y: 0, scale: 1 }}
            viewport={{ once: true }}
            transition={{ delay: 0.3 + i * 0.25, type: "spring", stiffness: 260, damping: 18 }}
            className="max-w-[95%] rounded-2xl rounded-bl-md bg-verify-bg px-3.5 py-2 text-[11px] leading-snug text-ink shadow-sm"
          >
            {m}
          </motion.div>
        ))}
      </div>
      <p className="mt-3 text-[10px] text-fg/40">Class reminders 12h, 2h & 5 min before — plus every win.</p>
    </div>
  );
}

const BENTO: { span: string; accent: string; el: React.ReactNode }[] = [
  { span: "md:col-span-6", accent: "#1b6df0", el: <CoachTile /> },
  { span: "md:col-span-2", accent: "#7c3aed", el: <CvTile /> },
  { span: "md:col-span-2", accent: "#d64545", el: <ClassesTile /> },
  { span: "md:col-span-2", accent: "#0ba860", el: <TrackerTile /> },
  { span: "md:col-span-2", accent: "#f5a623", el: <LabsTile /> },
  { span: "md:col-span-2", accent: "#1b6df0", el: <MasteryTile /> },
  { span: "md:col-span-2", accent: "#25D366", el: <WhatsAppTile /> },
  { span: "md:col-span-3", accent: "#0ba860", el: <CertTile /> },
  { span: "md:col-span-3", accent: "#0e3fa9", el: <FreeLadderTile /> },
  { span: "md:col-span-3", accent: "#1b6df0", el: <TracksTile /> },
  { span: "md:col-span-3", accent: "#7c3aed", el: <MentorsTile /> },
];

/* ------------------------------ path / journey ----------------------------- */

const PATH_STEPS = [
  { n: "01", free: true, icon: "📞", t: "Free counselling", b: "A written Career Analysis Report — whether you join or not." },
  { n: "02", free: true, icon: "🎓", t: "Free live masterclass", b: "See the method live: how the syllabus is reverse-engineered." },
  { n: "03", free: true, icon: "🐍", t: "Free 7-hour bootcamp", b: "Sit a real teaching day of live Python before you decide." },
  { n: "04", free: false, icon: "💺", t: "Paid batch", b: "Live, instructor-led. ₹30,000 — EMI 3 × ₹10,000." },
  { n: "05", free: false, icon: "🧠", t: "AI coach", b: "PRI, mastery map, and one clear next best action, daily." },
  { n: "06", free: false, icon: "🎙️", t: "Mock interviews", b: "Voice mocks scored on real interview data, with a gap report." },
  { n: "07", free: false, icon: "🏁", t: "Placement", b: "Your profile worked until you accept an offer." },
];

function PathCard({ s, i }: { s: (typeof PATH_STEPS)[number]; i: number }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 40, scale: 0.94 }}
      whileInView={{ opacity: 1, y: 0, scale: 1 }}
      viewport={{ once: true, margin: "-5%" }}
      transition={{ delay: (i % 3) * 0.08, duration: 0.7, ease: EASE }}
      whileHover={{ y: -8 }}
      className={`relative flex w-[300px] shrink-0 flex-col overflow-hidden rounded-3xl border p-7 md:w-[330px] ${
        s.free
          ? "border-[#0ba860]/25 bg-gradient-to-b from-white to-[#e6f7ef]/60 shadow-[0_16px_50px_rgba(11,168,96,0.10)]"
          : "border-fg/[0.07] bg-surface shadow-[0_16px_50px_rgba(10,18,32,0.06)]"
      }`}
    >
      <span aria-hidden className={`pointer-events-none absolute -right-3 -top-7 font-display text-[7rem] font-bold leading-none ${s.free ? "text-[#0ba860]/[0.07]" : "text-[#1b6df0]/[0.06]"}`}>
        {s.n}
      </span>
      <div className="flex items-center justify-between">
        <motion.span
          initial={{ scale: 0, rotate: -20 }}
          whileInView={{ scale: 1, rotate: 0 }}
          viewport={{ once: true }}
          transition={{ delay: 0.2 + (i % 3) * 0.08, type: "spring", stiffness: 280, damping: 14 }}
          className={`grid h-12 w-12 place-items-center rounded-2xl text-xl ${s.free ? "bg-[#0ba860]/10" : "bg-sky"}`}
        >
          {s.icon}
        </motion.span>
        {s.free ? (
          <motion.span
            animate={{ scale: [1, 1.06, 1] }}
            transition={{ duration: 2, repeat: Infinity }}
            className="rounded-full bg-[#0ba860] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white shadow-[0_4px_14px_rgba(11,168,96,0.4)]"
          >
            Free
          </motion.span>
        ) : (
          <span className="rounded-full bg-fg/[0.05] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-fg/40">Step {s.n}</span>
        )}
      </div>
      <h3 className="font-display mt-6 text-2xl font-bold leading-tight">{s.t}</h3>
      <p className="mt-3 flex-1 text-sm leading-relaxed text-fg/50">{s.b}</p>
      <div className={`mt-6 h-1 w-full overflow-hidden rounded-full ${s.free ? "bg-[#0ba860]/15" : "bg-fg/[0.06]"}`}>
        <motion.div
          initial={{ width: 0 }}
          whileInView={{ width: "100%" }}
          viewport={{ once: true }}
          transition={{ delay: 0.4, duration: 0.9, ease: EASE }}
          className={`h-full rounded-full ${s.free ? "bg-[#0ba860]" : "bg-gradient-to-r from-[#1b6df0] to-[#7c3aed]"}`}
        />
      </div>
    </motion.div>
  );
}

function PathSection() {
  const wrapRef = useRef<HTMLDivElement>(null);
  const { scrollYProgress } = useScroll({ target: wrapRef, offset: ["start start", "end end"] });
  const x = useTransform(scrollYProgress, [0.02, 1], ["1%", "-64%"]);
  const barW = useTransform(scrollYProgress, [0, 1], ["4%", "100%"]);

  const Head = (
    <>
      <motion.p
        initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
        className="text-sm font-semibold uppercase tracking-[0.3em] text-[#1b6df0]"
      >
        The path
      </motion.p>
      <motion.h2
        initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
        className="font-display mt-5 text-4xl font-bold leading-[1.06] tracking-tight md:text-6xl"
      >
        Three free steps before <span className="text-[#0ba860]">you pay a rupee.</span>
      </motion.h2>
      <motion.p
        initial={{ opacity: 0 }} whileInView={{ opacity: 1 }} viewport={{ once: true }} transition={{ delay: 0.25, duration: 0.8 }}
        className="mt-5 max-w-lg text-lg text-fg/50"
      >
        Keep scrolling — the page walks the whole journey with you, from your first free call to an accepted offer.
      </motion.p>
    </>
  );

  return (
    <section id="path" className="bg-paper py-24 md:py-0">
      {/* Mobile: vertical stack */}
      <div className="mx-auto max-w-3xl px-6 md:hidden">
        {Head}
        <div className="mt-12 space-y-5">
          {PATH_STEPS.map((s, i) => <PathCard key={s.n} s={s} i={i} />)}
        </div>
      </div>

      {/* Desktop: horizontal scrub — vertical scroll drives the strip sideways */}
      <div ref={wrapRef} className="relative hidden md:block" style={{ height: "340vh" }}>
        <div className="sticky top-0 flex h-screen flex-col justify-center overflow-hidden">
          <div className="mx-auto w-full max-w-6xl px-6">{Head}</div>
          <motion.div style={{ x }} className="mt-14 flex items-stretch gap-6 pl-[max(1.5rem,calc((100vw-72rem)/2+1.5rem))]">
            {PATH_STEPS.map((s, i) => (
              <div key={s.n} className="flex items-center gap-6">
                <PathCard s={s} i={i} />
                {i < PATH_STEPS.length - 1 && (
                  <motion.span
                    animate={{ x: [0, 6, 0], opacity: [0.35, 0.9, 0.35] }}
                    transition={{ duration: 1.6, repeat: Infinity, delay: i * 0.15 }}
                    className="text-2xl text-[#1b6df0]"
                  >
                    ⟶
                  </motion.span>
                )}
              </div>
            ))}
            <div className="flex w-[330px] shrink-0 flex-col justify-center rounded-3xl bg-[#0a0f1c] p-8 text-white shadow-[0_24px_70px_rgba(10,18,32,0.35)]">
              <motion.span
                animate={{ rotate: [0, 12, -8, 0] }}
                transition={{ duration: 2.4, repeat: Infinity, repeatDelay: 1 }}
                className="text-4xl"
              >
                🎉
              </motion.span>
              <p className="font-display mt-5 text-3xl font-bold leading-tight">Offer accepted.</p>
              <p className="mt-3 text-sm text-white/55">The placement fee starts only here — paid from the salary the offer brings.</p>
            </div>
          </motion.div>
          <div className="mx-auto mt-12 h-1.5 w-full max-w-md rounded-full bg-fg/[0.07]">
            <motion.div style={{ width: barW }} className="h-full rounded-full bg-gradient-to-r from-[#0ba860] via-[#1b6df0] to-[#7c3aed]" />
          </div>
        </div>
      </div>
    </section>
  );
}

/* --------------------------------- programs -------------------------------- */

const PROGRAMS = [
  { code: "DE", slug: "data-engineering", name: "Data Engineering", tag: "Pipelines, warehouses, and the modern data stack.", tools: ["Python", "SQL", "Spark", "AWS", "Databricks", "Airflow"], projects: "3 CV-ready projects", accent: "#1b6df0", icon: "🛠️" },
  { code: "DC", slug: "devops-cloud", name: "DevOps & Cloud", tag: "Ship, scale, and run production systems.", tools: ["Docker", "Kubernetes", "Terraform", "Jenkins", "AWS", "Ansible"], projects: "4 CV-ready projects", accent: "#7c3aed", icon: "☁️" },
  { code: "PB", slug: "python-backend", name: "Python Backend", tag: "APIs, databases, and production Python.", tools: ["Python", "FastAPI", "PostgreSQL", "Redis"], projects: "Detailed syllabus arriving", accent: "#f59e0b", icon: "🐍" },
  { code: "DA", slug: "data-analytics", name: "Data Analytics", tag: "SQL, dashboards, and decisions from data.", tools: ["Excel", "SQL", "Python", "Power BI", "DAX", "Pandas"], projects: "5 CV-ready projects", accent: "#0ba860", icon: "📊" },
];

/** Light-mode 3D tilt with a cursor-tracking accent spotlight. */
function TiltLight({ children, accent, className = "" }: { children: React.ReactNode; accent: string; className?: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const rx = useMotionValue(0);
  const ry = useMotionValue(0);
  const srx = useSpring(rx, { stiffness: 160, damping: 18 });
  const sry = useSpring(ry, { stiffness: 160, damping: 18 });
  const [spot, setSpot] = useState({ x: 50, y: 50 });
  return (
    <motion.div
      ref={ref}
      style={{ rotateX: srx, rotateY: sry, transformStyle: "preserve-3d", perspective: 900 }}
      onMouseMove={(e) => {
        const r = ref.current!.getBoundingClientRect();
        const px = (e.clientX - r.left) / r.width;
        const py = (e.clientY - r.top) / r.height;
        ry.set((px - 0.5) * 7);
        rx.set((0.5 - py) * 7);
        setSpot({ x: px * 100, y: py * 100 });
      }}
      onMouseLeave={() => { rx.set(0); ry.set(0); }}
      className={`group relative overflow-hidden ${className}`}
    >
      <div
        className="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
        style={{ background: `radial-gradient(420px circle at ${spot.x}% ${spot.y}%, ${accent}14, transparent 70%)` }}
      />
      {children}
    </motion.div>
  );
}

type HomeDemand = Record<string, { total: number; trend: string }>;

function ProgramsSection() {
  const [demand, setDemand] = useState<HomeDemand | null>(null);
  const [demandLive, setDemandLive] = useState(false);
  useEffect(() => {
    fetch("/api/job-demand")
      .then((r) => r.json())
      .then((d) => {
        const compact: HomeDemand = {};
        for (const [k, v] of Object.entries(d.tracks ?? {})) {
          const t = v as { total: number; trend: string };
          compact[k] = { total: t.total, trend: t.trend };
        }
        setDemand(compact);
        setDemandLive(d.source === "counted");
      })
      .catch(() => setDemand(null));
  }, []);
  return (
    <section id="programs" className="mx-auto max-w-6xl px-6 py-32">
      <motion.p
        initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
        className="text-sm font-semibold uppercase tracking-[0.3em] text-[#1b6df0]"
      >
        Programs
      </motion.p>
      <motion.h2
        initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
        className="font-display mt-5 max-w-2xl text-4xl font-bold leading-[1.06] tracking-tight md:text-6xl"
      >
        Four live tracks. Each one rebuilt monthly.
      </motion.h2>
      <motion.p
        initial={{ opacity: 0 }} whileInView={{ opacity: 1 }} viewport={{ once: true }} transition={{ delay: 0.25, duration: 0.8 }}
        className="mt-5 max-w-xl text-lg text-fg/50"
      >
        Only four — because <strong className="text-ink">demand decides what we teach.</strong> We run
        tracks only where the market is actively hiring.
        {demandLive && <span className="ml-2 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-600">● counted from our job feed</span>}
      </motion.p>
      <div className="mt-14 grid gap-6 md:grid-cols-2">
        {PROGRAMS.map((c, i) => (
          <motion.div
            key={c.code}
            initial={{ opacity: 0, y: 50 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-60px" }}
            transition={{ delay: (i % 2) * 0.12, duration: 0.85, ease: EASE }}
          >
            <a href={`/courses/${c.slug}`} className="block h-full">
              <TiltLight
                accent={c.accent}
                className="flex h-full flex-col rounded-3xl border border-fg/[0.07] bg-surface p-9 shadow-[0_10px_40px_rgba(10,18,32,0.05)] transition-all duration-300 hover:shadow-[0_28px_80px_rgba(10,18,32,0.12)]"
              >
                <span aria-hidden className="pointer-events-none absolute -right-4 -top-10 font-display text-[9rem] font-bold leading-none opacity-[0.05] transition-opacity duration-300 group-hover:opacity-[0.09]" style={{ color: c.accent }}>
                  {c.code}
                </span>
                <div className="relative flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <motion.span
                      initial={{ scale: 0 }} whileInView={{ scale: 1 }} viewport={{ once: true }}
                      transition={{ delay: 0.2 + (i % 2) * 0.12, type: "spring", stiffness: 280, damping: 14 }}
                      className="grid h-12 w-12 place-items-center rounded-2xl text-2xl"
                      style={{ background: `${c.accent}14` }}
                    >
                      {c.icon}
                    </motion.span>
                    <span className="rounded-full px-3 py-1 font-mono text-xs font-bold" style={{ background: `${c.accent}14`, color: c.accent }}>{c.code}</span>
                  </div>
                  <div className="flex flex-col items-end gap-1.5">
                    <span className="flex items-center gap-1.5 text-xs font-bold text-[#0ba860]">
                      <span className="relative flex h-2 w-2">
                        <span className="absolute h-full w-full animate-ping rounded-full bg-[#0ba860] opacity-60" />
                        <span className="relative h-2 w-2 rounded-full bg-[#0ba860]" />
                      </span>
                      Live
                    </span>
                    {demand?.[c.slug] && (
                      <motion.span
                        initial={{ opacity: 0, scale: 0.7 }} animate={{ opacity: 1, scale: 1 }}
                        transition={{ type: "spring", stiffness: 280, damping: 18 }}
                        className="rounded-full px-2.5 py-1 text-[10px] font-bold"
                        style={{ background: `${c.accent}12`, color: c.accent }}
                      >
                        ≈{demand[c.slug].total.toLocaleString("en-IN")} openings ▲ {demand[c.slug].trend}
                      </motion.span>
                    )}
                  </div>
                </div>
                <h3 className="font-display relative mt-6 text-3xl font-bold md:text-4xl">
                  {c.name}
                  <span className="mt-2 block h-1 w-0 rounded-full transition-all duration-500 group-hover:w-24" style={{ background: c.accent }} />
                </h3>
                <p className="relative mt-3 flex-1 text-fg/50">{c.tag}</p>
                <div className="relative mt-6 flex flex-wrap gap-1.5">
                  {c.tools.map((t, ti) => (
                    <motion.span
                      key={t}
                      initial={{ opacity: 0, scale: 0.6 }} whileInView={{ opacity: 1, scale: 1 }} viewport={{ once: true }}
                      transition={{ delay: 0.35 + ti * 0.06, type: "spring", stiffness: 300, damping: 16 }}
                      className="rounded-full border px-2.5 py-1 font-mono text-[11px] transition-colors duration-300"
                      style={{ borderColor: `${c.accent}33`, color: "#5a6b85" }}
                    >
                      {t}
                    </motion.span>
                  ))}
                </div>
                <div className="relative mt-7 flex items-center justify-between border-t border-fg/[0.07] pt-5">
                  <span className="font-mono text-xs text-fg/40">{c.projects}</span>
                  <span className="flex items-center gap-1.5 text-sm font-bold" style={{ color: c.accent }}>
                    View syllabus <span className="transition-transform duration-300 group-hover:translate-x-1.5">→</span>
                  </span>
                </div>
              </TiltLight>
            </a>
          </motion.div>
        ))}
      </div>
      <motion.div
        initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ delay: 0.2, duration: 0.7 }}
        className="mt-6 grid gap-4 sm:grid-cols-3"
      >
        {[
          { name: "Agentic AI", slug: "agentic-ai" },
          { name: "Cyber Security", slug: "cyber-security" },
          { name: "ServiceNow", slug: "servicenow" },
        ].map((w) => (
          <a key={w.slug} href={`/courses/${w.slug}`} className="group flex items-center justify-between rounded-2xl border border-fg/[0.07] bg-paper px-6 py-4 transition-colors hover:border-[#1b6df0]/30">
            <span className="font-semibold text-fg/60">{w.name}</span>
            <span className="text-xs font-bold uppercase tracking-wider text-fg/35 transition-colors group-hover:text-[#1b6df0]">
              Waitlist <span className="inline-block transition-transform group-hover:translate-x-0.5">→</span>
            </span>
          </a>
        ))}
      </motion.div>
    </section>
  );
}

/* ------------------------------ career report ------------------------------ */

function CareerReportSection() {
  return (
    <section id="report" className="bg-[#06070d] px-6 py-32 text-white">
      <div className="mx-auto grid max-w-6xl items-center gap-14 md:grid-cols-2">
        <div>
          <motion.p
            initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
            className="text-sm font-semibold uppercase tracking-[0.25em] text-[#4d94ff]"
          >
            Free career report · unbiased
          </motion.p>
          <motion.h2
            initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
            className="font-display mt-5 text-4xl font-bold leading-[1.06] tracking-tight md:text-5xl"
          >
            Where does the market say <span className="text-[#4d94ff]">you</span> should go next?
          </motion.h2>
          <motion.p
            initial={{ opacity: 0 }} whileInView={{ opacity: 1 }} viewport={{ once: true }} transition={{ delay: 0.25, duration: 0.8 }}
            className="mt-6 max-w-md leading-relaxed text-white/55"
          >
            Paste your resume, register once, and get a genuine direction report: what&apos;s working, what&apos;s missing,
            and which path fits your skills — <strong className="text-white">even when it&apos;s one we don&apos;t teach.</strong>{" "}
            Your resume is analysed and discarded, never stored.
          </motion.p>
          <motion.div
            initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ delay: 0.35, duration: 0.7 }}
            className="mt-8"
          >
            <Magnetic>
              <button
                onClick={() => openLeadModal({ variant: "counselling" })}
                className="rounded-full bg-[#1b6df0] px-8 py-3.5 font-semibold shadow-[0_12px_40px_rgba(27,109,240,0.35)] transition-transform hover:scale-[1.02]"
              >
                Get my free report
              </button>
            </Magnetic>
            <p className="mt-3 text-xs text-white/30">The analysis runs only after registration.</p>
          </motion.div>
        </div>
        <ResumeScanDemo />
      </div>
    </section>
  );
}

/** Resume being scanned live → Career Analysis Report assembling itself. */
function ResumeScanDemo() {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { once: true, margin: "-15%" });
  const [phase, setPhase] = useState(0); // 0 scanning, 1 skills lit, 2 report out
  useEffect(() => {
    if (!inView) return;
    const t1 = setTimeout(() => setPhase(1), 1400);
    const t2 = setTimeout(() => setPhase(2), 2600);
    return () => { clearTimeout(t1); clearTimeout(t2); };
  }, [inView]);
  const skills = [
    { s: "SQL", hit: true }, { s: "Python", hit: true }, { s: "Excel", hit: true },
    { s: "Spark", hit: false }, { s: "AWS", hit: false },
  ];
  return (
    <motion.div
      ref={ref}
      initial={{ opacity: 0, y: 50 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-10%" }}
      transition={{ duration: 1, ease: EASE }}
      className="relative"
    >
      {/* resume being scanned */}
      <div className="relative overflow-hidden rounded-3xl border border-white/10 bg-surface/[0.05] p-6 backdrop-blur">
        <div className="flex items-center justify-between">
          <p className="text-[10px] font-bold uppercase tracking-widest text-white/40">your_resume.pdf</p>
          <motion.p
            key={phase}
            initial={{ opacity: 0 }} animate={{ opacity: 1 }}
            className={`text-[10px] font-bold ${phase === 2 ? "text-emerald-400" : "text-[#4d94ff]"}`}
          >
            {phase === 2 ? "✓ ANALYSED — RESUME DISCARDED" : "◉ SCANNING…"}
          </motion.p>
        </div>
        <div className="mt-4 space-y-2">
          {[100, 82, 94, 68, 88, 40].map((w, i) => (
            <div key={i} className="h-2 rounded-full bg-surface/[0.09]" style={{ width: `${w}%` }} />
          ))}
        </div>
        <div className="mt-4 flex flex-wrap gap-1.5">
          {skills.map((k, i) => (
            <motion.span
              key={k.s}
              animate={phase >= 1 ? { scale: [1, 1.15, 1] } : {}}
              transition={{ delay: i * 0.12, duration: 0.4 }}
              className={`rounded-full px-2.5 py-1 font-mono text-[10px] font-bold transition-colors duration-500 ${
                phase >= 1
                  ? k.hit ? "bg-emerald-400/15 text-emerald-400" : "bg-[#d64545]/15 text-[#ff8f8f]"
                  : "bg-surface/[0.07] text-white/40"
              }`}
            >
              {k.s}{phase >= 1 ? (k.hit ? " ✓" : " ✗") : ""}
            </motion.span>
          ))}
        </div>
        {phase < 2 && (
          <motion.div
            aria-hidden
            animate={{ top: ["-20%", "110%"] }}
            transition={{ duration: 1.6, repeat: Infinity, ease: "easeInOut" }}
            className="absolute inset-x-0 h-14 bg-gradient-to-b from-transparent via-[#1b6df0]/25 to-transparent"
          />
        )}
      </div>

      <motion.div
        initial={{ opacity: 0 }}
        animate={phase === 2 ? { opacity: 1 } : {}}
        className="my-3 text-center text-white/30"
      >
        ↓
      </motion.div>

      {/* the report that comes out */}
      <motion.div
        initial={{ opacity: 0, y: 30, scale: 0.96 }}
        animate={phase === 2 ? { opacity: 1, y: 0, scale: 1 } : {}}
        transition={{ duration: 0.8, ease: EASE }}
        className="rounded-3xl border border-[#4d94ff]/25 bg-surface/[0.05] p-6 shadow-[0_24px_80px_rgba(27,109,240,0.2)] backdrop-blur"
      >
        <div className="flex items-center justify-between">
          <p className="text-[10px] font-bold uppercase tracking-widest text-[#4d94ff]">Career Analysis Report</p>
          <div className="relative h-14 w-14">
            <svg viewBox="0 0 100 100" className="h-full w-full -rotate-90">
              <circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.1)" strokeWidth="11" />
              <motion.circle
                cx="50" cy="50" r="40" fill="none" stroke="#4d94ff" strokeWidth="11" strokeLinecap="round"
                strokeDasharray={251}
                initial={{ strokeDashoffset: 251 }}
                animate={phase === 2 ? { strokeDashoffset: 251 * (1 - 0.87) } : {}}
                transition={{ duration: 1.4, ease: EASE, delay: 0.3 }}
              />
            </svg>
            <span className="absolute inset-0 grid place-items-center text-xs font-bold">87%</span>
          </div>
        </div>
        {[
          { k: "Working for you", v: "SQL joins & window functions", c: "#34d399" },
          { k: "Missing for the market", v: "Cloud fundamentals (AWS/GCP)", c: "#ff8f8f" },
          { k: "Best-fit path", v: "Data Engineering — 87% skill overlap", c: "#4d94ff" },
        ].map((r, i) => (
          <motion.div
            key={r.k}
            initial={{ opacity: 0, x: 24 }}
            animate={phase === 2 ? { opacity: 1, x: 0 } : {}}
            transition={{ delay: 0.45 + i * 0.2, duration: 0.6, ease: EASE }}
            className="mt-3 rounded-2xl bg-surface/[0.05] p-3.5"
          >
            <p className="text-[9px] font-bold uppercase tracking-widest" style={{ color: r.c }}>{r.k}</p>
            <p className="mt-1 text-sm font-medium text-white/85">{r.v}</p>
          </motion.div>
        ))}
      </motion.div>
    </motion.div>
  );
}

/* ---------------------------------- fees ----------------------------------- */

function FeesSection() {
  return (
    <section id="fees" className="mx-auto max-w-6xl px-6 py-32">
      <motion.p
        initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
        className="text-sm font-semibold uppercase tracking-[0.3em] text-[#1b6df0]"
      >
        The fee structure
      </motion.p>
      <motion.h2
        initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
        className="font-display mt-5 max-w-3xl text-4xl font-bold leading-[1.06] tracking-tight md:text-6xl"
      >
        Two fees. Both in writing. <span className="bg-gradient-to-r from-[#1b6df0] to-[#7c3aed] bg-clip-text text-transparent">One only after you&apos;re hired.</span>
      </motion.h2>
      <div className="mt-14 grid gap-6 md:grid-cols-2">
        <motion.div
          initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-60px" }}
          transition={{ duration: 0.8, ease: EASE }}
          className="rounded-3xl border border-fg/[0.07] bg-surface p-9 shadow-[0_10px_40px_rgba(10,18,32,0.05)]"
        >
          <p className="text-xs font-bold uppercase tracking-[0.2em] text-fg/40">01 · Registration</p>
          <p className="font-display mt-4 text-5xl font-bold">₹<Counter to={30000} /></p>
          <p className="mt-3 text-fg/50">Payable only after the free masterclass and the free 7-hour bootcamp.</p>
          <div className="mt-5 flex gap-2">
            <span className="rounded-full bg-sky px-3 py-1.5 text-xs font-bold text-[#0e3fa9]">One payment</span>
            <span className="rounded-full bg-sky px-3 py-1.5 text-xs font-bold text-[#0e3fa9]">EMI · 3 × ₹10,000</span>
          </div>
          <p className="mt-6 rounded-2xl bg-verify-bg px-4 py-3 text-sm font-semibold text-[#0ba860]">
            30-day money-back guarantee — any reason, full refund, in writing.
          </p>
        </motion.div>
        <motion.div
          initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-60px" }}
          transition={{ duration: 0.8, ease: EASE, delay: 0.12 }}
          className="rounded-3xl bg-[#0a0f1c] p-9 text-white"
        >
          <p className="text-xs font-bold uppercase tracking-[0.2em] text-white/40">02 · Placement fee</p>
          <p className="mt-4 text-lg leading-relaxed text-white/75">
            Your first 3 months&apos; CTC, due <strong className="text-white">only after you accept an offer</strong> — paid as 6 monthly
            EMIs from your new salary. Your ₹30,000 registration is adjusted inside it.
          </p>
          <div className="mt-6 rounded-2xl bg-surface/[0.05] p-5">
            <p className="text-[10px] font-bold uppercase tracking-widest text-[#4d94ff]">Worked example · ₹12 LPA offer</p>
            {[
              ["3 months' CTC", "₹3,00,000"],
              ["− registration already paid", "− ₹30,000"],
              ["Placement fee", "₹2,70,000"],
              ["= 6 EMIs of", "₹45,000 / month"],
            ].map(([k, v], i) => (
              <motion.div
                key={k}
                initial={{ opacity: 0, x: 20 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true }}
                transition={{ delay: 0.3 + i * 0.15, duration: 0.5, ease: EASE }}
                className={`flex items-center justify-between py-2 text-sm ${i === 3 ? "border-t border-white/10 pt-3 font-bold text-white" : "text-white/60"}`}
              >
                <span>{k}</span><span className="font-mono">{v}</span>
              </motion.div>
            ))}
          </div>
        </motion.div>
      </div>
      <div className="mt-6 grid gap-6 md:grid-cols-2">
        <motion.div
          initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
          className="rounded-3xl border border-fg/[0.07] bg-paper p-8"
        >
          <p className="font-display text-lg font-bold">Included, forever</p>
          <ul className="mt-4 space-y-2.5">
            {["Live instructor-led classes", "All class recordings, one year", "AI tutor, unlimited", "MCQ tests & mastery tracking", "Your first comprehensive CV", "Base mock-interview quota", "Student support desk"].map((x, i) => (
              <motion.li
                key={x}
                initial={{ opacity: 0, x: -16 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true }}
                transition={{ delay: 0.15 + i * 0.07, duration: 0.5 }}
                className="flex items-center gap-2.5 text-sm text-fg/65"
              >
                <span className="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#0ba860] text-[9px] font-bold text-white">✓</span>
                {x}
              </motion.li>
            ))}
          </ul>
        </motion.div>
        <motion.div
          initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE, delay: 0.1 }}
          className="rounded-3xl border border-fg/[0.07] bg-surface p-8"
        >
          <p className="font-display text-lg font-bold">Optional extras</p>
          <ul className="mt-4 space-y-2.5">
            {[["Extra CV credits", "₹99 / 3"], ["Voice mock interview", "₹249 · ₹599 / 3"], ["Extra 1:1 mentor session", "₹499"], ["Career+ (post-placement)", "₹499 / mo"]].map(([k, v]) => (
              <li key={k} className="flex items-center justify-between text-sm text-fg/65">
                <span>{k}</span><span className="font-mono text-xs font-bold text-fg/45">{v}</span>
              </li>
            ))}
          </ul>
          <p className="mt-5 text-xs leading-relaxed text-fg/40">
            Everything needed for the placement promise is included. Charges apply only to extras beyond generous quotas.
          </p>
        </motion.div>
      </div>
    </section>
  );
}

/* ------------------------- honesty + written promises ----------------------- */

/** Step 1 demo: a Naukri search typing itself, then the posting count. */
function VerifyDemo1() {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { once: true, margin: "-15%" });
  const full = "data engineer, chennai";
  const [len, setLen] = useState(0);
  useEffect(() => {
    if (!inView) return;
    const id = setInterval(() => setLen((l) => (l >= full.length ? (clearInterval(id), l) : l + 1)), 65);
    return () => clearInterval(id);
  }, [inView]);
  const done = len >= full.length;
  return (
    <div ref={ref} className="mt-5 rounded-2xl bg-paper p-4">
      <div className="flex items-center gap-2 rounded-xl border border-fg/10 bg-surface px-3.5 py-2.5">
        <span className="text-fg/30">🔍</span>
        <span className="font-mono text-xs text-fg/70">
          {full.slice(0, len)}
          <motion.span animate={{ opacity: [1, 0, 1] }} transition={{ duration: 0.8, repeat: Infinity }} className="inline-block h-3.5 w-[2px] bg-[#1b6df0] align-middle" />
        </span>
      </div>
      <motion.div
        initial={{ opacity: 0, scale: 0.7 }}
        animate={done ? { opacity: 1, scale: 1 } : {}}
        transition={{ type: "spring", stiffness: 300, damping: 16 }}
        className="mt-2.5 inline-block rounded-full bg-sky px-3 py-1 text-[10px] font-bold text-[#0e3fa9]"
      >
        Filter: Last 7 days ✓
      </motion.div>
      <div className="mt-3 flex items-baseline gap-2">
        <p className="font-display text-3xl font-bold text-[#1b6df0]">{done ? <Counter to={1247} /> : "—"}</p>
        <p className="text-[11px] font-semibold text-fg/45">postings this week. The demand is real — count it yourself.</p>
      </div>
    </div>
  );
}

/** Step 2 demo: skills repeating across 5 JDs, tallied live. */
function VerifyDemo2() {
  const rows = [
    { s: "SQL", n: 5 }, { s: "Spark", n: 4 }, { s: "Airflow", n: 3 }, { s: "AWS", n: 4 },
  ];
  return (
    <div className="mt-5 rounded-2xl bg-paper p-4">
      <p className="text-[9px] font-bold uppercase tracking-widest text-fg/35">Skills repeating across 5 live JDs</p>
      <div className="mt-3 space-y-2">
        {rows.map((r, i) => (
          <div key={r.s} className="flex items-center gap-2.5">
            <span className="w-14 font-mono text-[11px] font-bold text-fg/60">{r.s}</span>
            <div className="h-2 flex-1 overflow-hidden rounded-full bg-fg/[0.07]">
              <motion.div
                initial={{ width: 0 }}
                whileInView={{ width: `${(r.n / 5) * 100}%` }}
                viewport={{ once: true }}
                transition={{ delay: 0.3 + i * 0.15, duration: 0.9, ease: EASE }}
                className="h-full rounded-full bg-gradient-to-r from-[#1b6df0] to-[#7c3aed]"
              />
            </div>
            <span className="w-8 text-right font-mono text-[10px] font-bold text-fg/45">{r.n}/5</span>
          </div>
        ))}
      </div>
      <motion.p
        initial={{ opacity: 0, y: 8 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }}
        transition={{ delay: 1.1, duration: 0.5 }}
        className="mt-3 inline-block rounded-full bg-verify-bg px-3 py-1 text-[10px] font-bold text-[#0ba860]"
      >
        ✓ All four are in our syllabus — check any institute&apos;s the same way
      </motion.p>
    </div>
  );
}

/** Step 3 demo: the three pressure-test questions, answered honestly. */
function VerifyDemo3() {
  const qs = [
    { q: "Every promise in writing?", a: "Yes ✓", good: true },
    { q: "Success rate defined clearly?", a: "Yes ✓", good: true },
    { q: "Do you guarantee a job?", a: "Honestly: no", good: false },
  ];
  return (
    <div className="mt-5 space-y-2 rounded-2xl bg-paper p-4">
      {qs.map((x, i) => (
        <motion.div
          key={x.q}
          initial={{ opacity: 0, x: -16 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true }}
          transition={{ delay: 0.25 + i * 0.25, duration: 0.5, ease: EASE }}
          className="flex items-center justify-between gap-2 rounded-xl border border-fg/[0.06] bg-surface px-3.5 py-2.5"
        >
          <p className="text-[11px] font-semibold text-fg/65">“{x.q}”</p>
          <motion.span
            initial={{ scale: 0 }} whileInView={{ scale: 1 }} viewport={{ once: true }}
            transition={{ delay: 0.5 + i * 0.25, type: "spring", stiffness: 320, damping: 15 }}
            className={`shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold ${x.good ? "bg-verify-bg text-[#0ba860]" : "bg-[color:var(--bj-amber)]/15 text-[#b45309]"}`}
          >
            {x.a}
          </motion.span>
        </motion.div>
      ))}
      <p className="pt-1 text-[10px] text-fg/40">Ask every institute the same three. Compare the answers.</p>
    </div>
  );
}

function HonestySection() {
  const steps = [
    { n: "01", min: "5 min", t: "Count the demand", b: "Open Naukri → search the role + your city → filter “Last 7 days”. If hiring is real, you'll see it in the number.", demo: <VerifyDemo1 /> },
    { n: "02", min: "5 min", t: "Read 5 job descriptions", b: "Note the skills that repeat, then compare them against any institute's syllabus — ours included.", demo: <VerifyDemo2 /> },
    { n: "03", min: "5 min", t: "Pressure-test everyone", b: "Three questions separate honest institutes from the rest. Ask them everywhere.", demo: <VerifyDemo3 /> },
  ];
  return (
    <section id="verify" className="bg-paper px-6 py-32">
      <div className="mx-auto max-w-6xl">
        <motion.p
          initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
          className="text-sm font-semibold uppercase tracking-[0.3em] text-[#1b6df0]"
        >
          The honesty doctrine
        </motion.p>
        <motion.h2
          initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
          className="font-display mt-5 text-4xl font-bold leading-[1.06] tracking-tight md:text-6xl"
        >
          Don&apos;t trust us. <span className="text-[#1b6df0]">Verify us.</span>
        </motion.h2>
        <motion.p
          initial={{ opacity: 0 }} whileInView={{ opacity: 1 }} viewport={{ once: true }} transition={{ delay: 0.25, duration: 0.8 }}
          className="mt-5 max-w-xl text-lg text-fg/50"
        >
          Fifteen minutes on Naukri is enough to check everything we claim. Here&apos;s how.
        </motion.p>
        <div className="mt-12 grid gap-5 md:grid-cols-3">
          {steps.map((s, i) => (
            <motion.div
              key={s.n}
              initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-60px" }}
              transition={{ delay: i * 0.12, duration: 0.8, ease: EASE }}
              className="flex flex-col rounded-3xl bg-surface p-8 shadow-[0_10px_40px_rgba(10,18,32,0.05)]"
            >
              <div className="flex items-center justify-between">
                <span className="font-mono text-sm font-bold text-fg/25">{s.n}</span>
                <span className="rounded-full bg-sky px-2.5 py-1 text-[10px] font-bold text-[#0e3fa9]">{s.min}</span>
              </div>
              <h3 className="font-display mt-4 text-xl font-bold">{s.t}</h3>
              <p className="mt-3 text-sm leading-relaxed text-fg/50">{s.b}</p>
              <div className="flex-1">{s.demo}</div>
            </motion.div>
          ))}
        </div>
        <div className="mt-16 grid gap-5 md:grid-cols-2">
          <motion.div
            initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
            className="rounded-3xl border-2 border-[#0ba860]/25 bg-surface p-8"
          >
            <p className="font-display text-lg font-bold text-[#0ba860]">What we promise in writing</p>
            <ul className="mt-4 space-y-2.5">
              {["Every commitment you get from us is in writing.", "Three full steps are free before you pay a rupee.", "The placement fee is due only after you accept an offer.", "30-day money-back guarantee — any reason, full refund.", "Every counselling call is recorded & AI-monitored."].map((x, i) => (
                <motion.li
                  key={i}
                  initial={{ opacity: 0, x: -16 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true }}
                  transition={{ delay: 0.15 + i * 0.08, duration: 0.5 }}
                  className="flex gap-2.5 text-sm text-fg/65"
                >
                  <span className="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#0ba860] text-[9px] font-bold text-white">✓</span>
                  {x}
                </motion.li>
              ))}
            </ul>
          </motion.div>
          <motion.div
            initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE, delay: 0.1 }}
            className="rounded-3xl border-2 border-[#d64545]/20 bg-surface p-8"
          >
            <p className="font-display text-lg font-bold text-[#d64545]">What we will never tell you</p>
            <ul className="mt-4 space-y-2.5">
              {["“We guarantee you a job.” Nobody honestly can — the market decides.", "“We'll adjust your experience.” We never fabricate employment.", "A fixed salary promise.", "That hiring is certain, or that there's a shortcut."].map((x, i) => (
                <motion.li
                  key={i}
                  initial={{ opacity: 0, x: 16 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true }}
                  transition={{ delay: 0.15 + i * 0.08, duration: 0.5 }}
                  className="flex gap-2.5 text-sm text-fg/65"
                >
                  <span className="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#d64545] text-[9px] font-bold text-white">✕</span>
                  {x}
                </motion.li>
              ))}
            </ul>
          </motion.div>
        </div>
      </div>
    </section>
  );
}

/* ------------------------------ success stories ----------------------------- */

const STORIES = [
  { i: "A", name: "Arjun Mehta", path: "Mechanical engineer, 2 yrs no job → Data Engineer", pkg: "₹11.5 LPA", co: "Nimbus Data", rounds: 4, asked: "SQL window functions — the exact drills", quote: "sir i got the offer!! 😭🔥 4 rounds and honestly the SQL round was exactly the stuff you drilled us on 🙏", course: "data-engineering", courseName: "Data Engineering", accent: "#1b6df0" },
  { i: "S", name: "Sandeep Rao", path: "IT support, stuck 3 yrs → DevOps Engineer", pkg: "₹10.5 LPA", co: "Vertex Cloud", rounds: 4, asked: "Debug a CrashLoopBackOff — live", quote: "krish they literally asked me to debug a crashloopbackoff live 😅 did it in 2 mins because of your labs. offer letter in hand!", course: "devops-cloud", courseName: "DevOps & Cloud", accent: "#7c3aed" },
  { i: "I", name: "Imran Qureshi", path: "Frontend dev wanting backend → Backend Engineer", pkg: "₹10.0 LPA", co: "Larkspur Tech", rounds: 3, asked: "Design a rate limiter", quote: "got it 🎉 they asked me to design a rate limiter and i had already built one in the mock. felt so prepared", course: "python-backend", courseName: "Python Backend", accent: "#f59e0b" },
  { i: "K", name: "Karthik Reddy", path: "Accountant, wanted out → Data Analyst", pkg: "₹8.0 LPA", co: "Meridian Retail", rounds: 3, asked: "SQL — the practiced question types", quote: "the sql round was exactly the type of questions we practiced 🙌 cleared it and got the offer today!", course: "data-analytics", courseName: "Data Analytics", accent: "#0ba860" },
  { i: "F", name: "Fatima Noor", path: "Bank teller, self-taught at night → Analytics Engineer", pkg: "₹9.0 LPA", co: "Cobalt Systems", rounds: 3, asked: "Mocks were harder than the real rounds", quote: "3 rounds done and cleared 🥹 not gonna lie your mock interviews felt harder than the actual one", course: "data-engineering", courseName: "Data Engineering", accent: "#1b6df0" },
  { i: "M", name: "Meghna Iyer", path: "Manual QA tester → Cloud / SRE", pkg: "₹12.0 LPA", co: "Halcyon Labs", rounds: 3, asked: "Terraform — like a class recording", quote: "thank you so much to the whole browsejobs team 🙏 the terraform round felt like a class recording lol", course: "devops-cloud", courseName: "DevOps & Cloud", accent: "#7c3aed" },
];

type QRound = { round: number; name: string; questions: string[] };
type QBank = { tracks: Record<string, QRound[]>; source: string };

/** Auto-cycling round-by-round question bank inside each story card. */
function RoundBank({ rounds, accent, live }: { rounds: QRound[] | null; accent: string; live: boolean }) {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { margin: "-10%" });
  const [active, setActive] = useState(0);
  const [paused, setPaused] = useState(false);
  const n = rounds?.length ?? 0;
  useEffect(() => {
    if (!inView || paused || n < 2) return;
    const id = setInterval(() => setActive((a) => (a + 1) % n), 3200);
    return () => clearInterval(id);
  }, [inView, paused, n]);

  const r = rounds?.[Math.min(active, Math.max(0, n - 1))];
  return (
    <div
      ref={ref}
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      className="mt-4 overflow-hidden rounded-2xl bg-[#0a0f1c] p-4"
    >
      {!rounds || !r ? (
        <div className="space-y-2">
          <div className="h-3 w-2/3 animate-pulse rounded-full bg-surface/10" />
          <div className="h-3 w-1/2 animate-pulse rounded-full bg-surface/10" />
          <p className="pt-1 text-[9px] text-white/30">Loading the question bank…</p>
        </div>
      ) : (
      <>
      <div className="flex items-center justify-between gap-2">
        <p className="text-[8px] font-bold uppercase tracking-widest text-white/40">From the question bank — by round</p>
        <span className={`flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[8px] font-bold uppercase ${live ? "bg-emerald-400/10 text-emerald-400" : "bg-surface/[0.06] text-white/35"}`}>
          <span className={`h-1 w-1 rounded-full ${live ? "animate-pulse bg-emerald-400" : "bg-surface/30"}`} />
          {live ? "kimi-k3" : "sample"}
        </span>
      </div>
      <div className="mt-3 flex gap-1.5">
        {rounds.map((rd, idx) => (
          <button
            key={rd.round}
            onClick={() => { setActive(idx); setPaused(true); }}
            className="flex-1 rounded-lg px-1 py-1.5 text-[9px] font-bold transition-colors duration-300"
            style={idx === active ? { background: accent, color: "#fff" } : { background: "rgba(255,255,255,0.06)", color: "rgba(255,255,255,0.45)" }}
          >
            R{rd.round}
          </button>
        ))}
      </div>
      {!paused && n > 1 && (
        <motion.div
          key={`t-${active}`}
          initial={{ width: "0%" }}
          animate={{ width: "100%" }}
          transition={{ duration: 3.2, ease: "linear" }}
          className="mt-1.5 h-0.5 rounded-full opacity-60"
          style={{ background: accent }}
        />
      )}
      <AnimatePresence mode="wait">
        <motion.div
          key={active}
          initial={{ opacity: 0, y: 14 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -10 }}
          transition={{ duration: 0.35, ease: EASE }}
          className="mt-3"
        >
          <p className="text-[10px] font-bold uppercase tracking-wider" style={{ color: accent }}>
            Round {r.round} · {r.name}
          </p>
          <div className="mt-2 space-y-1.5">
            {r.questions.slice(0, 3).map((q, qi) => (
              <motion.div
                key={q}
                initial={{ opacity: 0, x: 16 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: 0.12 + qi * 0.14, duration: 0.4, ease: EASE }}
                className="flex items-start gap-2"
              >
                <span className="mt-0.5 shrink-0 rounded bg-surface/[0.08] px-1.5 py-0.5 font-mono text-[8px] font-bold text-white/50">Q{qi + 1}</span>
                <p className="text-[11px] leading-snug text-white/80">“{q}”</p>
              </motion.div>
            ))}
          </div>
        </motion.div>
      </AnimatePresence>
      </>
      )}
    </div>
  );
}

function StoryCard({ s, i, bank }: { s: (typeof STORIES)[number]; i: number; bank: QBank | null }) {
  const rounds = bank?.tracks?.[s.course]?.slice(0, s.rounds) ?? null;
  return (
    <motion.div
      initial={{ opacity: 0, y: 50, rotate: i % 2 ? 1.2 : -1.2 }}
      whileInView={{ opacity: 1, y: 0, rotate: 0 }}
      viewport={{ once: true, margin: "-40px" }}
      transition={{ delay: (i % 3) * 0.12, duration: 0.85, ease: EASE }}
      whileHover={{ y: -6, rotate: i % 2 ? -0.6 : 0.6 }}
      className="flex flex-col overflow-hidden rounded-3xl border border-fg/[0.07] bg-surface shadow-[0_10px_40px_rgba(10,18,32,0.05)]"
    >
      {/* chat header */}
      <div className="flex items-start justify-between px-6 pb-4 pt-6" style={{ background: `linear-gradient(135deg, ${s.accent}0d, transparent)` }}>
        <div className="flex items-center gap-3">
          <motion.span
            initial={{ scale: 0 }} whileInView={{ scale: 1 }} viewport={{ once: true }}
            transition={{ delay: 0.2 + (i % 3) * 0.12, type: "spring", stiffness: 300, damping: 14 }}
            className="grid h-11 w-11 place-items-center rounded-full text-sm font-bold text-white shadow-lg"
            style={{ background: `linear-gradient(135deg, ${s.accent}, #0a1220)` }}
          >
            {s.i}
          </motion.span>
          <div>
            <p className="text-sm font-bold">{s.name}</p>
            <p className="text-[10px] leading-tight text-fg/45">{s.path}</p>
          </div>
        </div>
        <span className="rounded-full border border-fg/10 px-2 py-0.5 text-[9px] font-bold uppercase text-fg/35">Sample</span>
      </div>

      <div className="flex flex-1 flex-col px-6 pb-6">
        {/* the question they were asked */}
        <motion.div
          initial={{ opacity: 0, x: -18 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true }}
          transition={{ delay: 0.35 + (i % 3) * 0.12, duration: 0.55, ease: EASE }}
          className="self-start rounded-2xl rounded-bl-md bg-sky px-4 py-2.5"
        >
          <p className="text-[8px] font-bold uppercase tracking-widest text-fg/35">Interview asked</p>
          <p className="mt-0.5 text-[11.5px] font-semibold text-ink">“{s.asked}”</p>
        </motion.div>

        {/* their reply, WhatsApp-style */}
        <motion.div
          initial={{ opacity: 0, x: 18, scale: 0.95 }} whileInView={{ opacity: 1, x: 0, scale: 1 }} viewport={{ once: true }}
          transition={{ delay: 0.6 + (i % 3) * 0.12, duration: 0.55, ease: EASE }}
          className="mt-3 self-end rounded-2xl rounded-br-md bg-verify-bg px-4 py-2.5"
        >
          <p className="text-xs leading-relaxed text-ink">{s.quote}</p>
          <p className="mt-1 flex items-center justify-end gap-1 text-[9px] text-fg/35">
            6:42 pm
            <motion.span
              initial={{ color: "rgba(0,0,0,0.3)" }}
              whileInView={{ color: "#34b7f1" }}
              viewport={{ once: true }}
              transition={{ delay: 1.3 + (i % 3) * 0.12 }}
            >
              ✓✓
            </motion.span>
          </p>
        </motion.div>

        {/* outcome stats */}
        <div className="mt-5 grid grid-cols-3 gap-2 text-center">
          {[["Package", s.pkg], ["Joined", s.co], ["Cleared", `${s.rounds} rounds`]].map(([k, v]) => (
            <div key={k} className="rounded-xl bg-paper px-1 py-2">
              <p className="text-[8px] font-bold uppercase tracking-wider text-fg/35">{k}</p>
              <p className="truncate text-[11px] font-bold">{v}</p>
            </div>
          ))}
        </div>
        <div className="mt-2.5 flex gap-1.5">
          {Array.from({ length: s.rounds }).map((_, r) => (
            <motion.span
              key={r}
              initial={{ scaleX: 0 }} whileInView={{ scaleX: 1 }} viewport={{ once: true }}
              transition={{ delay: 0.8 + r * 0.2, duration: 0.4, ease: EASE }}
              className="h-1 flex-1 origin-left rounded-full"
              style={{ background: s.accent }}
            />
          ))}
        </div>
        <p className="mt-1 text-right text-[9px] font-bold" style={{ color: s.accent }}>all rounds cleared ✓</p>

        {/* round-by-round question bank, generated by the platform LLM */}
        <RoundBank rounds={rounds} accent={s.accent} live={bank?.source === "kimi-k3"} />
      </div>
    </motion.div>
  );
}

function StoriesSection() {
  const [bank, setBank] = useState<QBank | null>(null);
  useEffect(() => {
    fetch("/api/interview-questions")
      .then((r) => r.json())
      .then(setBank)
      .catch(() => setBank(null));
  }, []);
  return (
    <section id="stories" className="mx-auto max-w-6xl px-6 py-32">
      <motion.p
        initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
        className="text-sm font-semibold uppercase tracking-[0.3em] text-[#1b6df0]"
      >
        Success stories
      </motion.p>
      <motion.h2
        initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
        className="font-display mt-5 text-4xl font-bold leading-[1.06] tracking-tight md:text-6xl"
      >
        From stuck <span className="text-[#0ba860]">→ hired.</span>
      </motion.h2>
      <motion.p
        initial={{ opacity: 0 }} whileInView={{ opacity: 1 }} viewport={{ once: true }} transition={{ delay: 0.25, duration: 0.8 }}
        className="mt-5 max-w-xl text-lg text-fg/50"
      >
        Where learners started, the package and company they landed, the rounds they cleared —
        and the exact question the interview threw at them.
      </motion.p>
      <div className="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        {STORIES.map((s, i) => (
          <StoryCard key={s.name} s={s} i={i} bank={bank} />
        ))}
      </div>
      <p className="mt-8 text-center text-xs text-fg/35">
        Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome.
        Cards marked “Sample” are illustrative — real, consented student stories replace them as they&apos;re published.
      </p>
    </section>
  );
}

/* ---------------------------------- reviews --------------------------------- */

const REVIEWS = [
  { n: "Chakravarthi Kuraba", src: "Google", q: "The course was well-structured, covering Python, SQL, AWS, and PySpark with hands-on projects. Thanks to their training and mentorship, I secured a job as a data engineer." },
  { n: "KR Sampritha", src: "Verified testimonial", q: "Your way of teaching with real-life examples made everything so easy to understand. You're not just a trainer, but a true mentor!" },
  { n: "supritha selvapandiyan", src: "Google", q: "His dedication and expertise have been instrumental in my career growth, giving me the skills and confidence needed to succeed in the job market." },
  { n: "Anish Lakumarapu", src: "Verified testimonial", q: "Instead of just theory, I got to work on actual data pipelines and industry-relevant case studies." },
  { n: "Niloy Roy", src: "Google", q: "I was quite tensed after graduation about which path to take — Krish sir's way of teaching is literally fabulous. Strongly recommend for your career-building phase." },
  { n: "Saket Vaibhav", src: "Verified testimonial", q: "Sincere thanks to BrowseJobs for their incredible support in helping me find a job." },
  { n: "Neha kumari", src: "Google", q: "This platform has truly been the best place to learn and grow, with unwavering support and guidance throughout the journey." },
  { n: "Arbaz Khan", src: "Verified testimonial", q: "Comprehensive, well-structured, and tailored. My tutor was always available to clear any doubts." },
  { n: "Anjali Yokshi", src: "Google", q: "The way Krish sir motivated and supported the students gave everyone confidence to get a job. I have never seen such a mentor." },
  { n: "Venkateswarlu", src: "JustDial", q: "Training is excellent, practical and easy to understand. The team also helps with interview preparation and job opportunities. Highly recommend." },
];

function ReviewsSection() {
  return (
    <section id="reviews" className="border-y border-fg/[0.06] bg-paper py-28">
      <div className="mx-auto max-w-6xl px-6">
        <motion.p
          initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
          className="text-sm font-semibold uppercase tracking-[0.3em] text-[#1b6df0]"
        >
          In their words
        </motion.p>
        <motion.h2
          initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
          className="font-display mt-5 text-4xl font-bold leading-[1.06] tracking-tight md:text-6xl"
        >
          The people who did it, on the record.
        </motion.h2>
        <motion.p
          initial={{ opacity: 0 }} whileInView={{ opacity: 1 }} viewport={{ once: true }} transition={{ delay: 0.25, duration: 0.8 }}
          className="mt-5 max-w-xl text-lg text-fg/50"
        >
          Real reviews from Google, JustDial and verified students — never a fabricated one.
        </motion.p>
      </div>
      {[0, 1].map((row) => (
        <div key={row} className="mt-8 overflow-hidden [mask-image:linear-gradient(90deg,transparent,black_8%,black_92%,transparent)]">
          <div className="flex w-max gap-5 pr-5" style={{ animation: `${row ? "v3marqueeR" : "v3marquee"} ${row ? 60 : 52}s linear infinite` }}>
            {[...REVIEWS.slice(row * 5, row * 5 + 5), ...REVIEWS.slice(row * 5, row * 5 + 5)].map((r, i) => (
              <div key={i} className="w-[380px] shrink-0 rounded-3xl border border-fg/[0.06] bg-surface p-6 shadow-[0_8px_30px_rgba(10,18,32,0.04)]">
                <div className="flex items-center justify-between">
                  <p className="text-sm font-bold">{r.n}</p>
                  <span className="rounded-full bg-sky px-2.5 py-0.5 text-[9px] font-bold text-[#0e3fa9]">{r.src}</span>
                </div>
                <p className="mt-1 text-xs text-[#f5a623]">★★★★★</p>
                <p className="mt-3 text-[13px] leading-relaxed text-fg/60">“{r.q}”</p>
              </div>
            ))}
          </div>
        </div>
      ))}
    </section>
  );
}

/* ------------------------------- market intel ------------------------------- */

const ENGINE_SKILLS = ["SQL window functions", "Spark partitioning", "Airflow DAG design", "dbt incremental models", "Kafka consumer groups", "Docker multi-stage builds", "Kubernetes probes", "Terraform state", "Python decorators", "Slowly changing dimensions", "CI/CD rollbacks", "Power BI DAX measures", "Data modelling trade-offs", "REST vs. event-driven"];

type MarketSignal = { skill: string; change: string; note: string };
type MarketData = { rising: MarketSignal[]; cooling: MarketSignal[]; source: string; updated: string; sample?: number };

function SignalBar({ s, i, up }: { s: MarketSignal; i: number; up: boolean }) {
  const pct = Math.min(100, Math.abs(parseInt(s.change.replace(/[^0-9-]/g, ""), 10) || 20) * 1.5);
  const color = up ? "#34d399" : "#fbbf24";
  return (
    <motion.div
      initial={{ opacity: 0, x: up ? -24 : 24 }}
      whileInView={{ opacity: 1, x: 0 }}
      viewport={{ once: true, margin: "-30px" }}
      transition={{ delay: i * 0.12, duration: 0.6, ease: EASE }}
      className="rounded-2xl bg-surface/[0.04] p-4"
    >
      <div className="flex items-center justify-between gap-3">
        <p className="text-sm font-bold text-white/90">{s.skill}</p>
        <motion.span
          initial={{ scale: 0 }} whileInView={{ scale: 1 }} viewport={{ once: true }}
          transition={{ delay: 0.3 + i * 0.12, type: "spring", stiffness: 300, damping: 15 }}
          className="rounded-full px-2.5 py-1 font-mono text-xs font-bold"
          style={{ background: `${color}1f`, color }}
        >
          {up ? "▲" : "▼"} {s.change}
        </motion.span>
      </div>
      <div className="mt-2.5 h-1.5 overflow-hidden rounded-full bg-surface/[0.07]">
        <motion.div
          initial={{ width: 0 }}
          whileInView={{ width: `${pct}%` }}
          viewport={{ once: true }}
          transition={{ delay: 0.25 + i * 0.12, duration: 1.1, ease: EASE }}
          className="h-full rounded-full"
          style={{ background: `linear-gradient(90deg, ${color}66, ${color})` }}
        />
      </div>
      <p className="mt-2 text-[11px] text-white/40">{s.note}</p>
    </motion.div>
  );
}

function IntelSection() {
  const [data, setData] = useState<MarketData | null>(null);
  useEffect(() => {
    fetch("/api/market-signals")
      .then((r) => r.json())
      .then(setData)
      .catch(() => setData(null));
  }, []);

  return (
    <section id="signals" className="overflow-clip bg-[#06070d] py-28 text-white">
      <div className="mx-auto max-w-6xl px-6">
        <motion.p
          initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
          className="text-sm font-semibold uppercase tracking-[0.25em] text-[#4d94ff]"
        >
          The intelligence board
        </motion.p>
        <div className="flex flex-wrap items-end justify-between gap-4">
          <motion.h2
            initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
            className="font-display mt-5 max-w-2xl text-4xl font-bold leading-[1.06] tracking-tight md:text-6xl"
          >
            Live market signal, <span className="text-[#4d94ff]">read by the engine.</span>
          </motion.h2>
          <motion.span
            initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.4 }}
            className={`flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-[10px] font-bold uppercase tracking-wider ${
              data?.source === "counted" ? "border-emerald-400/30 bg-emerald-400/10 text-emerald-400" : "border-white/15 bg-surface/[0.04] text-white/40"
            }`}
          >
            <span className={`h-1.5 w-1.5 rounded-full ${data?.source === "counted" ? "animate-pulse bg-emerald-400" : "bg-surface/30"}`} />
            {/* The badge may only claim what the figures are. "Counted" means
                measured over postings we ingested; anything else is bundled
                illustrative content and says so. */}
            {data
              ? data.source === "counted"
                ? `Counted from ${data.sample?.toLocaleString("en-IN") ?? ""} postings we track`
                : "Illustrative sample — not measured"
              : "Loading…"}
          </motion.span>
        </div>

        <div className="mt-12 grid gap-8 lg:grid-cols-2">
          <div>
            <p className="mb-4 flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-emerald-400">
              <span className="text-base">📈</span> Heating up — learn these now
            </p>
            <div className="space-y-3">
              {data
                ? data.rising.map((s, i) => <SignalBar key={s.skill} s={s} i={i} up />)
                : Array.from({ length: 5 }).map((_, i) => (
                    <div key={i} className="h-[92px] animate-pulse rounded-2xl bg-surface/[0.04]" />
                  ))}
            </div>
          </div>
          <div>
            <p className="mb-4 flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-amber-400">
              <span className="text-base">📉</span> Cooling down — don&apos;t bet a career on these
            </p>
            <div className="space-y-3">
              {data
                ? data.cooling.map((s, i) => <SignalBar key={s.skill} s={s} i={i} up={false} />)
                : Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} className="h-[92px] animate-pulse rounded-2xl bg-surface/[0.04]" />
                  ))}
            </div>
          </div>
        </div>
        {/* The caveat has to match the source. It previously said the deltas
            were "estimated by the platform's own LLM", which was at least
            honest about being estimates; now that they are counted, saying so
            is both more accurate and a stronger claim. */}
        <p className="mt-6 text-[11px] text-white/30">
          {data?.source === "counted"
            ? `Share of postings mentioning each skill, over the last ${30} days against the 30 before it, counted across the roles we ingest. Movement in what employers ask for — not a forecast.`
            : "Illustrative sample, not measured. Shown when we do not yet have enough postings to count a trend honestly."}
          {data?.updated ? ` Updated ${new Date(data.updated).toLocaleDateString("en-IN", { day: "numeric", month: "short" })}.` : ""}
        </p>
        <Disclaimer className="mt-3 !text-white/30" />

        <div className="mt-14 grid gap-5 md:grid-cols-2">
          <motion.div
            initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-60px" }}
            transition={{ duration: 0.8, ease: EASE }}
            className="rounded-3xl border border-white/10 bg-surface/[0.04] p-7"
          >
            <div className="flex items-center justify-between">
              <p className="text-[10px] font-bold uppercase tracking-widest text-white/40">Funding radar · from our research</p>
              <span className="flex items-center gap-1.5 rounded-full bg-emerald-400/10 px-2.5 py-1 text-[9px] font-bold text-emerald-400">
                <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400" /> REFRESHED DAILY
              </span>
            </div>
            <p className="mt-4 text-sm font-bold">Fintech · Late-stage raise · Bengaluru</p>
            <p className="mt-2 text-sm leading-relaxed text-white/55">
              Our engine expects the hiring wave in ~3 months, led by Data Engineering and Backend roles.
            </p>
            <div className="mt-4 flex gap-1.5">
              {["SQL", "Spark", "Python"].map((s) => (
                <span key={s} className="rounded-full bg-[#1b6df0]/15 px-2.5 py-1 font-mono text-[10px] font-bold text-[#4d94ff]">{s}</span>
              ))}
            </div>
          </motion.div>
          <motion.div
            initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-60px" }}
            transition={{ duration: 0.8, ease: EASE, delay: 0.12 }}
            className="rounded-3xl border border-white/10 bg-surface/[0.04] p-7"
          >
            <p className="text-[10px] font-bold uppercase tracking-widest text-white/40">GCC expansion · New centre announced</p>
            <p className="mt-4 text-sm font-bold">Pune · hiring wave expected in ~5 months</p>
            <p className="mt-2 text-sm leading-relaxed text-white/55">
              Compiled from public funding &amp; hiring news. Signals, not guarantees — every named company links to its public source.
            </p>
            <span className="mt-4 inline-block rounded-full border border-white/15 px-2.5 py-1 text-[9px] font-bold uppercase text-white/40">Curated sample</span>
          </motion.div>
        </div>
      </div>
      <p className="mt-14 px-6 text-center text-[10px] font-bold uppercase tracking-[0.3em] text-white/30">From the engine — what interviews ask this month</p>
      <div className="mt-5 overflow-hidden [mask-image:linear-gradient(90deg,transparent,black_10%,black_90%,transparent)]">
        <div className="flex w-max gap-3 pr-3" style={{ animation: "v3marquee 40s linear infinite" }}>
          {[...ENGINE_SKILLS, ...ENGINE_SKILLS].map((s, i) => (
            <span key={i} className="whitespace-nowrap rounded-full border border-white/10 bg-surface/[0.04] px-4 py-2 font-mono text-xs text-white/55">
              {s}
            </span>
          ))}
        </div>
      </div>
    </section>
  );
}

/* ------------------------------- scene shell ------------------------------- */

function Scene({ kicker, title, body, accent, demo, flip = false }: {
  kicker: string; title: React.ReactNode; body: string; accent: string; demo: React.ReactNode; flip?: boolean;
}) {
  return (
    <section className="relative overflow-clip bg-[#06070d] py-28 text-white md:py-40">
      <div aria-hidden className="absolute left-1/2 top-0 h-px w-2/3 -translate-x-1/2 bg-gradient-to-r from-transparent via-white/20 to-transparent" />
      <div aria-hidden className="absolute left-1/2 top-1/2 h-[500px] w-[700px] -translate-x-1/2 -translate-y-1/2 rounded-full blur-[160px]" style={{ background: accent, opacity: 0.14 }} />
      <div className={`relative mx-auto flex max-w-6xl flex-col items-center gap-16 px-6 md:gap-24 ${flip ? "md:flex-row-reverse" : "md:flex-row"}`}>
        <div className="flex-1">
          <motion.p
            initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-100px" }}
            transition={{ duration: 0.7, ease: EASE }}
            className="text-sm font-semibold uppercase tracking-[0.25em]" style={{ color: accent }}
          >
            {kicker}
          </motion.p>
          <motion.h2
            initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-100px" }}
            transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
            className="font-display mt-5 text-4xl font-bold leading-[1.06] tracking-tight md:text-6xl"
          >
            {title}
          </motion.h2>
          <motion.p
            initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-100px" }}
            transition={{ duration: 0.8, ease: EASE, delay: 0.2 }}
            className="mt-6 max-w-md text-lg leading-relaxed text-white/55"
          >
            {body}
          </motion.p>
        </div>
        <motion.div
          initial={{ opacity: 0, y: 60, scale: 0.92 }} whileInView={{ opacity: 1, y: 0, scale: 1 }} viewport={{ once: true, margin: "-10%" }}
          transition={{ duration: 1, ease: EASE }}
          className="flex-1"
        >
          {demo}
        </motion.div>
      </div>
    </section>
  );
}

/* ---------------------------------- page ----------------------------------- */

export default function V3Landing() {
  useLenis();

  const heroRef = useRef<HTMLDivElement>(null);
  const { scrollYProgress } = useScroll({ target: heroRef, offset: ["start start", "end start"] });
  const titleScale = useTransform(scrollYProgress, [0, 1], [1, 0.9]);
  const titleFade = useTransform(scrollYProgress, [0, 0.6], [1, 0]);

  const dashRef = useRef<HTMLDivElement>(null);
  const { scrollYProgress: dashScroll } = useScroll({ target: dashRef, offset: ["start end", "center center"] });
  const dashRotate = useTransform(dashScroll, [0, 1], [24, 0]);
  const dashY = useTransform(dashScroll, [0, 1], [80, 0]);

  return (
    <main className="bg-surface font-body text-ink antialiased selection:bg-[#1b6df0] selection:text-white">
      <style dangerouslySetInnerHTML={{ __html: `
        @keyframes v3marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
        @keyframes v3marqueeR { from { transform: translateX(-50%); } to { transform: translateX(0); } }
      `}} />
      {/* -------------------------------- nav -------------------------------- */}
      <SiteNav />

      {/* ------------------------------- hero -------------------------------- */}
      <section ref={heroRef} className="flex min-h-[92vh] flex-col items-center justify-center px-6 pt-28 text-center">
        <motion.div style={{ scale: titleScale, opacity: titleFade }}>
          <motion.p
            initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.2, duration: 1 }}
            className="text-sm font-semibold uppercase tracking-[0.3em] text-[#1b6df0]"
          >
            BrowseJobs · The Career Engine
          </motion.p>
          <h1 className="font-display mx-auto mt-6 max-w-5xl text-[13vw] font-bold leading-[0.98] tracking-[-0.04em] md:text-[7.5rem]">
            {["Six", "months", "from"].map((w, i) => (
              <Fragment key={w}>
                <span className="inline-block overflow-hidden align-bottom">
                  <motion.span
                    initial={{ y: "105%" }} animate={{ y: "0%" }}
                    transition={{ delay: 0.25 + i * 0.09, duration: 1, ease: EASE }}
                    className="inline-block pr-[0.22em]"
                  >
                    {w}
                  </motion.span>
                </span>
              </Fragment>
            ))}
            <br />
            <span className="inline-block overflow-hidden align-bottom">
              <motion.span
                initial={{ y: "105%" }} animate={{ y: "0%" }} transition={{ delay: 0.55, duration: 1, ease: EASE }}
                className="inline-block bg-gradient-to-r from-[#1b6df0] via-[#4d94ff] to-[#7c3aed] bg-clip-text pr-[0.05em] text-transparent"
              >
                “still searching”
              </motion.span>
            </span>
            <span className="inline-block overflow-hidden align-bottom">
              <motion.span initial={{ y: "105%" }} animate={{ y: "0%" }} transition={{ delay: 0.65, duration: 1, ease: EASE }} className="inline-block">
                &nbsp;to&nbsp;signed.
              </motion.span>
            </span>
          </h1>
          <motion.p
            initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 1, duration: 0.9, ease: EASE }}
            className="mx-auto mt-8 max-w-xl text-xl leading-relaxed text-fg/50"
          >
            An AI tutor that never sleeps. Mock interviews that call your phone.
            A job radar that hunts while you study. <strong className="font-semibold text-fg">You bring the effort — we bring the offer.</strong>
          </motion.p>
          <motion.div
            initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 1.2, duration: 0.9, ease: EASE }}
            className="mt-10 flex items-center justify-center gap-5"
          >
            <Magnetic>
              <button
                onClick={() => openLeadModal({ variant: "masterclass" })}
                className="rounded-full bg-[#1b6df0] px-9 py-4 text-lg font-semibold text-white shadow-[0_12px_40px_rgba(27,109,240,0.35)] transition-all hover:scale-[1.02] hover:shadow-[0_16px_50px_rgba(27,109,240,0.5)]"
              >
                Book the free masterclass
              </button>
            </Magnetic>
            <a href="#tutor" className="group text-lg font-semibold text-[#1b6df0]">
              Watch it work <span className="inline-block transition-transform group-hover:translate-x-1">→</span>
            </a>
          </motion.div>
          <motion.p initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 1.5, duration: 1 }} className="mt-5 text-sm text-fg/35">
            Free seat · No card · 20,000+ students before you
          </motion.p>
        </motion.div>
      </section>

      {/* --------------------- dashboard tilts up into view ------------------- */}
      <section ref={dashRef} className="px-6 pb-32" style={{ perspective: 1200 }}>
        <motion.div
          style={{ rotateX: dashRotate, y: dashY, transformStyle: "preserve-3d" }}
          className="mx-auto max-w-4xl overflow-hidden rounded-2xl border border-fg/10 bg-[#0a0f1c] shadow-[0_60px_140px_rgba(10,18,32,0.35)]"
        >
          <div className="flex items-center gap-1.5 border-b border-white/5 px-4 py-3">
            {["#ff5f57", "#febc2e", "#28c840"].map((c) => <span key={c} className="h-2.5 w-2.5 rounded-full" style={{ background: c }} />)}
            <span className="ml-3 text-[10px] text-white/30">app.browsejobs.in — your placement dashboard</span>
          </div>
          <div className="grid grid-cols-[150px_1fr] text-white">
            <div className="space-y-1 border-r border-white/5 p-4 text-[11px] text-white/45">
              {["📊 Overview", "🧠 AI Tutor", "🎙️ Mock lab", "📡 Job radar", "🧾 Certificates"].map((m, i) => (
                <p key={m} className={`rounded-lg px-3 py-2 ${i === 0 ? "bg-[#1b6df0]/20 font-semibold text-white" : ""}`}>{m}</p>
              ))}
            </div>
            <div className="p-6">
              <p className="text-xs text-white/40">Placement readiness</p>
              <p className="font-display mt-1 text-4xl font-bold">
                <Counter to={87} suffix="%" />
              </p>
              <div className="mt-5 flex h-24 items-end gap-2">
                {[34, 48, 41, 62, 58, 74, 69, 87].map((h, i) => (
                  <motion.div
                    key={i}
                    initial={{ height: 0 }}
                    whileInView={{ height: `${h}%` }}
                    viewport={{ once: true }}
                    transition={{ delay: 0.4 + i * 0.08, duration: 0.8, ease: EASE }}
                    className="flex-1 rounded-t-md bg-gradient-to-t from-[#1b6df0]/40 to-[#1b6df0]"
                  />
                ))}
              </div>
              <div className="mt-4 flex gap-3 text-[10px] text-white/40">
                <span className="rounded-full bg-emerald-400/10 px-2.5 py-1 font-semibold text-emerald-400">▲ 12 mock interviews cleared</span>
                <span className="rounded-full bg-surface/5 px-2.5 py-1">3 interview calls this week</span>
              </div>
            </div>
          </div>
        </motion.div>
      </section>

      {/* ------------------------------ manifesto ----------------------------- */}
      <ManifestoSection />

      {/* ---------------------------- the path (7 steps) ---------------------- */}
      <PathSection />

      {/* ------------------------------- scenes ------------------------------- */}
      <div id="tutor">
        <Scene
          kicker="01 · AI Tutor"
          title={<>Stuck at 2am?<br />So is your tutor. <span className="text-[#4d94ff]">Awake.</span></>}
          body="Every doubt answered in seconds, in your context, from your syllabus. Students ask it 400+ questions a course — because it never judges, never sleeps, never says 'ask me tomorrow'."
          accent="#1b6df0"
          demo={<ChatDemo />}
        />
        <Scene
          flip
          kicker="02 · Voice Mock Lab"
          title={<>The interviewer that <span className="text-[#c084fc]">calls you first.</span></>}
          body="A voice AI rings your phone, runs a real technical round, and scores every answer — communication, depth, confidence. By interview #12, the real thing feels like a rerun."
          accent="#7c3aed"
          demo={<CallDemo />}
        />
        <Scene
          kicker="03 · Job Radar"
          title={<>Openings hunted <span className="text-emerald-400">while you sleep.</span></>}
          body="Live roles from LinkedIn and Naukri, matched to your skill graph and refreshed daily. When you're ready, the interviews are already waiting."
          accent="#10b981"
          demo={<RadarDemo />}
        />
      </div>

      {/* ---------------------------- everything bento ------------------------ */}
      <section className="bg-paper px-6 py-32 md:py-40">
        <div className="mx-auto max-w-6xl">
          <motion.p
            initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
            className="text-sm font-semibold uppercase tracking-[0.3em] text-[#1b6df0]"
          >
            Everything included
          </motion.p>
          <motion.h2
            initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
            className="font-display mt-5 max-w-3xl text-4xl font-bold leading-[1.06] tracking-tight md:text-6xl"
          >
            One fee. The whole machine.
          </motion.h2>
          <motion.p
            initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.2 }}
            className="mt-5 max-w-xl text-lg text-fg/50"
          >
            No add-on pricing for the things that get you hired. Every tool below ships with every track.
          </motion.p>
          <div className="mt-14 grid gap-4 md:grid-cols-6 md:gap-5">
            {BENTO.map((b, i) => (
              <motion.div
                key={i}
                initial={{ opacity: 0, y: 40, scale: 0.97 }}
                whileInView={{ opacity: 1, y: 0, scale: 1 }}
                viewport={{ once: true, margin: "-40px" }}
                transition={{ delay: (i % 3) * 0.1, duration: 0.8, ease: EASE }}
                whileHover={{ y: -5 }}
                className={`relative overflow-hidden rounded-3xl border p-6 shadow-[0_10px_40px_rgba(10,18,32,0.04)] transition-shadow md:p-7 ${b.span}`}
                style={{
                  background: `linear-gradient(165deg, ${b.accent}0d, #ffffff 55%)`,
                  borderColor: `${b.accent}2b`,
                }}
              >
                <span
                  aria-hidden
                  className="pointer-events-none absolute inset-x-0 top-0 h-1"
                  style={{ background: `linear-gradient(90deg, ${b.accent}, ${b.accent}00 70%)` }}
                />
                {b.el}
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* ------------------------------- programs ----------------------------- */}
      <ProgramsSection />

      {/* ------------------------------ open roles ---------------------------- */}
      <HomeJobs />

      {/* ---------------------------- career report --------------------------- */}
      <CareerReportSection />

      {/* --------------------------------- fees ------------------------------- */}
      <FeesSection />

      {/* ---------------------- honesty + written promises -------------------- */}
      <HonestySection />

      {/* ---------------------------- success stories ------------------------- */}
      <StoriesSection />

      {/* -------------------------------- reviews ----------------------------- */}
      <ReviewsSection />

      {/* ----------------------------- market intel --------------------------- */}
      <IntelSection />

      {/* ------------------------------- numbers ------------------------------ */}
      <section className="mx-auto max-w-6xl px-6 py-32 text-center md:py-44">
        <motion.p
          initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8 }}
          className="text-sm font-semibold uppercase tracking-[0.3em] text-[#1b6df0]"
        >
          The receipts
        </motion.p>
        <div className="mt-14 grid gap-14 md:grid-cols-3">
          {[
            { v: 20000, s: "+", label: "students trained", d: 0 },
            { v: 98, s: "%", label: "success rate among students who attend interviews", d: 0.15 },
            { v: 42, s: " LPA", label: "highest package (₹)", d: 0.3 },
          ].map((st) => (
            <motion.div
              key={st.label}
              initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-80px" }}
              transition={{ delay: st.d, duration: 0.9, ease: EASE }}
            >
              <p className="font-display text-7xl font-bold tracking-tight md:text-8xl">
                <Counter to={st.v} suffix={st.s} />
              </p>
              <p className="mt-3 text-lg text-fg/45">{st.label}</p>
            </motion.div>
          ))}
        </div>
        <motion.p
          initial={{ opacity: 0 }} whileInView={{ opacity: 1 }} viewport={{ once: true }} transition={{ delay: 0.5, duration: 1 }}
          className="mx-auto mt-16 max-w-xl text-fg/40"
        >
          Every number above is backed by verifiable student records —{" "}
          <span className="font-semibold text-[#0ba860]">scan any certificate&apos;s QR to check us.</span>
        </motion.p>
      </section>

      {/* -------------------------------- final ------------------------------- */}
      <section className="relative overflow-clip bg-[#06070d] px-6 py-36 text-center text-white md:py-48">
        <div aria-hidden className="absolute left-1/2 top-1/2 h-[500px] w-[800px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#1b6df0]/20 blur-[160px]" />
        <motion.h2
          initial={{ opacity: 0, y: 50 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-100px" }}
          transition={{ duration: 1, ease: EASE }}
          className="font-display relative mx-auto max-w-4xl text-5xl font-bold leading-[1.04] tracking-[-0.03em] md:text-8xl"
        >
          The offer letter
          <br />
          <span className="bg-gradient-to-r from-[#4d94ff] via-[#1b6df0] to-[#7c3aed] bg-clip-text text-transparent">
            already has a date.
          </span>
        </motion.h2>
        <motion.p
          initial={{ opacity: 0 }} whileInView={{ opacity: 1 }} viewport={{ once: true }} transition={{ delay: 0.3, duration: 1 }}
          className="relative mx-auto mt-8 max-w-md text-lg text-white/50"
        >
          One free masterclass decides if this is for you. 45 minutes. Zero pressure.
        </motion.p>
        <motion.div
          initial={{ opacity: 0, scale: 0.9 }} whileInView={{ opacity: 1, scale: 1 }} viewport={{ once: true }}
          transition={{ delay: 0.45, duration: 0.8, ease: EASE }}
          className="relative mt-12"
        >
          <Magnetic>
            <button
              onClick={() => openLeadModal({ variant: "masterclass" })}
              className="rounded-full bg-surface px-12 py-5 text-xl font-bold text-ink transition-all hover:scale-[1.03] hover:shadow-[0_0_60px_rgba(255,255,255,0.35)]"
            >
              Claim my free seat →
            </button>
          </Magnetic>
        </motion.div>
        <p className="relative mt-6 text-sm text-white/30">Next batch closes Sunday midnight</p>
      </section>

      <Footer />
      <LeadModal />
      <StickyCta />
    </main>
  );
}
