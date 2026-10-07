"use client";

import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "next/navigation";
import { MotionConfig, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import {
  LOOP_MS,
  PEOPLE,
  PHASES,
  callTranscript,
  clock,
  peopleIn,
  personPhase,
  snapshot,
  type AgentCard,
  type Person,
  type PhaseId,
} from "./simulation";

const PANEL = "rounded-[22px] border border-line/80 bg-surface/75 shadow-soft backdrop-blur-md";

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
    const start = performance.now();
    const tick = (now: number) => {
      setElapsed(now - start);
      frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frame);
  }, [frozen]);

  const view = useMemo(() => snapshot(elapsed, autonomous), [elapsed, autonomous]);
  const t = view.elapsed;
  const featured = PEOPLE.find((p) => p.id === (view.speakingId ?? openId)) ?? peopleIn(view.activePhase, t)[0] ?? PEOPLE[0];

  return (
    <div className="relative min-h-screen overflow-x-hidden text-fg">
      <Backdrop reduced={reduced} />
      <div className="relative mx-auto flex min-h-screen max-w-[1440px] flex-col gap-4 px-3 py-3 sm:px-5 sm:py-5">
        <Header t={t} autonomous={autonomous} onToggle={() => setAutonomous((v) => !v)} reduced={reduced} />
        <Pipeline t={t} active={view.activePhase} onPick={setOpenId} reduced={reduced} />
        <div className="grid flex-1 gap-4 lg:grid-cols-12">
          <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 lg:col-span-8" aria-label="Agents">
            {view.agents.map((agent) => (
              <AgentCardView key={agent.id} agent={agent} reduced={reduced} />
            ))}
          </section>
          <aside className="flex flex-col gap-4 lg:col-span-4">
            <FocusCard person={featured} t={t} onOpen={() => setOpenId(featured.id)} />
            {view.approvalVisible ? <ApprovalCard /> : null}
            {view.dropoutAlert ? <DropoutBanner /> : null}
            <Feed items={view.feed} />
            <WhatsApp lines={view.chat} />
          </aside>
        </div>
        <p className="font-mono text-[10px] uppercase tracking-[0.16em] text-muted">
          Sample figures for this demo. Not a live role and not a promise of an outcome.
        </p>
      </div>
      {openId ? <Drawer person={PEOPLE.find((p) => p.id === openId) ?? featured} t={t} onClose={() => setOpenId(null)} /> : null}
      <button
        type="button"
        onClick={() => setOpenId(featured.id)}
        className="fixed bottom-4 right-4 z-20 rounded-full border border-trust/50 bg-surface/90 px-4 py-2 font-mono text-[11px] uppercase tracking-[0.14em] text-trust shadow-soft backdrop-blur lg:hidden"
      >
        Open {featured.name}
      </button>
    </div>
  );
}

