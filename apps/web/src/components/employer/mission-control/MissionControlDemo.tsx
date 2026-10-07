"use client";

import { memo, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { useSearchParams } from "next/navigation";
import { AnimatePresence, LayoutGroup, MotionConfig, motion, useReducedMotion } from "framer-motion";
import { durations, ease, stagger } from "@/lib/motion";
import { RobotStage } from "./RobotStage";
import {
  LOOP_MS,
  PEOPLE,
  PHASES,
  callTranscript,
  clock,
  peopleIn,
  personPhase,
  snapshot,
  type Person,
  type PhaseId,
} from "./simulation";

const GLASS = "relative rounded-[14px] border border-trust/25 bg-surface/55 shadow-soft backdrop-blur-md";

export function MissionControlDemo() {
  return (
    <MotionConfig reducedMotion="user">
      <Desk />
    </MotionConfig>
  );
}

function Desk() {
  const reduced = useReducedMotion() ?? false;
  const params = useSearchParams();
  const frozenSec = params.get("at");
  const frozen = frozenSec !== null && frozenSec !== "" && Number.isFinite(Number(frozenSec));
  const [elapsed, setElapsed] = useState(frozen ? Number(frozenSec) * 1000 : 0);
  const [autonomous, setAutonomous] = useState(false);
  const [openId, setOpenId] = useState<string | null>(null);

  useEffect(() => {
    if (frozen) return;
    let frame = 0;
    let last = 0;
    const start = performance.now();
    const tick = (now: number) => {
      if (now - last > 80) {
        last = now;
        setElapsed(now - start);
      }
      frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frame);
  }, [frozen]);

  useEffect(() => {
    if (!openId) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpenId(null);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [openId]);

  const view = useMemo(() => snapshot(elapsed, autonomous), [elapsed, autonomous]);
  const t = view.elapsed;
  const callWaiting = view.agents.some((agent) => agent.id === "call" && agent.status === "waiting");

  return (
    <div className="relative min-h-screen text-fg lg:h-screen lg:overflow-hidden">
      <Backdrop reduced={reduced} t={t} />
      <div className="relative z-10 mx-auto flex min-h-screen w-full max-w-[1440px] flex-col gap-2 px-2 py-2 sm:px-3 lg:h-full lg:min-h-0">
        <Header t={t} autonomous={autonomous} onToggle={() => setAutonomous((value) => !value)} reduced={reduced} />
        <Pipeline t={t} active={view.activePhase} onPick={setOpenId} reduced={reduced} />
        <div className="grid flex-1 gap-2 lg:min-h-0 lg:grid-cols-[minmax(0,1.22fr)_minmax(320px,0.86fr)] lg:overflow-hidden">
          <RobotStage t={t} reduced={reduced} waiting={callWaiting} speakingId={view.speakingId} />
          <div className="flex flex-col gap-2 lg:min-h-0 lg:overflow-hidden">
            <WhatsApp lines={view.chat} />
            <div className="flex min-h-0 flex-1 flex-col gap-2 overflow-hidden">
              {view.approvalVisible ? <ApprovalCard /> : null}
              {view.dropoutAlert ? <DropoutBanner /> : null}
              <Feed items={view.feed} reduced={reduced} />
            </div>
          </div>
        </div>
      </div>
      {openId ? (
        <Drawer person={PEOPLE.find((person) => person.id === openId) ?? PEOPLE[0]} t={t} reduced={reduced} onClose={() => setOpenId(null)} />
      ) : null}
    </div>
  );
}

function Backdrop({ reduced, t }: { reduced: boolean; t: number }) {
  const drift = reduced ? 0 : ((t % 8000) / 8000) * -16;
  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden>
      <div
        className="absolute inset-0"
        style={{
          background:
            "radial-gradient(ellipse at 28% 18%, color-mix(in srgb, var(--bj-trust) 22%, transparent), transparent 46%), radial-gradient(ellipse at 82% 88%, color-mix(in srgb, var(--bj-deep) 18%, transparent), transparent 42%)",
        }}
      />
      <div
        className="absolute -inset-10"
        style={{
          transform: `translateY(${drift}px)`,
          backgroundImage:
            "linear-gradient(color-mix(in srgb, var(--bj-trust) 10%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--bj-trust) 10%, transparent) 1px, transparent 1px)",
          backgroundSize: "68px 68px",
        }}
      />
      <div
        className="absolute inset-0 opacity-[0.07]"
        style={{
          backgroundImage:
            "repeating-linear-gradient(to bottom, transparent 0px, transparent 4px, color-mix(in srgb, var(--bj-fg) 14%, transparent) 5px)",
        }}
      />
      {[8, 18, 32, 47, 63, 74, 86, 93].map((left, index) => (
        <motion.span
          key={left}
          className="absolute size-1 rounded-full bg-trust"
          style={{ left: `${left}%`, top: `${12 + (index % 4) * 18}%` }}
          animate={reduced ? { opacity: 0.25 } : { opacity: [0.12, 0.7, 0.12], y: [0, -8, 0] }}
          transition={reduced ? { duration: 0 } : { duration: durations.slower * 4, repeat: Infinity, ease, delay: index * stagger }}
        />
      ))}
    </div>
  );
}

function Header({
  t,
  autonomous,
  onToggle,
  reduced,
}: {
  t: number;
  autonomous: boolean;
  onToggle: () => void;
  reduced: boolean;
}) {
  const latency = 32 + (Math.floor(t / 700) % 9) * 2;
  const cvs = t >= 8_000 && t < 20_000 ? Math.min(18, 3 + Math.floor((t - 8_000) / 700)) : 0;
  const calls = t >= 27_000 && t < 48_000 ? 1 : 0;
  const progress = t / LOOP_MS;
  const job = t >= 6_800 ? "Backend engineer · Bengaluru · 2 openings · sample budget ₹18 LPA" : "Waiting on the voice note";
  return (
    <header className={`${GLASS} flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2`}>
      <Brackets />
      <div className="min-w-[140px]">
        <p className="kicker text-[10px] text-trust">Demo data · not a live desk</p>
        <h1 className="font-display text-lg leading-none text-fg sm:text-xl">Mission control</h1>
      </div>
      <p className="min-w-0 flex-1 font-mono text-[10px] leading-snug text-muted sm:text-[11px]">
        {job}. Sample figures for this demo. Not a live role and not a promise of an outcome.
      </p>
      <dl className="flex items-center gap-3 font-mono text-[11px]">
        <dt className="sr-only">Demo telemetry</dt>
        <Stat k="Lat" v={`${latency}ms`} />
        <Stat k="CV/s" v={String(cvs)} />
        <Stat k="Calls" v={String(calls)} hot={calls > 0} />
      </dl>
      <button
        type="button"
        onClick={onToggle}
        aria-pressed={autonomous}
        className="rounded-full border border-line px-3 py-1 font-mono text-[10px] uppercase tracking-[0.12em] text-fg"
      >
        {autonomous ? "Autonomous on" : "Ask HR first"}
      </button>
      <div className="relative grid size-11 place-items-center" aria-label={`Demo time ${clock(t)}`}>
        <svg viewBox="0 0 36 36" className="absolute inset-0 size-11 -rotate-90">
          <circle cx="18" cy="18" r="15" className="fill-none stroke-line" strokeWidth="2" />
          <circle
            cx="18"
            cy="18"
            r="15"
            className="fill-none stroke-trust"
            strokeWidth="2"
            strokeDasharray={94}
            strokeDashoffset={94 - progress * 94}
            style={{ transition: reduced ? "none" : "stroke-dashoffset 80ms linear" }}
          />
        </svg>
        <span className="font-mono text-[10px] text-fg">{clock(t)}</span>
      </div>
    </header>
  );
}

function Stat({ k, v, hot = false }: { k: string; v: string; hot?: boolean }) {
  return (
    <div className="leading-none">
      <p className="text-[9px] uppercase tracking-[0.14em] text-muted">{k}</p>
      <p className={`mt-1 text-sm ${hot ? "text-trust" : "text-fg"}`}>{v}</p>
    </div>
  );
}

function Pipeline({
  t,
  active,
  onPick,
  reduced,
}: {
  t: number;
  active: PhaseId;
  onPick: (id: string) => void;
  reduced: boolean;
}) {
  const activeIndex = PHASES.findIndex((phase) => phase.id === active);
  return (
    <section className={`${GLASS} px-2 py-2`} aria-label="Hiring phases">
      <Brackets />
      <LayoutGroup>
        <div className="flex items-stretch gap-1 overflow-x-auto lg:overflow-visible">
          {PHASES.map((phase, index) => {
            const people = peopleIn(phase.id, t);
            const on = phase.id === active;
            const reached = index <= activeIndex;
            return (
              <div key={phase.id} className="flex min-w-[104px] flex-1 items-center gap-1">
                <Stage index={index} label={phase.label} count={people.length} on={on} reduced={reduced}>
                  {people.slice(0, 4).map((person) => (
                    <motion.button
                      key={person.id}
                      type="button"
                      layoutId={reduced ? undefined : person.id}
                      aria-label={person.initials}
                      title={person.name}
                      onClick={() => onPick(person.id)}
                      className="grid size-5 place-items-center rounded-full border border-paper bg-trust font-mono text-[7px] text-paper"
                    >
                      {person.initials}
                    </motion.button>
                  ))}
                </Stage>
                {index < PHASES.length - 1 ? <Connector hot={reached && index < activeIndex} reduced={reduced} /> : null}
              </div>
            );
          })}
        </div>
      </LayoutGroup>
    </section>
  );
}

function Stage({
  index,
  label,
  count,
  on,
  reduced,
  children,
}: {
  index: number;
  label: string;
  count: number;
  on: boolean;
  reduced: boolean;
  children: ReactNode;
}) {
  const shown = useTickCount(count, reduced);
  return (
    <div className={`relative min-w-0 flex-1 rounded-[12px] border px-1.5 py-1 ${on ? "border-trust/80 bg-sky/50" : "border-line/80 bg-paper/25"}`}>
      {on && !reduced ? <ActiveHalo /> : null}
      <div className="relative">
        <div className="flex items-center justify-between gap-1">
          <p className="font-mono text-[9px] uppercase tracking-[0.12em] text-muted">{String(index + 1).padStart(2, "0")}</p>
          <p className="font-mono text-sm text-fg" aria-label={`${label} count ${shown}`}>
            {shown}
          </p>
        </div>
        <p className={`truncate text-[11px] ${on ? "text-trust" : "text-fg"}`}>{label}</p>
        <div className="mt-1 flex min-h-5 -space-x-1">{children}</div>
      </div>
    </div>
  );
}

const ActiveHalo = memo(function ActiveHalo() {
  return (
    <motion.span
      className="absolute -inset-0.5 rounded-[14px] bg-trust/25"
      animate={{ opacity: [0.25, 0.7, 0.25], scale: [0.98, 1.03, 0.98] }}
      transition={{ duration: durations.slower * 2, repeat: Infinity, ease }}
      aria-hidden
    />
  );
});

const Connector = memo(function Connector({ hot, reduced }: { hot: boolean; reduced: boolean }) {
  return (
    <span className="relative hidden h-6 w-3 shrink-0 overflow-hidden sm:block" aria-hidden>
      <span className={`absolute left-0 right-0 top-1/2 h-px ${hot ? "bg-trust/50" : "bg-line"}`} />
      <motion.span
        className={`absolute top-1/2 -mt-px h-0.5 w-2/3 ${hot ? "bg-trust" : "bg-trust/30"}`}
        animate={reduced ? { x: 0 } : { x: ["-120%", "220%"] }}
        transition={reduced ? { duration: 0 } : { duration: durations.slower * 2.2, repeat: Infinity, ease }}
      />
    </span>
  );
});

function useTickCount(target: number, reduced: boolean) {
  const [value, setValue] = useState(target);
  const valueRef = useRef(target);
  useEffect(() => {
    if (reduced || valueRef.current === target) {
      valueRef.current = target;
      setValue(target);
      return;
    }
    const from = valueRef.current;
    const start = performance.now();
    let frame = 0;
    const step = (now: number) => {
      const progress = Math.min(1, (now - start) / (durations.slow * 1000));
      const next = Math.round(from + (target - from) * progress);
      valueRef.current = next;
      setValue(next);
      if (progress < 1) frame = requestAnimationFrame(step);
    };
    frame = requestAnimationFrame(step);
    return () => cancelAnimationFrame(frame);
  }, [target, reduced]);
  return value;
}


const VOICE_BARS = [0.4, 0.7, 0.5, 1, 0.6, 0.85, 0.35, 0.75, 0.55, 0.9, 0.45, 0.65];

const Waveform = memo(function Waveform({ active }: { active: boolean }) {
  return (
    <div className="mt-1 flex h-5 items-end gap-[3px]" aria-hidden>
      {VOICE_BARS.map((amp, index) => (
        <motion.span
          key={index}
          className="w-[3px] origin-bottom rounded-full bg-current"
          style={{ height: `${Math.round(6 + amp * 12)}px` }}
          initial={false}
          animate={active ? { scaleY: [0.4, 1, 0.55, 0.85, 0.4] } : { scaleY: 0.45 + amp * 0.4 }}
          transition={active ? { duration: durations.slower * 1.5, repeat: Infinity, ease, delay: index * stagger } : { duration: 0 }}
        />
      ))}
    </div>
  );
});

function Feed({ items, reduced }: { items: { at: number; bot: string; text: string }[]; reduced: boolean }) {
  const latest = items[0];
  return (
    <section className={`${GLASS} flex min-h-[120px] flex-1 flex-col overflow-hidden p-2.5`} aria-label="Activity">
      <Brackets />
      <h2 className="kicker text-[10px] text-muted">Live activity</h2>
      <p className="sr-only" aria-live="polite">
        {latest ? `${latest.bot}. ${latest.text}` : "Waiting"}
      </p>
      <ol className="mt-2 min-h-0 flex-1 space-y-1.5 overflow-y-auto">
        {items.length === 0 ? <li className="text-sm text-muted">The desk is quiet.</li> : null}
        <AnimatePresence initial={false}>
          {items.map((item, index) => (
            <motion.li
              key={item.at}
              initial={reduced ? false : { opacity: 0, y: -12 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0 }}
              transition={{ duration: durations.base, ease }}
              className={`relative overflow-hidden rounded-[10px] border-l-2 px-2 py-1 ${index === 0 ? "border-trust bg-trust/10" : "border-line"}`}
            >
              {index === 0 && !reduced ? (
                <motion.span
                  className="pointer-events-none absolute inset-0 bg-trust/25"
                  initial={{ opacity: 0.85 }}
                  animate={{ opacity: 0 }}
                  transition={{ duration: durations.slower, ease }}
                  aria-hidden
                />
              ) : null}
              <p className="relative font-mono text-[10px] uppercase tracking-[0.12em] text-trust">{item.bot}</p>
              <p className="relative text-xs text-fg">{item.text}</p>
            </motion.li>
          ))}
        </AnimatePresence>
      </ol>
    </section>
  );
}

function WhatsApp({ lines }: { lines: { at: number; from: "hr" | "bot"; text: string; voice?: boolean }[] }) {
  const scroller = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const el = scroller.current;
    if (!el) return;
    el.scrollTop = el.scrollHeight;
  }, [lines.length]);
  return (
    <section className={`${GLASS} flex h-[220px] shrink-0 flex-col p-2.5 lg:h-[208px]`} aria-label="WhatsApp with HR">
      <Brackets />
      <div className="flex items-center gap-2">
        <span className="grid size-7 place-items-center rounded-full border border-trust/50 font-mono text-[9px] text-trust">HR</span>
        <div className="min-w-0">
          <h2 className="text-sm leading-none text-fg">WhatsApp · HR</h2>
          <p className="mt-0.5 font-mono text-[9px] uppercase tracking-[0.14em] text-muted">Demo thread</p>
        </div>
      </div>
      <div ref={scroller} className="mt-2 flex min-h-0 flex-1 flex-col justify-end gap-1.5 overflow-y-auto pr-0.5">
        {lines.length === 0 ? <p className="text-sm text-muted">No messages yet.</p> : null}
        {lines.map((line) => (
          <div key={line.at} className={line.from === "hr" ? "self-end" : "self-start"}>
            <div
              className={`max-w-[280px] rounded-[14px] px-2.5 py-1.5 text-xs ${
                line.from === "hr" ? "bg-trust text-paper" : "border border-line bg-paper/50 text-fg"
              }`}
            >
              {line.voice ? (
                <span className="mb-1 block">
                  <span className="font-mono text-[9px] uppercase tracking-[0.12em] opacity-80">Voice note · 0:08</span>
                  <Waveform active={false} />
                </span>
              ) : null}
              {line.text}
              <span className={`mt-0.5 block text-right font-mono text-[9px] ${line.from === "hr" ? "text-paper/80" : "text-muted"}`}>
                {clock(line.at)}
              </span>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}

function ApprovalCard() {
  return (
    <section className={`${GLASS} border-amber/60 p-2.5`} aria-label="Approval queue">
      <Brackets />
      <p className="kicker text-[10px] text-amber">Approval queue</p>
      <p className="mt-1 text-sm text-fg">Call bot is waiting. Shall I start reaching out to the top 6?</p>
      <div className="mt-2 flex gap-2">
        <span className="rounded-full bg-trust px-3 py-1 font-mono text-[10px] uppercase tracking-[0.12em] text-paper">Yes</span>
        <span className="rounded-full border border-line px-3 py-1 font-mono text-[10px] uppercase tracking-[0.12em] text-fg">Not yet</span>
      </div>
    </section>
  );
}

function DropoutBanner() {
  return (
    <section className={`${GLASS} border-amber/70 p-2.5`} aria-label="Dropout alert">
      <Brackets />
      <p className="kicker text-[10px] text-amber">Dropout alert</p>
      <p className="mt-1 text-sm text-fg">Sample Rahul · risk 74 · stopped replying after the offer. HR has been pinged.</p>
    </section>
  );
}

function Drawer({ person, t, reduced, onClose }: { person: Person; t: number; reduced: boolean; onClose: () => void }) {
  const phase = personPhase(person, t);
  const transcript = callTranscript(person.id, t);
  const showL1 = (person.enter.l1 ?? Infinity) <= t && person.l1 !== undefined;
  const showL2 = (person.enter.l2 ?? Infinity) <= t && person.l2 !== undefined;
  const showBgv = (person.enter.bgv ?? Infinity) <= t;
  const showRisk = person.dropout !== undefined && t >= 86_000;
  return (
    <div
      className="fixed inset-0 z-30 flex justify-end"
      style={{ backgroundColor: "color-mix(in srgb, var(--bj-paper) 72%, transparent)" }}
      role="presentation"
      onClick={onClose}
    >
      <motion.aside
        role="dialog"
        aria-label={person.name}
        className={`${GLASS} m-2 flex w-full max-w-md flex-col overflow-y-auto p-4 sm:m-4`}
        onClick={(event) => event.stopPropagation()}
        initial={reduced ? false : { opacity: 0, x: 28 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ duration: durations.base, ease }}
      >
        <Brackets />
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="kicker text-trust">Sample candidate</p>
            <h2 className="display mt-1 text-2xl text-fg">{person.name}</h2>
            <p className="mt-1 font-mono text-xs text-muted">
              {person.source} · match {person.match}% · {person.phone}
            </p>
          </div>
          <button type="button" onClick={onClose} className="rounded-full border border-line px-3 py-1 text-sm text-fg">
            Close
          </button>
        </div>
        <p className="mt-4 text-sm text-ink2">Now in {phase ? PHASES.find((item) => item.id === phase)?.label : "the pool"}.</p>
        <ol className="mt-4 space-y-2">
          {PHASES.filter((item) => (person.enter[item.id] ?? Infinity) <= t).map((item) => (
            <li key={item.id} className="flex items-center justify-between border-b border-line/60 py-1.5 text-sm">
              <span>{item.label}</span>
              <span className="font-mono text-xs text-muted">{clock(person.enter[item.id] ?? 0)}</span>
            </li>
          ))}
        </ol>
        {transcript.length > 0 ? (
          <div className="mt-4">
            <h3 className="kicker text-muted">Call</h3>
            <ul className="mt-2 space-y-1.5 text-sm text-ink2">
              {transcript.map((line) => (
                <li key={line.at}>{line.text}</li>
              ))}
            </ul>
          </div>
        ) : null}
        {showL1 || showL2 ? (
          <div className="mt-4 flex gap-4">
            {showL1 ? <Dial label="L1" score={person.l1 ?? 0} /> : null}
            {showL2 ? <Dial label="L2" score={person.l2 ?? 0} /> : null}
          </div>
        ) : null}
        {showBgv ? <p className="mt-4 text-sm text-verify">Pre-BGV: identity matched, employment history matched. Nothing failed.</p> : null}
        {showRisk ? <p className="mt-4 text-sm text-amber">Dropout risk {person.dropout}. Went quiet after the offer.</p> : null}
        {(person.enter.engage ?? Infinity) <= t ? (
          <p className="mt-4 text-sm text-ink2">Engagement bot: “Joining is Monday. Bring a photo ID. Reply if the date is wrong.”</p>
        ) : null}
      </motion.aside>
    </div>
  );
}

function Dial({ label, score }: { label: string; score: number }) {
  const radius = 16;
  const circ = 2 * Math.PI * radius;
  return (
    <div className="flex items-center gap-2">
      <svg viewBox="0 0 40 40" className="size-12" aria-hidden>
        <circle cx="20" cy="20" r={radius} fill="none" stroke="var(--bj-line)" strokeWidth="3" />
        <circle
          cx="20"
          cy="20"
          r={radius}
          fill="none"
          stroke="var(--bj-trust)"
          strokeWidth="3"
          strokeLinecap="round"
          strokeDasharray={circ}
          strokeDashoffset={circ - (score / 100) * circ}
          transform="rotate(-90 20 20)"
        />
      </svg>
      <div>
        <p className="kicker text-muted">{label}</p>
        <p className="font-mono text-lg text-fg">{score}</p>
      </div>
    </div>
  );
}

function Brackets() {
  const corner = "pointer-events-none absolute z-10 h-2.5 w-2.5 border-trust/80";
  return (
    <>
      <span className={`${corner} left-1.5 top-1.5 border-l border-t`} aria-hidden />
      <span className={`${corner} right-1.5 top-1.5 border-r border-t`} aria-hidden />
      <span className={`${corner} bottom-1.5 left-1.5 border-b border-l`} aria-hidden />
      <span className={`${corner} bottom-1.5 right-1.5 border-b border-r`} aria-hidden />
    </>
  );
}
