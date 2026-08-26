"use client";

/**
 * Course page keynote template (preview) — conversion-first redesign of the
 * course detail pages. Minimal text, interactive visuals: an explorable
 * module journey, the platform's smart systems demoing themselves on THIS
 * course's content, the live LLM interview intel for the track, and a
 * free-first closing offer. Rendered at /v3/courses/[slug]; the live
 * /courses/[slug] pages are untouched until this is approved.
 */

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import Lenis from "lenis";
import {
  AnimatePresence,
  animate,
  motion,
  useInView,
  useMotionValue,
  useSpring,
} from "framer-motion";
import { Wordmark } from "@/components/brand/Wordmark";
import { Footer } from "@/components/landing/Footer";
import { LeadModal } from "@/components/landing/LeadModal";
import { StickyCta } from "@/components/landing/StickyCta";
import { openLeadModal } from "@/components/landing/leadModalBus";
import type { CourseDetail } from "@/content/courses";
import { SyllabusDownload } from "@/components/courses/SyllabusDownload";

const EASE = [0.16, 1, 0.3, 1] as const;

/* ------------------------- per-course personality -------------------------- */

const COURSE_EXTRAS: Record<string, { accent: string; icon: string; tutorQ: string; tutorA: string; mockQ: string }> = {
  "data-engineering": {
    accent: "#1b6df0",
    icon: "🛠️",
    tutorQ: "Why is my Spark join spilling to disk? 😩",
    tutorA: "Your build side is too big for the shuffle partitions. Salt the skewed keys or bump spark.sql.shuffle.partitions — here's the exact config for your job 👇",
    mockQ: "Walk me through partitioning vs bucketing — and when you'd use each in production.",
  },
  "devops-cloud": {
    accent: "#7c3aed",
    icon: "☁️",
    tutorQ: "My pod keeps restarting with CrashLoopBackOff 😵",
    tutorA: "Check `kubectl logs --previous` first — your liveness probe fires before the app boots. Raise initialDelaySeconds and it'll stabilise 👇",
    mockQ: "Your deploy just took production down. Walk me through your rollback, step by step.",
  },
  "data-analytics": {
    accent: "#0ba860",
    icon: "📊",
    tutorQ: "My DAX measure shows wrong totals in the matrix 😖",
    tutorA: "Classic filter-context trap. Wrap it in SUMX over VALUES so the total row iterates rows instead of summing the cache 👇",
    mockQ: "Sales dropped 12% last quarter. Show me how you'd investigate it with data.",
  },
  "python-backend": {
    accent: "#f59e0b",
    icon: "🐍",
    tutorQ: "My list endpoint fires 200 queries 😤",
    tutorA: "Classic N+1 — you're looping over a related field. Add select_related for the FK and prefetch_related for the M2M; here it is on your serializer 👇",
    mockQ: "Design a rate limiter for a public API. What do you store, and where?",
  },
  "ai-engineering": {
    accent: "#e11d8f",
    icon: "🧠",
    tutorQ: "My RAG keeps citing the wrong section 😩",
    tutorA: "Retrieval is fine — your chunks split mid-clause, so the citation loses its heading. Chunk on structure, not size; here's the splitter 👇",
    mockQ: "Your agent has write access to a customer database. How do you stop a prompt injection from using it?",
  },
};

/* --------------------------------- helpers --------------------------------- */