function Backdrop({ reduced }: { reduced: boolean }) {
  return (
    <div className="pointer-events-none absolute inset-0" aria-hidden>
      <div
        className="absolute inset-0 opacity-80"
        style={{
          backgroundImage:
            "radial-gradient(ellipse at 50% -10%, color-mix(in srgb, var(--bj-trust) 22%, transparent), transparent 58%), linear-gradient(color-mix(in srgb, var(--bj-line) 90%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--bj-line) 90%, transparent) 1px, transparent 1px)",
          backgroundSize: "auto, 64px 64px, 64px 64px",
        }}
      />
      {reduced
        ? null
        : [12, 28, 70, 84].map((left) => (
            <motion.span
              key={left}
              className="absolute top-24 size-1 rounded-full bg-trust"
              style={{ left: `${left}%` }}
              animate={{ opacity: [0.15, 0.7, 0.15] }}
              transition={{ duration: durations.slower * 4, repeat: Infinity, ease }}
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
  const progress = t / LOOP_MS;
  return (
    <header className={`${PANEL} flex flex-wrap items-center gap-4 px-4 py-3 sm:px-5`}>
      <div className="min-w-0 flex-1">
        <p className="kicker text-trust">Demo data · not a live desk</p>
        <h1 className="display mt-1 text-2xl text-fg sm:text-3xl">Mission control</h1>
        <p className="mt-1 font-mono text-xs text-muted">
          {t >= 6_800 ? "Backend engineer · Bengaluru · 2 openings · sample budget ₹18 LPA" : "Waiting for HR…"}
        </p>
      </div>
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={onToggle}
          aria-pressed={autonomous}
          className="rounded-full border border-line px-3 py-1.5 font-mono text-[11px] uppercase tracking-[0.12em] text-fg"
        >
          {autonomous ? "Autonomous on" : "Ask HR first"}
        </button>
        <div className="relative grid size-12 place-items-center" aria-label={`Demo time ${clock(t)}`}>
          <svg viewBox="0 0 36 36" className="absolute inset-0 size-12 -rotate-90">
            <circle cx="18" cy="18" r="15" className="fill-none stroke-line" strokeWidth="2" />
            <circle
              cx="18"
              cy="18"
              r="15"
              className="fill-none stroke-trust"
              strokeWidth="2"
              strokeDasharray={94}
              strokeDashoffset={94 - progress * 94}
              style={{ transition: reduced ? "none" : "stroke-dashoffset 0.25s linear" }}
            />
          </svg>
          <span className="font-mono text-[10px] text-fg">{clock(t)}</span>
        </div>
      </div>
    </header>
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
  return (
    <section className={`${PANEL} px-3 py-3 sm:px-4`} aria-label="Hiring phases">
      <div className="flex gap-2 overflow-x-auto pb-1">
        {PHASES.map((phase, index) => {
          const people = peopleIn(phase.id, t);
          const on = phase.id === active;
          const reached = PHASES.findIndex((p) => p.id === active) >= index;
          return (
            <div key={phase.id} className="flex min-w-[132px] flex-1 items-stretch gap-2">
              <div
                className={`flex min-w-[120px] flex-1 flex-col rounded-[14px] border px-2.5 py-2 ${
                  on ? "border-trust/70 bg-sky/40" : "border-line/70 bg-paper/30"
                }`}
              >
                <div className="flex items-center justify-between gap-2">
                  <p className="kicker text-[10px] text-muted">{String(index + 1).padStart(2, "0")}</p>
                  <span className="font-mono text-sm text-fg">{people.length}</span>
                </div>
                <p className={`mt-1 text-xs ${on ? "text-trust" : "text-fg"}`}>{phase.label}</p>
                <div className="mt-2 flex -space-x-1.5">
                  {people.slice(0, 4).map((person) => (
                    <motion.button
                      key={person.id}
                      type="button"
                      layoutId={reduced ? undefined : person.id}
                      onClick={() => onPick(person.id)}
                      className="grid size-7 place-items-center rounded-full border border-paper bg-trust text-[9px] font-mono text-paper"
                      title={person.name}
                    >
                      {person.initials}
                    </motion.button>
                  ))}
                </div>
              </div>
              {index < PHASES.length - 1 ? (
                <span className={`my-auto hidden h-px w-3 shrink-0 sm:block ${reached ? "bg-trust/70" : "bg-line"}`} />
              ) : null}
            </div>
          );
        })}
      </div>
    </section>
  );
}

function AgentCardView({ agent, reduced }: { agent: AgentCard; reduced: boolean }) {
  const tone = agent.status === "idle" ? "text-muted" : "text-trust";
  return (
    <article className={`${PANEL} flex min-h-[148px] flex-col p-3.5`}>
      <div className="flex items-center justify-between gap-2">
        <h2 className="font-display text-sm text-fg">{agent.name}</h2>
        <span className={`font-mono text-[10px] uppercase tracking-[0.14em] ${tone}`}>{agent.status}</span>
      </div>
      <p className="mt-2 text-sm leading-snug text-ink2">{agent.detail}</p>
      {agent.live ? (
        <div className="mt-3 rounded-[10px] border border-trust/30 bg-sky/30 p-2.5">
          <div className="flex items-center justify-between gap-2">
            <p className="font-mono text-xs text-fg">{agent.live.who}</p>
            <p className="font-mono text-xs text-trust">{agent.live.elapsed}</p>
          </div>
          <Waveform active={!reduced} />
          <p className="mt-2 line-clamp-2 text-xs text-ink2">{agent.live.line}</p>
        </div>
      ) : null}
    </article>
  );
}

function Waveform({ active }: { active: boolean }) {
  return (
    <div className="mt-2 flex h-6 items-end gap-0.5" aria-hidden>
      {Array.from({ length: 14 }).map((_, i) => (
        <motion.span
          key={i}
          className="w-1 origin-bottom rounded-full bg-trust"
          style={{ height: 22 }}
          initial={false}
          animate={{ scaleY: active ? [0.25, 1, 0.4, 0.85, 0.3] : 0.35 }}
          transition={
            active
              ? { duration: durations.slower * 1.4, repeat: Infinity, ease, delay: i * 0.04 }
              : { duration: 0 }
          }
        />
      ))}
    </div>
  );
}

function Feed({ items }: { items: { bot: string; text: string }[] }) {
  const latest = items[0];
  return (
    <section className={`${PANEL} flex min-h-[180px] flex-1 flex-col p-3.5`} aria-label="Activity">
      <h2 className="kicker text-muted">Live activity</h2>
      <p className="sr-only" aria-live="polite">
        {latest ? `${latest.bot}. ${latest.text}` : "Waiting"}
      </p>
      <ol className="mt-3 space-y-2.5">
        {items.length === 0 ? <li className="text-sm text-muted">The desk is quiet.</li> : null}
        {items.map((item, index) => (
          <li key={`${item.bot}-${item.text}-${index}`} className="border-l border-trust/40 pl-2.5">
            <p className="font-mono text-[10px] uppercase tracking-[0.12em] text-trust">{item.bot}</p>
            <p className="text-sm text-fg">{item.text}</p>
          </li>
        ))}
      </ol>
    </section>
  );
}

function WhatsApp({ lines }: { lines: { from: "hr" | "bot"; text: string; voice?: boolean }[] }) {
  return (
    <section className={`${PANEL} flex max-h-[320px] flex-col p-3.5`} aria-label="WhatsApp with HR">
      <h2 className="kicker text-muted">WhatsApp · HR</h2>
      <div className="mt-3 flex flex-1 flex-col gap-2 overflow-y-auto pr-1">
        {lines.length === 0 ? <p className="text-sm text-muted">No messages yet.</p> : null}
        {lines.map((line, index) => (
          <div key={`${line.text}-${index}`} className={line.from === "hr" ? "self-end" : "self-start"}>
            <p className={`max-w-[260px] rounded-[14px] px-3 py-2 text-sm ${line.from === "hr" ? "bg-trust text-paper" : "border border-line bg-paper/50 text-fg"}`}>
              {line.voice ? <span className="mb-1 block font-mono text-[10px] uppercase tracking-[0.12em] opacity-80">Voice note</span> : null}
              {line.text}
            </p>
          </div>
        ))}
      </div>
    </section>
  );
}

function FocusCard({ person, t, onOpen }: { person: Person; t: number; onOpen: () => void }) {
  const phase = personPhase(person, t);
  return (
    <button type="button" onClick={onOpen} className={`${PANEL} p-3.5 text-left`}>
      <p className="kicker text-muted">Candidate in focus</p>
      <p className="mt-2 font-display text-lg text-fg">{person.name}</p>
      <p className="mt-1 font-mono text-xs text-muted">
        {person.source} · {person.match}% · {phase ? PHASES.find((item) => item.id === phase)?.label : "Waiting"}
      </p>
      <p className="mt-2 text-xs text-ink2">Open the timeline, scores, and transcript.</p>
    </button>
  );
}

function ApprovalCard() {
  return (
    <section className={`${PANEL} border-trust/50 p-3.5`} aria-label="Approval queue">
      <p className="kicker text-trust">Approval queue</p>
      <p className="mt-2 text-sm text-fg">Call bot is waiting. Shall I start reaching out to the top 6?</p>
      <div className="mt-3 flex gap-2">
        <span className="rounded-full bg-trust px-3 py-1.5 font-mono text-[11px] uppercase tracking-[0.12em] text-paper">Yes</span>
        <span className="rounded-full border border-line px-3 py-1.5 font-mono text-[11px] uppercase tracking-[0.12em] text-fg">Not yet</span>
      </div>
      <p className="mt-2 text-xs text-muted">The demo answers yes on its own so the story can finish.</p>
    </section>
  );
}

function DropoutBanner() {
  return (
    <section className={`${PANEL} border-warn/50 p-3.5`} aria-label="Dropout alert">
      <p className="kicker text-warn">Dropout alert</p>
      <p className="mt-2 text-sm text-fg">Sample Rahul · risk 74 · stopped replying after the offer. HR has been pinged.</p>
    </section>
  );
}

function Drawer({ person, t, onClose }: { person: Person; t: number; onClose: () => void }) {
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
      <aside
        role="dialog"
        aria-label={person.name}
        className={`${PANEL} m-2 flex w-full max-w-md flex-col overflow-y-auto p-4 sm:m-4`}
        onClick={(event) => event.stopPropagation()}
      >
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
        {showBgv ? (
          <p className="mt-4 text-sm text-verify">Pre-BGV: identity matched, employment history matched. Nothing failed.</p>
        ) : null}
        {showRisk ? (
          <p className="mt-4 text-sm text-warn">Dropout risk {person.dropout}. Went quiet after the offer.</p>
        ) : null}
        {(person.enter.engage ?? Infinity) <= t ? (
          <p className="mt-4 text-sm text-ink2">Engagement bot: “Joining is Monday. Bring a photo ID. Reply if the date is wrong.”</p>
        ) : null}
      </aside>
    </div>
  );
}

function Dial({ label, score }: { label: string; score: number }) {
  const r = 16;
  const c = 2 * Math.PI * r;
  return (
    <div className="flex items-center gap-2">
      <svg viewBox="0 0 40 40" className="size-12" aria-hidden>
        <circle cx="20" cy="20" r={r} fill="none" stroke="var(--bj-line)" strokeWidth="3" />
        <circle
          cx="20"
          cy="20"
          r={r}
          fill="none"
          stroke="var(--bj-trust)"
          strokeWidth="3"
          strokeLinecap="round"
          strokeDasharray={c}
          strokeDashoffset={c - (score / 100) * c}
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
