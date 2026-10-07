"use client";

import { memo, useEffect, useId, useMemo, useRef, useState } from "react";
import { motion } from "framer-motion";
import { durations, ease, stagger } from "@/lib/motion";
import { PEOPLE, PHASES, callTranscript, personPhase, type Person, type PhaseId } from "./simulation";

type BotId = "job" | "screen" | "call" | "interview" | "schedule" | "bgv" | "engage";
type Accessory = "none" | "headset" | "shield" | "card";

const BOTS: readonly { id: BotId; name: string; accessory: Accessory }[] = [
  { id: "job", name: "Job", accessory: "none" },
  { id: "screen", name: "Screen", accessory: "none" },
  { id: "call", name: "Call", accessory: "headset" },
  { id: "interview", name: "Score", accessory: "none" },
  { id: "schedule", name: "Slot", accessory: "card" },
  { id: "bgv", name: "BGV", accessory: "shield" },
  { id: "engage", name: "Join", accessory: "none" },
];

function activeBot(t: number): BotId | null {
  if (t < 8_000) return "job";
  if (t < 20_000) return "screen";
  if (t < 48_000) return "call";
  if (t < 66_000) return "interview";
  if (t < 73_000) return "schedule";
  if (t < 79_000) return "bgv";
  if (t < 84_000) return null;
  return "engage";
}

function accentFor(id: BotId, t: number, waiting: boolean) {
  if (id === "call" && waiting) return "var(--bj-amber)";
  if (id === "bgv" && t >= 77_500) return "var(--bj-verify)";
  if (id === "engage" && t >= 86_000) return "var(--bj-amber)";
  if (id === "screen" || id === "interview") return "var(--bj-deep)";
  return "var(--bj-trust)";
}