function useLenis() {
  useEffect(() => {
    const lenis = new Lenis({ lerp: 0.09 });
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

function Counter({ to, suffix = "" }: { to: number; suffix?: string }) {
  const ref = useRef<HTMLSpanElement>(null);
  const inView = useInView(ref, { once: true, margin: "-60px" });
  useEffect(() => {
    if (!inView || !ref.current) return;
    const c = animate(0, to, {
      duration: 1.6, ease: EASE,
      onUpdate: (v) => { ref.current!.textContent = Math.round(v).toLocaleString("en-IN") + suffix; },
    });
    return () => c.stop();
  }, [inView, to, suffix]);
  return <span ref={ref}>0{suffix}</span>;
}

/* ------------------------------ journey explorer ---------------------------- */

function JourneyExplorer({ course, accent }: { course: CourseDetail; accent: string }) {
  const [active, setActive] = useState(0);
  const [touched, setTouched] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { margin: "-15%" });
  const n = course.modules.length;

  // Auto-tour the modules until the visitor takes over.
  useEffect(() => {
    if (!inView || touched || n < 2) return;
    const id = setInterval(() => setActive((a) => (a + 1) % n), 2800);
    return () => clearInterval(id);
  }, [inView, touched, n]);

  const m = course.modules[active];
  if (!m) return null;

  return (
    <div ref={ref}>
      {/* module rail */}
      <div className="relative">
        <div className="absolute left-0 right-0 top-1/2 hidden h-px -translate-y-1/2 bg-fg/[0.08] md:block" />
        <div className="relative flex flex-wrap justify-center gap-2 md:justify-between md:gap-0">
          {course.modules.map((mod, i) => (
            <button
              key={mod.title}
              onClick={() => { setActive(i); setTouched(true); }}
              className="group flex flex-col items-center gap-1.5 px-1"
              aria-label={mod.title}
            >
              <motion.span
                animate={i === active ? { scale: 1.25 } : { scale: 1 }}
                transition={{ type: "spring", stiffness: 300, damping: 18 }}
                className="grid h-9 w-9 place-items-center rounded-full border-2 text-[10px] font-bold transition-colors md:h-10 md:w-10"
                style={
                  i <= active
                    ? { background: accent, borderColor: accent, color: "#fff" }
                    : { background: "#fff", borderColor: "rgba(0,0,0,0.12)", color: "rgba(0,0,0,0.4)" }
                }
              >
                M{i + 1}
              </motion.span>
              <span className={`hidden max-w-[80px] text-center text-[8px] font-semibold leading-tight lg:block ${i === active ? "text-fg/70" : "text-fg/30"}`}>
                {mod.title.split(" ").slice(0, 2).join(" ")}
              </span>
            </button>
          ))}
        </div>
      </div>

      {/* active module panel */}
      <AnimatePresence mode="wait">
        <motion.div
          key={active}
          initial={{ opacity: 0, y: 24, scale: 0.98 }}
          animate={{ opacity: 1, y: 0, scale: 1 }}
          exit={{ opacity: 0, y: -14, scale: 0.99 }}
          transition={{ duration: 0.4, ease: EASE }}
          className="relative mt-8 overflow-hidden rounded-3xl border p-7 md:p-9"
          style={{ background: `linear-gradient(160deg, ${accent}0d, #ffffff 55%)`, borderColor: `${accent}2b` }}
        >
          <span aria-hidden className="pointer-events-none absolute -right-4 -top-8 font-display text-[7rem] font-bold leading-none" style={{ color: `${accent}0f` }}>
            {String(active + 1).padStart(2, "0")}
          </span>
          <div className="flex flex-wrap items-center gap-3">
            <span className="rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white" style={{ background: accent }}>
              Module {active + 1} of {n}
            </span>
            <span className="rounded-full bg-fg/[0.05] px-3 py-1 text-[10px] font-bold text-fg/45">
              {m.topics.length} topics
            </span>
          </div>
          <h3 className="font-display relative mt-4 text-2xl font-bold md:text-3xl">{m.title}</h3>
          <p className="relative mt-2 max-w-xl text-fg/55">{m.hook}</p>
          <div className="relative mt-5 flex flex-wrap gap-1.5">
            {m.topics.slice(0, 10).map((t, ti) => (
              <motion.span
                key={t}
                initial={{ opacity: 0, scale: 0.7 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ delay: 0.1 + ti * 0.045, type: "spring", stiffness: 300, damping: 18 }}
                className="rounded-full border bg-surface px-3 py-1.5 text-[11px] font-medium text-fg/60"
                style={{ borderColor: `${accent}30` }}
              >
                {t}
              </motion.span>
            ))}
            {m.topics.length > 10 && (
              <span className="rounded-full px-3 py-1.5 text-[11px] font-bold" style={{ color: accent }}>
                +{m.topics.length - 10} more in class
              </span>
            )}
          </div>
        </motion.div>
      </AnimatePresence>

      {/* progress dots */}
      <div className="mt-5 flex justify-center gap-1.5">
        {course.modules.map((_, i) => (
          <span key={i} className="h-1 rounded-full transition-all duration-500" style={{ width: i === active ? 26 : 10, background: i === active ? accent : "rgba(0,0,0,0.12)" }} />
        ))}
      </div>
    </div>
  );
}

/* ------------------------- smart systems, per course ------------------------ */

function TutorDemo({ q, a, accent }: { q: string; a: string; accent: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { once: true, margin: "-15%" });
  const [step, setStep] = useState(0);
  useEffect(() => {
    if (!inView) return;
    const t1 = setTimeout(() => setStep(1), 700);
    const t2 = setTimeout(() => setStep(2), 1900);
    return () => { clearTimeout(t1); clearTimeout(t2); };
  }, [inView]);
  return (
    <div ref={ref} className="mt-4 space-y-2">
      {step >= 1 && (
        <motion.p initial={{ opacity: 0, x: 16, scale: 0.9 }} animate={{ opacity: 1, x: 0, scale: 1 }} className="ml-auto max-w-[90%] rounded-2xl rounded-br-md bg-[#1b6df0] px-3.5 py-2.5 text-[11px] leading-snug text-white">
          {q}
        </motion.p>
      )}
      {step >= 2 ? (
        <motion.p initial={{ opacity: 0, x: -16, scale: 0.9 }} animate={{ opacity: 1, x: 0, scale: 1 }} className="max-w-[92%] rounded-2xl rounded-bl-md bg-surface/10 px-3.5 py-2.5 text-[11px] leading-snug text-white/85">
          {a}
        </motion.p>
      ) : step === 1 ? (
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="flex w-14 gap-1 rounded-2xl rounded-bl-md bg-surface/10 px-3.5 py-3">
          {[0, 1, 2].map((d) => (
            <motion.span key={d} animate={{ y: [0, -3, 0] }} transition={{ duration: 0.6, repeat: Infinity, delay: d * 0.15 }} className="h-1.5 w-1.5 rounded-full bg-surface/50" />
          ))}
        </motion.div>
      ) : null}
      <p className="pt-1 text-[10px]" style={{ color: accent }}>answers in seconds · unlimited · included</p>
    </div>
  );
}

function MockDemo({ q }: { q: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { margin: "-15%" });
  return (
    <div ref={ref} className="mt-4">
      <div className="flex h-12 items-end justify-center gap-[3px]">
        {Array.from({ length: 24 }).map((_, i) => (
          <motion.span
            key={i}
            animate={inView ? { height: ["20%", `${28 + Math.abs(Math.sin(i * 1.9)) * 68}%`, "20%"] } : { height: "20%" }}
            transition={{ duration: 0.9 + (i % 5) * 0.13, repeat: Infinity, ease: "easeInOut", delay: (i % 7) * 0.07 }}
            className="w-[4px] rounded-full bg-gradient-to-t from-[#7c3aed] to-[#d946ef]"
          />
        ))}
      </div>
      <p className="mt-3 rounded-xl bg-surface/5 px-3.5 py-2.5 text-[11px] italic leading-snug text-white/70">“{q}”</p>
      <p className="pt-2 text-[10px] text-[#c084fc]">voice AI calls you · scored on real data</p>
    </div>
  );
}

function MasteryDemo({ course, accent }: { course: CourseDetail; accent: string }) {
  const rows = course.modules.slice(0, 3).map((m, i) => ({ t: m.title, v: [88, 72, 54][i] ?? 60 }));
  return (
    <div className="mt-4 space-y-2.5">
      {rows.map((r, i) => (
        <div key={r.t}>
          <div className="mb-1 flex justify-between text-[10px] text-white/50">
            <span className="truncate pr-2">{r.t}</span>
            <span className="font-mono font-bold" style={{ color: r.v > 70 ? "#34d399" : "#fbbf24" }}>{r.v}%</span>
          </div>
          <div className="h-1 overflow-hidden rounded-full bg-surface/10">
            <motion.div
              initial={{ width: 0 }}
              whileInView={{ width: `${r.v}%` }}
              viewport={{ once: true }}
              transition={{ delay: 0.3 + i * 0.15, duration: 1, ease: EASE }}
              className="h-full rounded-full"
              style={{ background: r.v > 70 ? "#34d399" : "#fbbf24" }}
            />
          </div>
        </div>
      ))}
      <p className="pt-1 text-[10px]" style={{ color: accent }}>your PRI updates after every MCQ &amp; mock</p>
    </div>
  );
}

function RadarDemo({ course }: { course: CourseDetail }) {
  return (
    <div className="mt-4">
      <div className="relative mx-auto h-[110px] w-[110px]">
        {[0, 1].map((r) => (
          <div key={r} className="absolute rounded-full border border-emerald-400/25" style={{ inset: r * 22 }} />
        ))}
        <div className="absolute inset-0 overflow-hidden rounded-full">
          <motion.div
            animate={{ rotate: 360 }}
            transition={{ duration: 3, repeat: Infinity, ease: "linear" }}
            className="absolute inset-0"
            style={{ background: "conic-gradient(from 0deg, rgba(16,185,129,0.5) 0deg, transparent 70deg)" }}
          />
        </div>
        <span className="absolute left-1/2 top-1/2 h-1.5 w-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-surface" />
      </div>
      <div className="mt-3 flex flex-wrap justify-center gap-1.5">
        {course.roles.slice(0, 3).map((role, i) => (
          <motion.span
            key={role}
            initial={{ opacity: 0, scale: 0.7 }}
            whileInView={{ opacity: 1, scale: 1 }}
            viewport={{ once: true }}
            transition={{ delay: 0.4 + i * 0.2, type: "spring", stiffness: 280, damping: 16 }}
            className="rounded-full bg-emerald-400/12 px-2.5 py-1 text-[10px] font-bold text-emerald-400"
          >
            {role}
          </motion.span>
        ))}
      </div>
      <p className="pt-2 text-center text-[10px] text-emerald-400/80">live roles matched to this track, daily</p>
    </div>
  );
}

/* ------------------------------ market demand ------------------------------- */

type TrackDemand = {
  total: number;
  trend: string;
  portals: { name: string; share: number }[];
  roles: { role: string; count: number }[];
  cities: { city: string; share: number }[];
};

const PORTAL_COLORS: Record<string, string> = { Naukri: "#4a90e2", LinkedIn: "#0a66c2", Others: "#8a97ab" };

function MarketDemandSection({ slug, accent, courseName }: { slug: string; accent: string; courseName: string }) {
  const [demand, setDemand] = useState<TrackDemand | null>(null);
  const [loaded, setLoaded] = useState(false);
  const [live, setLive] = useState(false);
  useEffect(() => {
    fetch("/api/job-demand")
      .then((r) => r.json())
      .then((d) => { setDemand(d.tracks?.[slug] ?? null); setLive(d.source === "kimi-k3"); })
      .catch(() => setDemand(null))
      .finally(() => setLoaded(true));
  }, [slug]);

  // Loaded but this track has no demand data: hide the section rather than
  // leaving a skeleton pulsing forever, and never borrow another track's numbers.
  if (loaded && !demand) return null;

  if (!demand) {
    return (
      <section className="mx-auto max-w-6xl px-6 py-24 md:py-32">
        <div className="h-40 animate-pulse rounded-3xl bg-fg/[0.04]" />
      </section>
    );
  }

  const maxRole = Math.max(...demand.roles.map((r) => r.count));

  return (
    <section className="mx-auto max-w-6xl px-6 py-24 md:py-32">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-sm font-semibold uppercase tracking-[0.3em]" style={{ color: accent }}>
            Market demand · why this track exists
          </p>
          <h2 className="font-display mt-4 max-w-2xl text-4xl font-bold leading-[1.06] tracking-tight md:text-5xl">
            The market is hiring for this. <span style={{ color: accent }}>We checked.</span>
          </h2>
        </div>
        <span className={`flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[9px] font-bold uppercase tracking-wider ${live ? "border-emerald-500/30 bg-emerald-500/10 text-emerald-600" : "border-fg/10 bg-fg/[0.03] text-fg/40"}`}>
          <span className={`h-1.5 w-1.5 rounded-full ${live ? "animate-pulse bg-emerald-500" : "bg-fg/25"}`} />
          {live ? "AI market analysis · kimi-k3" : "Curated estimate"}
        </span>
      </div>

      <div className="mt-12 grid gap-5 lg:grid-cols-3">
        {/* headline count */}
        <motion.div
          initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-40px" }}
          transition={{ duration: 0.8, ease: EASE }}
          className="rounded-3xl p-8 text-white"
          style={{ background: `linear-gradient(150deg, #0a0f1c, ${accent}44)` }}
        >
          <p className="text-[10px] font-bold uppercase tracking-widest text-white/45">Active openings · India · last 30 days (est.)</p>
          <p className="font-display mt-3 text-6xl font-bold">
            ≈<Counter to={demand.total} />
          </p>
          <span className="mt-3 inline-flex items-center gap-1 rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-bold text-emerald-400">
            ▲ {demand.trend} vs last month
          </span>
          <div className="mt-6 space-y-2.5">
            {demand.portals.map((p, i) => (
              <div key={p.name}>
                <div className="mb-1 flex justify-between text-[10px] text-white/55">
                  <span className="font-bold">{p.name}</span><span>{p.share}%</span>
                </div>
                <div className="h-1.5 overflow-hidden rounded-full bg-surface/10">
                  <motion.div
                    initial={{ width: 0 }} whileInView={{ width: `${p.share}%` }} viewport={{ once: true }}
                    transition={{ delay: 0.4 + i * 0.15, duration: 1, ease: EASE }}
                    className="h-full rounded-full"
                    style={{ background: PORTAL_COLORS[p.name] ?? "#8a97ab" }}
                  />
                </div>
              </div>
            ))}
          </div>
        </motion.div>

        {/* roles + cities */}
        <motion.div
          initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-40px" }}
          transition={{ delay: 0.1, duration: 0.8, ease: EASE }}
          className="rounded-3xl border border-fg/[0.06] bg-surface p-8 shadow-[0_10px_40px_rgba(10,18,32,0.05)]"
        >
          <p className="text-[10px] font-bold uppercase tracking-widest text-fg/40">Openings by role</p>
          <div className="mt-4 space-y-3">
            {demand.roles.map((r, i) => (
              <div key={r.role}>
                <div className="mb-1 flex justify-between text-[11px]">
                  <span className="font-bold text-fg/70">{r.role}</span>
                  <span className="font-mono text-fg/45">≈{r.count.toLocaleString("en-IN")}</span>
                </div>
                <div className="h-2 overflow-hidden rounded-full bg-fg/[0.06]">
                  <motion.div
                    initial={{ width: 0 }} whileInView={{ width: `${(r.count / maxRole) * 100}%` }} viewport={{ once: true }}
                    transition={{ delay: 0.3 + i * 0.12, duration: 1, ease: EASE }}
                    className="h-full rounded-full"
                    style={{ background: `linear-gradient(90deg, ${accent}88, ${accent})` }}
                  />
                </div>
              </div>
            ))}
          </div>
          <p className="mt-5 text-[10px] font-bold uppercase tracking-widest text-fg/40">Where the jobs are</p>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {demand.cities.map((c, i) => (
              <motion.span
                key={c.city}
                initial={{ opacity: 0, scale: 0.7 }} whileInView={{ opacity: 1, scale: 1 }} viewport={{ once: true }}
                transition={{ delay: 0.5 + i * 0.1, type: "spring", stiffness: 300, damping: 18 }}
                className="rounded-full border px-3 py-1 text-[11px] font-semibold text-fg/60"
                style={{ borderColor: `${accent}30` }}
              >
                {c.city} · {c.share}%
              </motion.span>
            ))}
          </div>
        </motion.div>

        {/* the doctrine */}
        <motion.div
          initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-40px" }}
          transition={{ delay: 0.2, duration: 0.8, ease: EASE }}
          className="flex flex-col rounded-3xl border p-8"
          style={{ background: `linear-gradient(160deg, ${accent}0d, #ffffff 55%)`, borderColor: `${accent}2b` }}
        >
          <span className="text-3xl">🧭</span>
          <h3 className="font-display mt-4 text-xl font-bold">Demand decides our modules.</h3>
          <p className="mt-3 flex-1 text-sm leading-relaxed text-fg/55">
            We don&apos;t run courses in every domain — only where the market is actually hiring.
            That&apos;s why there are just five live tracks, and why the {courseName} syllabus is
            rebuilt monthly from what this demand is asking for.
          </p>
          <a
            href="https://www.naukri.com/"
            target="_blank"
            rel="noopener noreferrer"
            className="mt-5 text-sm font-bold hover:underline"
            style={{ color: accent }}
          >
            Don&apos;t trust us — verify on Naukri in 5 minutes →
          </a>
        </motion.div>
      </div>
      <p className="mt-5 text-[11px] text-fg/35">
        Estimates from BrowseJobs&apos; AI market analysis of Indian tech hiring — directional signals, not exact counts. No scraping: we cite public portals and show you how to count them yourself.
      </p>
    </section>
  );
}

/* -------------------------------- reviews ----------------------------------- */

const COURSE_REVIEWS: Record<string, { n: string; src: string; q: string }[]> = {
  "data-engineering": [
    { n: "Chakravarthi Kuraba", src: "Google", q: "The course was well-structured, covering Python, SQL, AWS, and PySpark with hands-on projects. Thanks to their training and mentorship, I secured a job as a data engineer." },
    { n: "Niloy Roy", src: "Google", q: "I chose 'Data Engineer' which is impressive. Krish sir's way of teaching is literally fabulous. Strongly recommend for your career-building phase." },
    { n: "Anish Lakumarapu", src: "Verified testimonial", q: "Instead of just theory, I got to work on actual data pipelines and industry-relevant case studies." },
  ],
  "devops-cloud": [
    { n: "Venkateswarlu", src: "JustDial", q: "Training is excellent, practical and easy to understand. The team also helps with interview preparation and job opportunities. Highly recommend." },
    { n: "Arbaz Khan", src: "Verified testimonial", q: "Comprehensive, well-structured, and tailored. My tutor was always available to clear any doubts." },
    { n: "Saket Vaibhav", src: "Verified testimonial", q: "Sincere thanks to BrowseJobs for their incredible support in helping me find a job." },
  ],
  "data-analytics": [
    { n: "supritha selvapandiyan", src: "Google", q: "His dedication and expertise have been instrumental in my career growth, giving me the skills and confidence needed to succeed in the job market." },
    { n: "Neha kumari", src: "Google", q: "This platform has truly been the best place to learn and grow, with unwavering support and guidance throughout the journey." },
    { n: "KR Sampritha", src: "Verified testimonial", q: "Your way of teaching with real-life examples made everything so easy to understand. You're not just a trainer, but a true mentor!" },
  ],
  "python-backend": [
    { n: "Anjali Yokshi", src: "Google", q: "The way Krish sir motivated and supported the students gave everyone confidence to get a job. I have never seen such a mentor." },
    { n: "Arbaz Khan", src: "Verified testimonial", q: "Comprehensive, well-structured, and tailored. My tutor was always available to clear any doubts." },
    { n: "Saket Vaibhav", src: "Verified testimonial", q: "Sincere thanks to BrowseJobs for their incredible support in helping me find a job." },
  ],
};

function CourseReviews({ slug, accent }: { slug: string; accent: string }) {
  // Never borrow another track's testimonials — these are real, named students.
  // A track with no reviews of its own simply doesn't render the section.
  const reviews = COURSE_REVIEWS[slug] ?? [];
  if (reviews.length === 0) return null;
  return (
    <section className="mx-auto max-w-6xl px-6 py-24 md:py-28">
      <motion.p
        initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
        className="text-sm font-semibold uppercase tracking-[0.3em]" style={{ color: accent }}
      >
        In their words
      </motion.p>
      <motion.h2
        initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
        className="font-display mt-4 max-w-2xl text-4xl font-bold leading-[1.06] tracking-tight md:text-5xl"
      >
        Students who sat where you&apos;re sitting.
      </motion.h2>
      <div className="mt-10 grid gap-5 md:grid-cols-3">
        {reviews.map((r, i) => (
          <motion.div
            key={r.n}
            initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-40px" }}
            transition={{ delay: i * 0.12, duration: 0.8, ease: EASE }}
            whileHover={{ y: -5 }}
            className="flex flex-col rounded-3xl border border-fg/[0.06] bg-surface p-6 shadow-[0_10px_40px_rgba(10,18,32,0.05)]"
          >
            <div className="flex items-center justify-between">
              <p className="text-sm font-bold">{r.n}</p>
              <span className="rounded-full px-2.5 py-0.5 text-[9px] font-bold" style={{ background: `${accent}14`, color: accent }}>{r.src}</span>
            </div>
            <p className="mt-1 text-xs text-[#f5a623]">★★★★★</p>
            <p className="mt-3 flex-1 text-[13px] leading-relaxed text-fg/60">“{r.q}”</p>
          </motion.div>
        ))}
      </div>
      <p className="mt-6 text-center text-xs text-fg/35">
        Real reviews from Google, JustDial and verified students — never a fabricated one.{" "}
        <Link href="/reviews" className="font-bold hover:underline" style={{ color: accent }}>Read all reviews →</Link>
      </p>
    </section>
  );
}

/* --------------------------- interview intel (LLM) -------------------------- */

type QRound = { round: number; name: string; questions: string[] };

function InterviewIntel({ slug, accent }: { slug: string; accent: string }) {
  const [rounds, setRounds] = useState<QRound[] | null>(null);
  const [live, setLive] = useState(false);
  const [active, setActive] = useState(0);
  const ref = useRef<HTMLDivElement>(null);
  const inView = useInView(ref, { margin: "-15%" });

  useEffect(() => {
    fetch("/api/interview-questions")
      .then((r) => r.json())
      .then((d) => { setRounds(d.tracks?.[slug] ?? null); setLive(d.source === "kimi-k3"); })
      .catch(() => setRounds(null));
  }, [slug]);

  const n = rounds?.length ?? 0;
  useEffect(() => {
    if (!inView || n < 2) return;
    const id = setInterval(() => setActive((a) => (a + 1) % n), 3400);
    return () => clearInterval(id);
  }, [inView, n]);

  const r = rounds?.[Math.min(active, Math.max(0, n - 1))];

  return (
    <div ref={ref} className="overflow-hidden rounded-3xl bg-[#0a0f1c] p-7 md:p-9">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.25em]" style={{ color: accent }}>Interview intel</p>
          <h3 className="font-display mt-2 text-2xl font-bold text-white md:text-3xl">What this track&apos;s interviews ask</h3>
        </div>
        <div className="flex flex-wrap items-center gap-2.5">
          <span className={`flex items-center gap-1.5 rounded-full px-3 py-1 text-[9px] font-bold uppercase ${live ? "bg-emerald-400/10 text-emerald-400" : "bg-surface/[0.06] text-white/40"}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${live ? "animate-pulse bg-emerald-400" : "bg-surface/30"}`} />
            {rounds ? (live ? "Generated live by kimi-k3" : "Curated sample") : "Loading…"}
          </span>
          <a
            href={`/api/question-bank/${slug}`}
            download
            className="flex items-center gap-1.5 rounded-full px-4 py-1.5 text-[11px] font-bold text-white transition-transform hover:scale-[1.04]"
            style={{ background: accent }}
          >
            ⬇ Download the full question bank
          </a>
        </div>
      </div>

      {!rounds || !r ? (
        <div className="mt-6 space-y-2">
          <div className="h-3 w-2/3 animate-pulse rounded-full bg-surface/10" />
          <div className="h-3 w-1/2 animate-pulse rounded-full bg-surface/10" />
        </div>
      ) : (
        <>
          <div className="mt-6 flex gap-2">
            {rounds.map((rd, i) => (
              <button
                key={rd.round}
                onClick={() => setActive(i)}
                className="flex-1 rounded-xl px-2 py-2 text-[10px] font-bold transition-colors duration-300"
                style={i === active ? { background: accent, color: "#fff" } : { background: "rgba(255,255,255,0.06)", color: "rgba(255,255,255,0.45)" }}
              >
                <span className="hidden sm:inline">R{rd.round} · </span>{rd.name}
              </button>
            ))}
          </div>
          <AnimatePresence mode="wait">
            <motion.div
              key={active}
              initial={{ opacity: 0, y: 14 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              transition={{ duration: 0.35, ease: EASE }}
              className="mt-5 grid gap-2 md:grid-cols-3"
            >
              {r.questions.slice(0, 3).map((q, qi) => (
                <motion.div
                  key={q}
                  initial={{ opacity: 0, x: 16 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: 0.1 + qi * 0.12, duration: 0.4, ease: EASE }}
                  className="rounded-2xl bg-surface/[0.05] p-4"
                >
                  <span className="rounded bg-surface/[0.08] px-1.5 py-0.5 font-mono text-[8px] font-bold text-white/50">Q{qi + 1}</span>
                  <p className="mt-2 text-[12px] leading-snug text-white/80">“{q}”</p>
                </motion.div>
              ))}
            </motion.div>
          </AnimatePresence>
          <p className="mt-5 text-[11px] text-white/35">
            The syllabus is rebuilt monthly from questions like these — that&apos;s the whole method.
          </p>
        </>
      )}
    </div>
  );
}

/* ---------------------------------- page ------------------------------------ */

export default function CourseKeynote({ course }: { course: CourseDetail }) {
  useLenis();
  const extras = COURSE_EXTRAS[course.slug] ?? COURSE_EXTRAS["data-engineering"];
  const accent = extras.accent;

  const [role, setRole] = useState(0);
  useEffect(() => {
    if (course.roles.length < 2) return;
    const id = setInterval(() => setRole((r) => (r + 1) % course.roles.length), 2200);
    return () => clearInterval(id);
  }, [course.roles.length]);

  const facts = [
    { label: "Duration", value: course.duration },
    { label: "Format", value: course.format },
    { label: "Access", value: course.access },
    { label: "Projects", value: course.projectsLabel },
  ];

  const book = () => openLeadModal({ variant: "masterclass", courseSlug: course.slug });

  return (
    <main className="bg-surface font-body text-ink antialiased selection:text-white" style={{ ["--tw-selection" as never]: accent }}>
      <style dangerouslySetInnerHTML={{ __html: `
        ::selection { background: ${accent}; }
        @keyframes ckMarquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
      `}} />

      {/* nav */}
      <motion.header
        initial={{ y: -60, opacity: 0 }} animate={{ y: 0, opacity: 1 }} transition={{ duration: 0.9, ease: EASE }}
        className="fixed inset-x-0 top-0 z-50 border-b border-fg/[0.06] bg-surface/80 backdrop-blur-xl"
      >
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3.5 md:px-6">
          <Link href="/" aria-label="BrowseJobs home"><Wordmark /></Link>
          <div className="flex items-center gap-5">
            <a href="#journey" className="hidden text-sm font-medium text-fg/50 transition-colors hover:text-ink sm:block">
              The journey
            </a>
            <Link href="/courses" className="text-sm font-medium text-fg/50 transition-colors hover:text-ink">
              All programs
            </Link>
          </div>
        </div>
      </motion.header>

      {/* ------------------------------- hero -------------------------------- */}
      <section className="relative overflow-hidden px-6 pb-20 pt-32 md:pb-28 md:pt-40">
        <div aria-hidden className="absolute -top-40 left-1/2 h-[480px] w-[720px] -translate-x-1/2 rounded-full blur-[140px]" style={{ background: `${accent}22` }} />
        <div className="relative mx-auto max-w-4xl text-center">
          <motion.div
            initial={{ opacity: 0, scale: 0.85 }} animate={{ opacity: 1, scale: 1 }} transition={{ delay: 0.1, duration: 0.6, ease: "backOut" }}
            className="mx-auto mb-6 flex w-fit items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-bold"
            style={{ borderColor: `${accent}35`, background: `${accent}0d`, color: accent }}
          >
            <span className="text-base">{extras.icon}</span> {course.code} · Live batch ·
            <span className="relative flex h-2 w-2">
              <span className="absolute h-full w-full animate-ping rounded-full bg-[#0ba860] opacity-60" />
              <span className="relative h-2 w-2 rounded-full bg-[#0ba860]" />
            </span>
            Seats filling
          </motion.div>

          <h1 className="font-display text-5xl font-bold leading-[1.02] tracking-[-0.03em] md:text-7xl">
            {course.name.split(" ").map((w, i) => (
              <span key={w + i} className="inline-block overflow-hidden pb-1 align-bottom">
                <motion.span
                  initial={{ y: "105%" }} animate={{ y: "0%" }}
                  transition={{ delay: 0.2 + i * 0.09, duration: 0.9, ease: EASE }}
                  className="inline-block pr-3"
                >
                  {i === course.name.split(" ").length - 1 ? (
                    <span className="bg-clip-text text-transparent" style={{ backgroundImage: `linear-gradient(90deg, ${accent}, #7c3aed)` }}>{w}</span>
                  ) : w}
                </motion.span>
              </span>
            ))}
          </h1>

          <motion.p
            initial={{ opacity: 0, y: 18 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.6, duration: 0.8 }}
            className="mx-auto mt-5 max-w-xl text-lg text-fg/50"
          >
            {course.tagline}{" "}
            {course.roles.length > 0 && (
              <>
                You leave as a{" "}
                <span className="relative inline-block h-[1.55em] w-[15ch] overflow-hidden align-bottom text-left">
                  <AnimatePresence mode="popLayout">
                    <motion.span
                      key={role}
                      initial={{ y: "100%", opacity: 0 }} animate={{ y: "0%", opacity: 1 }} exit={{ y: "-100%", opacity: 0 }}
                      transition={{ duration: 0.4, ease: EASE }}
                      className="absolute inset-0 font-bold" style={{ color: accent }}
                    >
                      {course.roles[role]}
                    </motion.span>
                  </AnimatePresence>
                </span>
              </>
            )}
          </motion.p>

          {/* fact chips */}
          <motion.div
            initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.8, duration: 0.8 }}
            className="mx-auto mt-8 grid max-w-2xl grid-cols-2 gap-2.5 md:grid-cols-4"
          >
            {facts.map((f, i) => (
              <motion.div
                key={f.label}
                initial={{ opacity: 0, scale: 0.85 }} animate={{ opacity: 1, scale: 1 }}
                transition={{ delay: 0.85 + i * 0.08, type: "spring", stiffness: 280, damping: 18 }}
                className="rounded-2xl border bg-surface px-3 py-3 text-center shadow-[0_8px_30px_rgba(10,18,32,0.05)]"
                style={{ borderColor: `${accent}20` }}
              >
                <p className="text-[9px] font-bold uppercase tracking-widest text-fg/35">{f.label}</p>
                <p className="mt-1 text-sm font-bold">{f.value}</p>
              </motion.div>
            ))}
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 1.05, duration: 0.8 }}
            className="mt-9 flex flex-wrap items-center justify-center gap-4"
          >
            <Magnetic>
              <button onClick={book} className="rounded-full px-9 py-4 text-lg font-semibold text-white transition-all hover:scale-[1.02]" style={{ background: accent, boxShadow: `0 12px 40px ${accent}59` }}>
                Book the free masterclass
              </button>
            </Magnetic>
            <a href="#journey" className="group text-lg font-semibold" style={{ color: accent }}>
              Explore the journey <span className="inline-block transition-transform group-hover:translate-y-0.5">↓</span>
            </a>
          </motion.div>
          <motion.p initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 1.3 }} className="mt-4 text-sm text-fg/35">
            3 free steps first · enrollment fee ₹30,000 only after the free bootcamp · EMI available
          </motion.p>
        </div>
      </section>

      {/* tools marquee */}
      {course.tools.length > 0 && (
        <section className="border-y border-fg/[0.06] bg-paper py-5">
          <div className="overflow-hidden [mask-image:linear-gradient(90deg,transparent,black_12%,black_88%,transparent)]">
            <div className="flex w-max gap-3 pr-3" style={{ animation: "ckMarquee 26s linear infinite" }}>
              {[...course.tools, ...course.tools].map((t, i) => (
                <span key={i} className="whitespace-nowrap rounded-full border border-fg/[0.08] bg-surface px-4 py-1.5 font-mono text-xs text-fg/55">
                  {t}
                </span>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* ---------------------------- market demand ---------------------------- */}
      <MarketDemandSection slug={course.slug} accent={accent} courseName={course.name} />

      {/* ---------------------------- journey map ----------------------------- */}
      {course.modules.length > 0 && (
        <section id="journey" className="mx-auto max-w-5xl px-6 py-24 md:py-32">
          <motion.p
            initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
            className="text-center text-sm font-semibold uppercase tracking-[0.3em]" style={{ color: accent }}
          >
            The journey · tap any module
          </motion.p>
          <motion.h2
            initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
            className="font-display mx-auto mt-4 max-w-2xl text-center text-4xl font-bold leading-[1.06] tracking-tight md:text-5xl"
          >
            {course.modules.length} modules. {course.duration}. Zero filler.
          </motion.h2>
          <div className="mt-14">
            <JourneyExplorer course={course} accent={accent} />
          </div>
        </section>
      )}

      {/* ----------------------- smart systems on this course ------------------ */}
      <section className="bg-[#06070d] px-6 py-24 text-white md:py-32">
        <div className="mx-auto max-w-6xl">
          <motion.p
            initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
            className="text-sm font-semibold uppercase tracking-[0.3em]" style={{ color: accent }}
          >
            The smart systems, on this course
          </motion.p>
          <motion.h2
            initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
            className="font-display mt-4 max-w-2xl text-4xl font-bold leading-[1.06] tracking-tight md:text-5xl"
          >
            You don&apos;t study alone here.
          </motion.h2>
          <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            {[
              { t: "AI Tutor", k: "Doubts die in seconds", el: <TutorDemo q={extras.tutorQ} a={extras.tutorA} accent="#4d94ff" /> },
              { t: "Voice mock lab", k: "Interviewed before the interview", el: <MockDemo q={extras.mockQ} /> },
              { t: "Mastery map", k: "Weak topics turn green", el: <MasteryDemo course={course} accent="#fbbf24" /> },
              ...(course.roles.length > 0 ? [{ t: "Job radar", k: "Roles hunted while you study", el: <RadarDemo course={course} /> }] : []),
            ].map((c, i) => (
              <motion.div
                key={c.t}
                initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-40px" }}
                transition={{ delay: (i % 4) * 0.1, duration: 0.8, ease: EASE }}
                className="rounded-3xl border border-white/10 bg-surface/[0.04] p-6"
              >
                <p className="text-[10px] font-bold uppercase tracking-widest text-white/40">{c.t}</p>
                <h3 className="font-display mt-1.5 text-lg font-bold">{c.k}</h3>
                {c.el}
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* -------------------------- interview intel ---------------------------- */}
      {course.hasSyllabus && (
        <section className="mx-auto max-w-6xl px-6 py-24 md:py-28">
          <InterviewIntel slug={course.slug} accent={accent} />

          {/* The lead-gated syllabus download. It was dropped when this page
              was rebuilt as the keynote layout, which quietly removed a
              capture point from every course page. */}
          <div className="mt-10 flex justify-center">
            <SyllabusDownload courseSlug={course.slug} />
          </div>
        </section>
      )}

      {/* ------------------------------ projects ------------------------------- */}
      {course.projects.length > 0 && (
        <section className="bg-paper px-6 py-24 md:py-32">
          <div className="mx-auto max-w-6xl">
            <motion.p
              initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7, ease: EASE }}
              className="text-sm font-semibold uppercase tracking-[0.3em]" style={{ color: accent }}
            >
              {course.projectsLabel} projects
            </motion.p>
            <motion.h2
              initial={{ opacity: 0, y: 30 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.8, ease: EASE, delay: 0.1 }}
              className="font-display mt-4 max-w-2xl text-4xl font-bold leading-[1.06] tracking-tight md:text-5xl"
            >
              Walk into interviews with proof.
            </motion.h2>
            <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
              {course.projects.map((p, i) => (
                <motion.div
                  key={p.title}
                  initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-40px" }}
                  transition={{ delay: (i % 3) * 0.1, duration: 0.8, ease: EASE }}
                  whileHover={{ y: -6 }}
                  className="flex flex-col rounded-3xl border border-fg/[0.06] bg-surface p-7 shadow-[0_10px_40px_rgba(10,18,32,0.05)]"
                >
                  <span className="font-mono text-xs font-bold" style={{ color: accent }}>{String(i + 1).padStart(2, "0")}</span>
                  <h3 className="font-display mt-2 text-xl font-bold">{p.title}</h3>
                  <p className="mt-2 flex-1 text-sm text-fg/50">{p.body}</p>
                  <ul className="mt-4 space-y-1.5">
                    {p.points.slice(0, 3).map((pt, pi) => (
                      <motion.li
                        key={pt}
                        initial={{ opacity: 0, x: -12 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true }}
                        transition={{ delay: 0.3 + pi * 0.1, duration: 0.5 }}
                        className="flex gap-2 text-xs text-fg/60"
                      >
                        <span className="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full text-[8px] font-bold text-white" style={{ background: accent }}>✓</span>
                        {pt}
                      </motion.li>
                    ))}
                  </ul>
                </motion.div>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* -------------------------------- reviews ------------------------------ */}
      <CourseReviews slug={course.slug} accent={accent} />

      {/* ------------------------------- closer -------------------------------- */}
      <section className="relative overflow-clip bg-[#06070d] px-6 py-28 text-center text-white md:py-36">
        <div aria-hidden className="absolute left-1/2 top-1/2 h-[420px] w-[680px] -translate-x-1/2 -translate-y-1/2 rounded-full blur-[150px]" style={{ background: `${accent}30` }} />
        <div className="relative mx-auto max-w-3xl">
          <motion.div
            initial={{ opacity: 0, y: 20 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }} transition={{ duration: 0.7 }}
            className="mx-auto mb-8 flex w-fit flex-wrap items-center justify-center gap-2"
          >
            {["Free counselling", "Free masterclass", "Free 7-hr bootcamp"].map((s, i) => (
              <span key={s} className="flex items-center gap-1.5 rounded-full bg-[#0ba860]/15 px-3 py-1.5 text-[11px] font-bold text-[#34d399]">
                <span className="grid h-4 w-4 place-items-center rounded-full bg-[#0ba860] text-[8px] text-white">{i + 1}</span>
                {s}
              </span>
            ))}
          </motion.div>
          <motion.h2
            initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: "-80px" }}
            transition={{ duration: 0.9, ease: EASE }}
            className="font-display text-4xl font-bold leading-[1.05] tracking-tight md:text-6xl"
          >
            Decide after the free bootcamp —
            <br />
            <span className="bg-clip-text text-transparent" style={{ backgroundImage: `linear-gradient(90deg, ${accent}, #7c3aed)` }}>
              not before.
            </span>
          </motion.h2>
          <motion.p
            initial={{ opacity: 0 }} whileInView={{ opacity: 1 }} viewport={{ once: true }} transition={{ delay: 0.25, duration: 0.9 }}
            className="mx-auto mt-6 max-w-md text-white/50"
          >
            Enrollment fee ₹30,000 (or 3 EMIs of ₹10,000), payable only after the three free steps.
            30-day money-back guarantee, in writing.
          </motion.p>
          <motion.div
            initial={{ opacity: 0, scale: 0.9 }} whileInView={{ opacity: 1, scale: 1 }} viewport={{ once: true }}
            transition={{ delay: 0.4, duration: 0.7, ease: EASE }}
            className="mt-10"
          >
            <Magnetic>
              <button onClick={book} className="rounded-full bg-surface px-11 py-4.5 text-lg font-bold text-ink transition-all hover:scale-[1.03] hover:shadow-[0_0_60px_rgba(255,255,255,0.3)]">
                Book my free {course.code} masterclass →
              </button>
            </Magnetic>
          </motion.div>
          <p className="mt-5 text-xs text-white/30">
            <Counter to={20000} suffix="+" /> students trained · 98% interview success rate · top package ₹42 LPA
          </p>
        </div>
      </section>

      <Footer />
      <LeadModal />
      <StickyCta />
    </main>
  );
}