export function RobotStage({
  t,
  reduced,
  waiting,
  speakingId,
}: {
  t: number;
  reduced: boolean;
  waiting: boolean;
  speakingId: string | null;
}) {
  const labelId = useId();
  const stageRef = useRef<HTMLDivElement>(null);
  const [box, setBox] = useState({ w: 0, h: 0 });
  const active = activeBot(t);

  useEffect(() => {
    const el = stageRef.current;
    if (!el) return;
    const measure = () => setBox({ w: el.clientWidth, h: el.clientHeight });
    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  const narrow = box.w > 0 && box.w < 560;
  const dockY = Math.max(120, box.h - (narrow ? 78 : 96));

  return (
    <section className="relative flex min-h-[460px] flex-col overflow-hidden rounded-[22px] border border-trust/30 bg-surface/45 shadow-soft backdrop-blur-md lg:min-h-0" aria-labelledby={labelId}>
      <Brackets />
      <div className="flex items-center justify-between gap-3 px-3 pt-3">
        <p id={labelId} className="kicker text-[10px] text-trust">
          Hiring floor · demo data
        </p>
        <p className="font-mono text-[10px] uppercase tracking-[0.16em] text-muted">{active ? `${labelOf(active)} at work` : "Bots in dock"}</p>
      </div>
      <div ref={stageRef} className="relative min-h-[400px] flex-1">
        <Floor />
        {box.w > 0 ? (
          <>
            <div
              className="absolute z-10"
              style={
                narrow
                  ? { left: 12, right: 12, top: 118, bottom: box.h - dockY + 8 }
                  : { left: "34%", right: 12, top: 8, bottom: box.h - dockY + 12 }
              }
            >
              <Action t={t} active={active} reduced={reduced} waiting={waiting} speakingId={speakingId} />
            </div>
            <CandidatePath t={t} reduced={reduced} width={box.w} top={dockY - 36} />
            {BOTS.map((bot, index) => {
              const on = bot.id === active;
              const x = on ? (narrow ? box.w / 2 - 42 : 20) : dockX(index, box.w);
              const y = on ? (narrow ? 4 : 12) : dockY;
              return (
                <motion.div
                  key={bot.id}
                  className={`absolute left-0 top-0 ${on ? "z-30" : "z-20"}`}
                  initial={false}
                  animate={{ x, y, scale: on ? 1 : 0.92 }}
                  transition={{ duration: reduced ? 0 : durations.slower, ease }}
                >
                  <RobotFigure
                    name={bot.name}
                    accent={accentFor(bot.id, t, waiting && bot.id === "call")}
                    accessory={bot.accessory}
                    size={on ? "lg" : "sm"}
                    bob={!reduced}
                    alert={(bot.id === "engage" && t >= 86_000) || (bot.id === "call" && waiting && on)}
                  />
                </motion.div>
              );
            })}
            <div className="pointer-events-none absolute inset-x-3 z-0 flex justify-between" style={{ top: dockY + 58 }} aria-hidden>
              {BOTS.map((bot) => (
                <span key={bot.id} className="h-1.5 w-10 rounded-full bg-line/80" />
              ))}
            </div>
          </>
        ) : null}
      </div>
    </section>
  );
}

function labelOf(id: BotId) {
  return BOTS.find((bot) => bot.id === id)?.name ?? "Bot";
}

function dockX(index: number, width: number) {
  const inner = Math.max(0, width - 16);
  const slot = inner / BOTS.length;
  return 8 + slot * index + Math.max(0, (slot - 52) / 2);
}

function Floor() {
  return (
    <div className="pointer-events-none absolute inset-0" aria-hidden>
      <div
        className="absolute inset-x-6 bottom-24 top-8"
        style={{
          background:
            "radial-gradient(ellipse at 50% 100%, color-mix(in srgb, var(--bj-trust) 20%, transparent), transparent 62%)",
        }}
      />
      <div
        className="absolute inset-x-8 bottom-16 h-28 origin-bottom"
        style={{
          transform: "perspective(320px) rotateX(52deg)",
          backgroundImage:
            "linear-gradient(color-mix(in srgb, var(--bj-trust) 22%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--bj-trust) 22%, transparent) 1px, transparent 1px)",
          backgroundSize: "32px 32px",
          maskImage: "linear-gradient(to top, black, transparent)",
        }}
      />
    </div>
  );
}

const RobotFigure = memo(function RobotFigure({
  name,
  accent,
  accessory,
  size,
  bob,
  alert,
}: {
  name: string;
  accent: string;
  accessory: Accessory;
  size: "lg" | "sm";
  bob: boolean;
  alert: boolean;
}) {
  const large = size === "lg";
  return (
    <div className={large ? "w-[84px]" : "w-[52px]"}>
      <div className="relative">
        <span
          className="absolute bottom-1 left-1/2 h-1.5 w-8 -translate-x-1/2 rounded-full"
          style={{ background: accent, opacity: 0.4 }}
          aria-hidden
        />
        <motion.div
          initial={false}
          animate={bob ? { y: [0, large ? -7 : -4, 0] } : { y: 0 }}
          transition={bob ? { duration: durations.slower * (large ? 2.2 : 2.8), repeat: Infinity, ease } : { duration: 0 }}
        >
          <RobotSvg accent={accent} accessory={accessory} alert={alert && bob} />
        </motion.div>
      </div>
      <p className={`mt-0.5 text-center font-mono uppercase tracking-[0.12em] text-muted ${large ? "text-[10px]" : "text-[8px]"}`}>{name}</p>
    </div>
  );
});

function RobotSvg({ accent, accessory, alert }: { accent: string; accessory: Accessory; alert: boolean }) {
  return (
    <svg viewBox="0 0 64 78" className="h-auto w-full overflow-visible" aria-hidden>
      <motion.g
        initial={false}
        animate={alert ? { opacity: [0.45, 1, 0.45] } : { opacity: 1 }}
        transition={alert ? { duration: durations.slower * 1.4, repeat: Infinity, ease } : { duration: 0 }}
      >
        <line x1="32" y1="11" x2="32" y2="3" stroke={accent} strokeWidth="1.6" strokeLinecap="round" />
        <circle cx="32" cy="3" r="2.5" fill={accent} />
      </motion.g>
      {accessory === "headset" ? (
        <g fill="none" stroke={accent} strokeWidth="1.6" strokeLinecap="round">
          <path d="M16 26c0-9 7-15 16-15s16 6 16 15" />
          <rect x="12" y="24" width="5" height="9" rx="1.5" fill={accent} stroke="none" />
          <rect x="47" y="24" width="5" height="9" rx="1.5" fill={accent} stroke="none" />
          <path d="M49 33c2 2 2 5-1 7" />
        </g>
      ) : null}
      <rect x="14" y="14" width="36" height="26" rx="12" fill="var(--bj-surface)" stroke="var(--bj-line)" strokeWidth="1.3" />
      <rect x="20" y="22" width="24" height="10" rx="5" fill={accent} />
      <rect x="22" y="24" width="7" height="6" rx="3" fill="var(--bj-fg)" opacity="0.35" />
      <rect x="18" y="43" width="28" height="22" rx="10" fill="var(--bj-surface)" stroke="var(--bj-line)" strokeWidth="1.3" />
      <circle cx="27" cy="53" r="1.5" fill={accent} />
      <circle cx="33" cy="53" r="1.5" fill={accent} opacity="0.55" />
      <circle cx="39" cy="53" r="1.5" fill={accent} opacity="0.35" />
      <rect x="22" y="66" width="8" height="5" rx="2" fill="var(--bj-ink2)" />
      <rect x="34" y="66" width="8" height="5" rx="2" fill="var(--bj-ink2)" />
      {accessory === "shield" ? (
        <path d="M48 46l9 2.2v6.2c0 4-3.2 6.6-9 8.2-5.8-1.6-9-4.2-9-8.2v-6.2z" fill="var(--bj-surface)" stroke={accent} strokeWidth="1.3" />
      ) : null}
      {accessory === "card" ? (
        <g>
          <rect x="44" y="48" width="14" height="16" rx="2" fill="var(--bj-paper)" stroke={accent} strokeWidth="1.2" />
          <path d="M47 53h8M47 57h5" stroke={accent} strokeWidth="1" />
        </g>
      ) : null}
    </svg>
  );
}

function Action({
  t,
  active,
  reduced,
  waiting,
  speakingId,
}: {
  t: number;
  active: BotId | null;
  reduced: boolean;
  waiting: boolean;
  speakingId: string | null;
}) {
  return (
    <div className="flex h-full min-h-0 items-center">
      <div className="max-h-full w-full overflow-hidden">
        {active === "job" ? <JobAct t={t} reduced={reduced} /> : null}
        {active === "screen" ? <ScreenAct t={t} reduced={reduced} /> : null}
        {active === "call" ? <CallAct t={t} reduced={reduced} waiting={waiting} speakingId={speakingId} /> : null}
        {active === "interview" ? <InterviewAct t={t} /> : null}
        {active === "schedule" ? <ScheduleAct t={t} reduced={reduced} /> : null}
        {active === "bgv" ? <BgvAct t={t} reduced={reduced} /> : null}
        {active === "engage" ? <EngageAct t={t} reduced={reduced} /> : null}
        {active === null ? <OfferAct /> : null}
      </div>
    </div>
  );
}

function JobAct({ t, reduced }: { t: number; reduced: boolean }) {
  const caught = Math.min(1, t / 3_200);
  const card = t >= 6_400;
  return (
    <div className="space-y-2">
      <motion.div
        className="max-w-[280px] rounded-[16px] bg-trust px-3 py-2 text-paper"
        initial={false}
        animate={{ x: reduced ? 0 : (1 - caught) * 64, opacity: card ? 0.55 : 1, scale: card ? 0.96 : 1 }}
        transition={{ duration: reduced ? 0 : durations.base, ease }}
      >
        <p className="font-mono text-[10px] uppercase tracking-[0.14em]">Voice note · 0:08</p>
        <MiniWave active={!reduced && !card} tone="paper" />
        <p className="mt-1 text-xs leading-snug">Backend engineers, Bengaluru, two people, about 18 LPA.</p>
      </motion.div>
      {card ? (
        <div className="max-w-[280px] rounded-[14px] border border-trust/40 bg-surface/80 px-3 py-2">
          <p className="kicker text-[10px] text-trust">Job card</p>
          <p className="mt-1 text-sm text-fg">Backend engineer</p>
          <p className="font-mono text-[11px] text-muted">Bengaluru · 2 openings · sample budget ₹18 LPA</p>
        </div>
      ) : (
        <p className="font-mono text-[10px] uppercase tracking-[0.14em] text-muted">Catching the note</p>
      )}
    </div>
  );
}

function ScreenAct({ t, reduced }: { t: number; reduced: boolean }) {
  const arrived = PEOPLE.filter((person) => (person.enter.sourcing ?? Infinity) <= t);
  return (
    <div>
      <div className="relative mb-2 h-16 max-w-[220px]">
        {[0, 1, 2].map((card) => (
          <div
            key={card}
            className="absolute h-14 w-24 rounded-[10px] border border-trust/40 bg-surface/90"
            style={{ left: card * 14, top: card * 3, transform: `rotate(${(card - 1) * 4}deg)` }}
          >
            <p className="px-2 pt-2 font-mono text-[9px] uppercase tracking-[0.12em] text-muted">CV</p>
            <p className="px-2 font-mono text-[10px] text-fg">{arrived[card]?.initials ?? "··"}</p>
          </div>
        ))}
        {!reduced ? <ScanBeam /> : <span className="absolute inset-y-1 left-8 w-6 bg-trust/40" aria-hidden />}
      </div>
      <div className="flex flex-wrap gap-1">
        {arrived.map((person) => (
          <TossTag key={person.id} initials={person.initials} score={person.match} />
        ))}
      </div>
    </div>
  );
}

const TossTag = memo(function TossTag({ initials, score }: { initials: string; score: number }) {
  return (
    <motion.span
      initial={{ opacity: 0, x: 18, y: -10 }}
      animate={{ opacity: 1, x: 0, y: 0 }}
      transition={{ duration: durations.base, ease }}
      className="inline-flex items-center gap-1 rounded-full border border-trust/40 bg-surface/80 px-1.5 py-0.5 font-mono text-[10px] text-fg"
    >
      {initials}
      <span className="text-trust">{score}</span>
    </motion.span>
  );
});

const ScanBeam = memo(function ScanBeam() {
  return (
    <motion.span
      className="absolute inset-y-1 w-6 bg-trust/45"
      animate={{ x: [0, 96, 0] }}
      transition={{ duration: durations.slower * 2.4, repeat: Infinity, ease }}
      aria-hidden
    />
  );
});

function CallAct({
  t,
  reduced,
  waiting,
  speakingId,
}: {
  t: number;
  reduced: boolean;
  waiting: boolean;
  speakingId: string | null;
}) {
  const person = PEOPLE.find((item) => item.id === speakingId) ?? PEOPLE.find((item) => (item.enter.calls ?? Infinity) <= t);
  if (waiting) {
    return (
      <div className="max-w-[280px] rounded-[14px] border border-amber/60 bg-surface/80 px-3 py-2">
        <p className="kicker text-[10px] text-amber">Headset on · holding</p>
        <p className="mt-1 text-sm text-fg">Waiting for HR to approve outreach.</p>
      </div>
    );
  }
  const lines = person ? callTranscript(person.id, t).slice(-2) : [];
  return (
    <div className="max-w-[320px] rounded-[14px] border border-trust/35 bg-surface/80 p-2.5">
      <div className="flex items-center gap-2">
        <span className="grid size-8 place-items-center rounded-full bg-trust font-mono text-[10px] text-paper">{person?.initials ?? "··"}</span>
        <div className="min-w-0">
          <p className="truncate text-sm text-fg">{person?.name ?? "Shortlist"}</p>
          <p className="font-mono text-[10px] text-muted">{person?.phone ?? "Sample"} · demo call</p>
        </div>
      </div>
      <div className="mt-2">
        <MiniWave active={!reduced} tall />
      </div>
      <div className="mt-2 space-y-1 rounded-[12px] bg-paper/40 px-2 py-1.5">
        {lines.length === 0 ? <p className="text-xs text-ink2">Working the shortlist.</p> : null}
        {lines.map((line, index) => (
          <Typed key={line.at} text={line.text} at={line.at} t={t} reduced={reduced} caret={index === lines.length - 1} />
        ))}
      </div>
    </div>
  );
}

function Typed({ text, at, t, reduced, caret }: { text: string; at: number; t: number; reduced: boolean; caret: boolean }) {
  const count = reduced ? text.length : Math.max(0, Math.floor((t - at) / 42));
  const shown = text.slice(0, count);
  const done = shown.length >= text.length;
  return (
    <p className="font-mono text-[11px] leading-snug text-ink2">
      {shown}
      {caret && !done ? <span className="text-trust">▍</span> : null}
    </p>
  );
}

function InterviewAct({ t }: { t: number }) {
  const round = t < 58_000 ? "l1" : "l2";
  const pool = PEOPLE.filter((person) => (person.enter[round] ?? Infinity) <= t && person[round] !== undefined);
  const person = pool[pool.length - 1] ?? PEOPLE[1];
  const score = person[round] ?? 0;
  const start = person.enter[round] ?? t;
  const shown = Math.round(score * Math.min(1, Math.max(0, (t - start) / 4_000)));
  const radius = 22;
  const circ = 2 * Math.PI * radius;
  return (
    <div className="flex max-w-[320px] items-center gap-3 rounded-[14px] border border-trust/35 bg-surface/80 p-2.5">
      <span className="grid size-8 place-items-center rounded-full bg-trust font-mono text-[10px] text-paper">{person.initials}</span>
      <span className="h-px w-8 bg-line" aria-hidden />
      <svg viewBox="0 0 60 60" className="size-14" aria-hidden>
        <circle cx="30" cy="30" r={radius} fill="none" stroke="var(--bj-line)" strokeWidth="4" />
        <circle
          cx="30"
          cy="30"
          r={radius}
          fill="none"
          stroke="var(--bj-trust)"
          strokeWidth="4"
          strokeLinecap="round"
          strokeDasharray={circ}
          strokeDashoffset={circ - (shown / 100) * circ}
          transform="rotate(-90 30 30)"
        />
      </svg>
      <div>
        <p className="kicker text-[10px] text-muted">{round === "l1" ? "L1" : "L2"} desk</p>
        <p className="font-mono text-2xl leading-none text-fg">{shown}</p>
        <p className="mt-1 text-xs text-ink2">{person.name}</p>
      </div>
    </div>
  );
}

function ScheduleAct({ t, reduced }: { t: number; reduced: boolean }) {
  const booked = t >= 70_000;
  return (
    <div className="flex max-w-[320px] items-center gap-2">
      <motion.div
        className={`rounded-[12px] border px-3 py-2 ${booked ? "border-trust bg-trust/15" : "border-line bg-surface/80"}`}
        initial={reduced ? false : { x: -28, opacity: 0 }}
        animate={{ x: 0, opacity: 1 }}
        transition={{ duration: reduced ? 0 : durations.slow, ease }}
      >
        <p className="kicker text-[10px] text-muted">Calendar</p>
        <p className="mt-1 font-mono text-sm text-fg">Tue 11:00</p>
        <p className="font-mono text-[10px] text-trust">{booked ? "Booked" : "Choosing"}</p>
      </motion.div>
      <span className="h-px w-6 bg-trust/60" aria-hidden />
      <div className="rounded-full border border-line bg-surface/80 px-2 py-1 text-center">
        <p className="font-mono text-[10px] text-fg">PR</p>
        <p className="font-mono text-[8px] uppercase tracking-[0.12em] text-muted">Interviewer</p>
      </div>
    </div>
  );
}

function BgvAct({ t, reduced }: { t: number; reduced: boolean }) {
  const checks = [
    { label: "Consent on file", at: 73_000 },
    { label: "Identity matched", at: 75_000 },
    { label: "Employment history matched", at: 77_500 },
  ];
  return (
    <div className="relative max-w-[300px] rounded-[14px] border border-line bg-surface/80 p-2.5">
      <p className="kicker text-[10px] text-muted">Sample Asha · Sample Rahul</p>
      <ul className="mt-2 space-y-1.5">
        {checks.map((check) => {
          const done = t >= check.at;
          return (
            <li key={check.label} className="flex items-center gap-2 text-xs">
              <span className={`grid size-3.5 place-items-center rounded-full border ${done ? "border-verify text-verify" : "border-line text-transparent"}`}>✓</span>
              <span className={done ? "text-fg" : "text-muted"}>{check.label}</span>
            </li>
          );
        })}
      </ul>
      {!reduced ? <Magnifier /> : null}
    </div>
  );
}

const Magnifier = memo(function Magnifier() {
  return (
    <motion.span
      className="pointer-events-none absolute size-8 rounded-full border-2 border-trust"
      style={{ top: 28 }}
      animate={{ x: [8, 120, 8], opacity: [0.4, 0.9, 0.4] }}
      transition={{ duration: durations.slower * 2.8, repeat: Infinity, ease }}
      aria-hidden
    />
  );
});

function EngageAct({ t, reduced }: { t: number; reduced: boolean }) {
  const risk = t >= 86_000 ? 0.74 : 0.22;
  return (
    <div className="max-w-[300px] space-y-1.5">
      <p className="max-w-[240px] rounded-[14px] border border-line bg-surface/80 px-2.5 py-1.5 text-xs text-fg">Joining is Monday. Bring a photo ID.</p>
      {t >= 85_000 ? <p className="max-w-[220px] rounded-[14px] bg-trust px-2.5 py-1.5 text-xs text-paper">Sample Rahul has not replied.</p> : null}
      <div className="rounded-[12px] border border-amber/50 bg-surface/80 px-2.5 py-2">
        <div className="flex items-center justify-between font-mono text-[10px] uppercase tracking-[0.12em]">
          <span className="text-amber">{t >= 86_000 ? "Alert" : "Watching"}</span>
          <span className="text-amber">{Math.round(risk * 100)}</span>
        </div>
        <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-line/80">
          <span className="block h-full w-full origin-left rounded-full bg-amber" style={{ transform: `scaleX(${risk})` }} />
        </div>
        {t >= 86_000 ? <p className="mt-1 text-xs text-fg">Sample Rahul · stopped replying after the offer.</p> : null}
      </div>
      <span className="sr-only">{reduced ? "static" : "live"}</span>
    </div>
  );
}

function OfferAct() {
  return (
    <div className="max-w-[280px] rounded-[14px] border border-trust/35 bg-surface/80 px-3 py-2">
      <p className="kicker text-[10px] text-trust">Offer</p>
      <p className="mt-1 text-sm text-fg">Emailed from your template.</p>
      <p className="mt-1 font-mono text-[10px] text-muted">Sample Asha · Sample Rahul</p>
    </div>
  );
}

function CandidatePath({ t, reduced, width, top }: { t: number; reduced: boolean; width: number; top: number }) {
  const placed = useMemo(() => layoutWalkers(t, width), [t, width]);
  return (
    <div className="absolute inset-x-3 z-10 h-7" style={{ top }} aria-hidden>
      <span className="absolute inset-x-0 top-1/2 h-px bg-trust/40" />
      {!reduced ? <PathPulse /> : null}
      {placed.map((person) => (
        <Walker key={person.id} initials={person.initials} x={person.x} reduced={reduced} />
      ))}
    </div>
  );
}

function layoutWalkers(t: number, width: number) {
  const groups = new Map<PhaseId, Person[]>();
  for (const person of PEOPLE) {
    const phase = personPhase(person, t);
    if (!phase) continue;
    const list = groups.get(phase) ?? [];
    list.push(person);
    groups.set(phase, list);
  }
  const placed: { id: string; initials: string; x: number }[] = [];
  const size = 20;
  const gap = 4;
  PHASES.forEach((phase, index) => {
    const group = groups.get(phase.id) ?? [];
    const center = ((index + 0.5) / PHASES.length) * Math.max(0, width - 24);
    const groupWidth = group.length * size + Math.max(0, group.length - 1) * gap;
    const start = center - groupWidth / 2;
    group.forEach((person, slot) => {
      placed.push({ id: person.id, initials: person.initials, x: start + slot * (size + gap) });
    });
  });
  placed.sort((a, b) => a.x - b.x);
  for (let index = 1; index < placed.length; index += 1) {
    const min = placed[index - 1].x + size + gap;
    if (placed[index].x < min) placed[index].x = min;
  }
  if (placed.length > 0) {
    const limit = Math.max(0, width - 24 - size);
    const overflow = placed[placed.length - 1].x - limit;
    if (overflow > 0) for (const person of placed) person.x -= overflow;
    if (placed[0].x < 0) {
      const shift = -placed[0].x;
      for (const person of placed) person.x += shift;
    }
  }
  return placed;
}

const Walker = memo(function Walker({ initials, x, reduced }: { initials: string; x: number; reduced: boolean }) {
  return (
    <motion.span
      className="absolute left-0 top-1/2 grid size-5 place-items-center rounded-full bg-trust font-mono text-[8px] text-paper"
      initial={false}
      animate={reduced ? { x, y: "-50%" } : { x, y: ["-58%", "-42%", "-58%"] }}
      transition={{
        x: { duration: reduced ? 0 : durations.slow, ease },
        y: reduced ? { duration: 0 } : { duration: durations.slower * 1.6, repeat: Infinity, ease },
      }}
    >
      {initials}
    </motion.span>
  );
});

const PathPulse = memo(function PathPulse() {
  return (
    <motion.span
      className="absolute top-1/2 h-0.5 w-10 -translate-y-1/2 bg-trust"
      animate={{ x: ["0%", "640%"] }}
      transition={{ duration: durations.slower * 3, repeat: Infinity, ease }}
    />
  );
});

const BAR_AMPS = [0.4, 0.75, 0.5, 1, 0.6, 0.9, 0.35, 0.8, 0.55, 0.95, 0.45, 0.7, 0.85, 0.4];

const MiniWave = memo(function MiniWave({ active, tall = false, tone = "trust" }: { active: boolean; tall?: boolean; tone?: "trust" | "paper" }) {
  return (
    <div className={`mt-1 flex items-end gap-1 ${tall ? "h-10" : "h-5"}`} aria-hidden>
      {BAR_AMPS.map((amp, index) => (
        <motion.span
          key={index}
          className={`w-1 origin-bottom rounded-full ${tone === "paper" ? "bg-paper" : "bg-trust"}`}
          style={{ height: `${Math.round((tall ? 14 : 8) + amp * (tall ? 24 : 10))}px` }}
          initial={false}
          animate={active ? { scaleY: [0.35, 1, 0.5, 0.88, 0.4] } : { scaleY: 0.4 + amp * 0.5 }}
          transition={active ? { duration: durations.slower * 1.5, repeat: Infinity, ease, delay: (index % 7) * stagger } : { duration: 0 }}
        />
      ))}
    </div>
  );
});

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
