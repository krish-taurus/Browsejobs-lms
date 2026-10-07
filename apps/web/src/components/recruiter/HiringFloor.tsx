"use client";

import { useEffect, useId, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { parseHiringPrompt, SAMPLE_PROMPTS, SHOWCASE_MS } from "./demo-data";
import { getHiringFloorData } from "./getHiringFloorData";
import { HoloStage } from "./HoloStage";
import type { BgvStatus, FloorCall, FloorCandidate, Interest, JobBrief } from "./types";

type Variant = "full" | "embed" | "console";
type Decision = "yes" | "no" | null;
type Panel = "calls" | "activity" | "approvals";

const INTEREST: Record<Interest, string> = {
  interested: "Interested",
  not_interested: "Not interested",
  no_answer: "No answer",
  unknown: "Not called yet",
};

const BGV: Record<BgvStatus, string> = {
  pending: "Pending",
  in_progress: "In progress",
  verified: "Verified",
  flagged: "Flagged",
};

type SpeechRec = {
  lang: string;
  interimResults: boolean;
  start: () => void;
  stop: () => void;
  onresult: ((event: { results: ArrayLike<ArrayLike<{ transcript: string }>> }) => void) | null;
  onerror: (() => void) | null;
  onend: (() => void) | null;
};

function speechCtor(): (new () => SpeechRec) | null {
  if (typeof window === "undefined") return null;
  const w = window as Window & {
    SpeechRecognition?: new () => SpeechRec;
    webkitSpeechRecognition?: new () => SpeechRec;
  };
  return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
}

export function HiringFloor({
  variant = "full",
  frozenAtMs = null,
  initialElapsedMs,
}: {
  variant?: Variant;
  frozenAtMs?: number | null;
  initialElapsedMs?: number;
}) {
  const reduced = useReducedMotion() ?? false;
  const embed = variant === "embed";
  const frozen = frozenAtMs != null;
  const [elapsed, setElapsed] = useState(frozen ? frozenAtMs : (initialElapsedMs ?? SHOWCASE_MS));
  const [playToken, setPlayToken] = useState(0);
  const [autonomous, setAutonomous] = useState(false);
  const [brief, setBrief] = useState<JobBrief | null>(null);
  const [prompt, setPrompt] = useState("");
  const [promptError, setPromptError] = useState<string | null>(null);
  const [panel, setPanel] = useState<Panel>("calls");
  const [openId, setOpenId] = useState<string | null>(null);
  const [outreach, setOutreach] = useState<Decision>(null);
  const [offer, setOffer] = useState<Decision>(null);
  const [listening, setListening] = useState(false);
  const [micNote, setMicNote] = useState<string | null>(null);
  const [micReady, setMicReady] = useState(false);
  const recRef = useRef<SpeechRec | null>(null);

  useEffect(() => {
    setMicReady(speechCtor() !== null);
  }, []);

  const elapsedRef = useRef(elapsed);
  elapsedRef.current = elapsed;

  useEffect(() => {
    if (frozen) return;
    let frame = 0;
    let last = 0;
    const origin = performance.now() - elapsedRef.current;
    const tick = (now: number) => {
      if (now - last > 200) {
        last = now;
        setElapsed(now - origin);
      }
      frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frame);
  }, [frozen, playToken]);

  useEffect(() => {
    if (!openId) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpenId(null);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [openId]);

  const floor = useMemo(
    () => getHiringFloorData({ elapsedMs: elapsed, autonomous, brief }),
    [elapsed, autonomous, brief],
  );
  const open = floor.candidates.find((person) => person.id === openId) ?? null;

  function applyPrompt(text: string) {
    const parsed = parseHiringPrompt(text);
    if (!parsed) {
      setPromptError("Name a role. Try: Hire 3 React developers in Bangalore, 2-4 yrs, notice under 30 days.");
      return;
    }
    setPromptError(null);
    setBrief(parsed);
    setPrompt("");
  }

  function toggleMic() {
    const Ctor = speechCtor();
    if (!Ctor) {
      setMicNote("This browser has no microphone speech. Use a sample prompt. Nothing is sent to a server.");
      return;
    }
    if (listening && recRef.current) {
      recRef.current.stop();
      setListening(false);
      return;
    }
    const rec = new Ctor();
    rec.lang = "en-IN";
    rec.interimResults = false;
    rec.onresult = (event) => {
      const said = event.results[0]?.[0]?.transcript ?? "";
      if (said) {
        setPrompt(said);
        applyPrompt(said);
      }
    };
    rec.onerror = () => {
      setListening(false);
      setMicNote("The microphone did not start. Use a sample prompt. No account and no key are required.");
    };
    rec.onend = () => setListening(false);
    recRef.current = rec;
    try {
      rec.start();
      setListening(true);
      setMicNote(null);
    } catch {
      setMicNote("The microphone did not start. Use a sample prompt.");
    }
  }

  const Title = embed ? "p" : "h1";
  const shell = embed
    ? "rounded-[22px] border border-line bg-paper p-3 sm:p-4"
    : variant === "console"
      ? "min-h-[820px] rounded-[22px] border border-line bg-paper"
      : "min-h-screen lg:h-screen lg:overflow-hidden";

  return (
    <div data-theme="dark" className={`relative text-fg ${shell}`}>
      <div className={`flex min-w-0 max-w-full flex-col gap-3 ${embed ? "" : "h-full p-2 sm:p-3"}`}>
        <header className="flex flex-wrap items-start justify-between gap-3 rounded-[14px] border border-line bg-surface/80 px-3 py-3 backdrop-blur-md">
          <div className="min-w-0">
            <p className="kicker text-trust">Demo data</p>
            <Title className="display mt-1 text-2xl leading-none text-fg sm:text-3xl">BrowseJobs AI Recruiter</Title>
            <p className="mono mt-2 text-[10px] uppercase tracking-[0.16em] text-muted">Powered by Taurus AI</p>
          </div>
          <div className="max-w-md text-right">
            <p className="text-sm text-fg">{floor.company}</p>
            <p className="mt-1 text-sm text-muted" aria-live="polite">
              {floor.job.title} · {floor.job.city} · {floor.job.openings} openings · {floor.job.experience} · notice {floor.job.notice}
            </p>
            <p className="mt-1 text-xs text-muted">AI calls, pre-BGV, offers, and joining chats are not live yet.</p>
          </div>
        </header>

        <ul aria-label="Stage counts" className="flex w-full min-w-0 max-w-full gap-2 overflow-x-auto pb-1">
          {floor.stages.map((stage) => (
            <li
              key={stage.id}
              className={`flex shrink-0 items-center gap-2 rounded-full border px-3 py-1.5 ${stage.id === floor.activeStage ? "border-trust bg-trust/15" : "border-line bg-surface/60"}`}
            >
              <span className="mono text-sm text-fg">{stage.count}</span>
              <span className="text-xs font-medium text-muted">{stage.label}</span>
              {stage.id === "offer" ? (
                <span className="text-[10px] font-semibold text-fg">Needs your approval</span>
              ) : stage.comingSoon ? (
                <span className="mono text-[9px] uppercase tracking-[0.12em] text-muted">Soon</span>
              ) : null}
            </li>
          ))}
        </ul>

        <div className={`grid min-h-0 flex-1 gap-3 ${embed ? "" : "lg:grid-cols-[16rem_minmax(0,1fr)_20rem]"}`}>
          {embed ? null : (
            <CandidateList people={floor.candidates} onOpen={setOpenId} className="hidden lg:flex" />
          )}

          <div className="flex min-h-0 min-w-0 flex-col gap-3">
            <HoloStage stages={floor.stages} agents={floor.agents} active={floor.activeStage} compact={embed} />
            <CommandBar
              prompt={prompt}
              error={promptError}
              listening={listening}
              micReady={micReady}
              micNote={micNote}
              embed={embed}
              autonomous={autonomous}
              frozen={frozen}
              onPrompt={setPrompt}
              onSubmit={() => applyPrompt(prompt)}
              onSample={(line) => applyPrompt(line)}
              onMic={toggleMic}
              onToggle={() => setAutonomous((value) => !value)}
              onReplay={() => {
                if (frozen) return;
                setElapsed(0);
                setPlayToken((value) => value + 1);
              }}
            />
          </div>

          {embed ? (
            <div className="grid gap-3 md:grid-cols-2">
              <CallsLog calls={floor.calls.slice(0, 4)} />
              <div className="flex flex-col gap-3">
                <CandidateList people={floor.candidates.slice(0, 6)} onOpen={setOpenId} />
                <Approvals
                  autonomous={autonomous}
                  outreach={outreach}
                  offer={offer}
                  onOutreach={setOutreach}
                  onOffer={setOffer}
                />
              </div>
            </div>
          ) : (
            <DeskPanels
              panel={panel}
              onPanel={setPanel}
              calls={floor.calls}
              activity={floor.activity}
              autonomous={autonomous}
              outreach={outreach}
              offer={offer}
              onOutreach={setOutreach}
              onOffer={setOffer}
              className="hidden lg:flex"
            />
          )}
        </div>

        {embed ? (
          <p className="text-sm text-muted">
            <Link href="/employers/mission-control-demo" className="font-semibold text-trust hover:text-deep">
              Open the full demo
            </Link>
            <span> · fictional names · nothing is sent</span>
          </p>
        ) : (
          <div className="grid gap-3 lg:hidden">
            <CandidateList people={floor.candidates} onOpen={setOpenId} />
            <DeskPanels
              panel={panel}
              onPanel={setPanel}
              calls={floor.calls}
              activity={floor.activity}
              autonomous={autonomous}
              outreach={outreach}
              offer={offer}
              onOutreach={setOutreach}
              onOffer={setOffer}
            />
          </div>
        )}
      </div>

      <AnimatePresence>
        {open ? (
          <CandidateDrawer person={open} reduced={reduced} call={floor.calls.find((row) => row.candidateId === open.id) ?? null} onClose={() => setOpenId(null)} />
        ) : null}
      </AnimatePresence>
    </div>
  );
}

function CandidateList({
  people,
  onOpen,
  className = "",
}: {
  people: FloorCandidate[];
  onOpen: (id: string) => void;
  className?: string;
}) {
  return (
    <section aria-label="Candidates" className={`min-h-0 flex-col rounded-[14px] border border-line bg-surface/70 ${className || "flex"}`}>
      <header className="flex items-center justify-between px-3 py-2">
        <h2 className="text-xs font-semibold uppercase tracking-[0.14em] text-muted">Candidates</h2>
        <span className="mono text-[10px] uppercase tracking-[0.14em] text-muted">Demo data</span>
      </header>
      <ul className="min-h-0 flex-1 space-y-1 overflow-y-auto px-2 pb-2">
        {people.map((person) => (
          <li key={person.id}>
            <button
              type="button"
              onClick={() => onOpen(person.id)}
              className="flex w-full items-center justify-between gap-2 rounded-[10px] px-2 py-2 text-left hover:bg-white/5"
            >
              <span className="min-w-0">
                <span className="block truncate text-sm font-medium text-fg">Open {person.name}</span>
                <span className="block truncate text-xs text-muted">
                  {person.source} · {person.stage === "ai" ? "AI interview" : person.stage}
                </span>
              </span>
              <span className="mono text-xs text-fg">{person.match}</span>
            </button>
          </li>
        ))}
      </ul>
    </section>
  );
}

function CallsLog({ calls }: { calls: FloorCall[] }) {
  return (
    <section aria-label="Calls" className="rounded-[14px] border border-line bg-surface/70">
      <header className="flex items-center justify-between px-3 py-2">
        <h2 className="text-xs font-semibold uppercase tracking-[0.14em] text-muted">Calls</h2>
        <span className="mono text-[10px] uppercase tracking-[0.14em] text-muted">Demo · not live</span>
      </header>
      {calls.length === 0 ? (
        <p className="px-3 pb-3 text-sm text-muted">No sample calls yet on this pass of the story.</p>
      ) : (
        <ul className="max-h-72 space-y-2 overflow-y-auto px-3 pb-3">
          {calls.map((call) => (
            <li key={call.id} className="border-t border-line pt-2 first:border-t-0 first:pt-0">
              <p className="text-sm font-medium text-fg">{call.name}</p>
              <p className="mono mt-0.5 text-[11px] text-muted">
                {call.when} · {call.duration} · {call.outcome}
              </p>
              <p className="mt-1 text-sm leading-snug text-muted">{call.snippet}</p>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function ActivityFeed({ items }: { items: { id: string; time: string; agent: string; text: string }[] }) {
  return (
    <section aria-label="Activity" className="min-h-0 flex-1 overflow-y-auto">
      <h2 className="sr-only">Activity</h2>
      <ul className="space-y-2">
        {items.map((item) => (
          <li key={item.id} className="border-t border-line pt-2 first:border-t-0">
            <p className="mono text-[11px] text-muted">
              {item.time} · {item.agent}
            </p>
            <p className="mt-0.5 text-sm leading-snug text-fg">{item.text}</p>
          </li>
        ))}
      </ul>
    </section>
  );
}

function Approvals({
  autonomous,
  outreach,
  offer,
  onOutreach,
  onOffer,
}: {
  autonomous: boolean;
  outreach: Decision;
  offer: Decision;
  onOutreach: (value: Decision) => void;
  onOffer: (value: Decision) => void;
}) {
  return (
    <section aria-label="Approvals" className="rounded-[14px] border border-line bg-surface/70 p-3">
      <h2 className="text-xs font-semibold uppercase tracking-[0.14em] text-muted">Approvals</h2>
      <p className="mt-1 text-xs text-muted">
        {autonomous
          ? "Autonomous walks the earlier steps in this demo. Offers always need a human."
          : "Outreach and the offer need a yes. Default is ask first. Offers always need a human."}
      </p>
      <ApprovalRow
        title="Start outreach"
        detail="Call the sample shortlist. The dialler is not live, so yes only marks the demo."
        decision={outreach}
        onYes={() => onOutreach("yes")}
        onNo={() => onOutreach("no")}
        yesLabel="Approve outreach"
        noLabel="Decline outreach"
      />
      <ApprovalRow
        title="Release offer"
        detail="Sample offer for Sample Asha Iyer. Nothing is emailed."
        decision={offer}
        pendingLabel="Needs your approval"
        onYes={() => onOffer("yes")}
        onNo={() => onOffer("no")}
        yesLabel="Approve offer"
        noLabel="Decline offer"
      />
    </section>
  );
}

function ApprovalRow({
  title,
  detail,
  decision,
  pendingLabel,
  onYes,
  onNo,
  yesLabel,
  noLabel,
}: {
  title: string;
  detail: string;
  decision: Decision;
  pendingLabel?: string;
  onYes: () => void;
  onNo: () => void;
  yesLabel: string;
  noLabel: string;
}) {
  return (
    <div className="mt-3 border-t border-line pt-3">
      <p className="text-sm font-medium text-fg">{title}</p>
      <p className="mt-1 text-xs leading-relaxed text-muted">{detail}</p>
      {pendingLabel && !decision ? (
        <p className="mt-2 text-xs font-semibold text-fg" role="status">
          {pendingLabel}
        </p>
      ) : null}
      <div className="mt-2 flex flex-wrap gap-2">
        <button type="button" onClick={onYes} className="rounded-full border border-trust px-3 py-1.5 text-xs font-semibold text-fg">
          {yesLabel}
        </button>
        <button type="button" onClick={onNo} className="rounded-full border border-line px-3 py-1.5 text-xs font-semibold text-muted">
          {noLabel}
        </button>
      </div>
      {decision ? (
        <p className="mt-2 text-xs text-fg" role="status">
          {decision === "yes" ? "Marked yes in this demo. Nothing was sent." : "Marked no. The demo waits."}
        </p>
      ) : null}
    </div>
  );
}

function DeskPanels({
  panel,
  onPanel,
  calls,
  activity,
  autonomous,
  outreach,
  offer,
  onOutreach,
  onOffer,
  className = "",
}: {
  panel: Panel;
  onPanel: (panel: Panel) => void;
  calls: FloorCall[];
  activity: { id: string; time: string; agent: string; text: string }[];
  autonomous: boolean;
  outreach: Decision;
  offer: Decision;
  onOutreach: (value: Decision) => void;
  onOffer: (value: Decision) => void;
  className?: string;
}) {
  const tabs: { id: Panel; label: string }[] = [
    { id: "calls", label: "Calls" },
    { id: "activity", label: "Activity" },
    { id: "approvals", label: "Approvals" },
  ];
  return (
    <div className={`min-h-0 flex-col rounded-[14px] border border-line bg-surface/70 ${className || "flex"}`}>
      <div className="flex gap-1 p-2" role="tablist" aria-label="Floor panels">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            type="button"
            role="tab"
            aria-selected={panel === tab.id}
            onClick={() => onPanel(tab.id)}
            className={`rounded-full px-3 py-1 text-xs font-semibold ${panel === tab.id ? "bg-trust text-white" : "text-muted"}`}
          >
            {tab.label}
          </button>
        ))}
      </div>
      <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-3">
        {panel === "calls" ? <CallsLog calls={calls} /> : null}
        {panel === "activity" ? <ActivityFeed items={activity} /> : null}
        {panel === "approvals" ? (
          <Approvals autonomous={autonomous} outreach={outreach} offer={offer} onOutreach={onOutreach} onOffer={onOffer} />
        ) : null}
      </div>
    </div>
  );
}

function CommandBar({
  prompt,
  error,
  listening,
  micReady,
  micNote,
  embed,
  autonomous,
  frozen,
  onPrompt,
  onSubmit,
  onSample,
  onMic,
  onToggle,
  onReplay,
}: {
  prompt: string;
  error: string | null;
  listening: boolean;
  micReady: boolean;
  micNote: string | null;
  embed: boolean;
  autonomous: boolean;
  frozen: boolean;
  onPrompt: (value: string) => void;
  onSubmit: () => void;
  onSample: (line: string) => void;
  onMic: () => void;
  onToggle: () => void;
  onReplay: () => void;
}) {
  return (
    <form
      className="rounded-[14px] border border-line bg-surface/80 p-3"
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      <label className="block">
        <span className="text-xs font-semibold uppercase tracking-[0.14em] text-muted">Tell the recruiter</span>
        <span className="mt-2 flex gap-2">
          <input
            value={prompt}
            onChange={(event) => onPrompt(event.target.value)}
            placeholder="Hire 3 React developers in Bangalore, 2-4 yrs, notice under 30 days"
            aria-label="Tell the recruiter"
            className="min-w-0 flex-1 rounded-[10px] border border-line bg-paper px-3 py-2 text-sm text-fg outline-none placeholder:text-muted focus:border-trust"
          />
          <button
            type="submit"
            className={
              embed
                ? "rounded-full border border-trust px-4 py-2 text-sm font-semibold text-fg"
                : "rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white"
            }
          >
            Ask
          </button>
        </span>
      </label>
      {error ? (
        <p className="mt-2 text-sm text-warn" role="alert">
          {error}
        </p>
      ) : null}
      <div className="mt-2 flex flex-wrap items-center gap-2">
        {SAMPLE_PROMPTS.map((line) => (
          <button key={line} type="button" onClick={() => onSample(line)} className="max-w-full rounded-full border border-line px-2.5 py-1 text-left text-[11px] text-muted hover:border-trust">
            {line}
          </button>
        ))}
      </div>
      <div className="mt-2 flex flex-wrap items-center gap-2">
        <button
          type="button"
          onClick={onMic}
          aria-pressed={listening}
          className="rounded-full border border-line px-3 py-1 text-xs font-semibold text-fg"
        >
          {listening ? "Stop mic" : micReady ? "Use mic" : "Mic unavailable"}
        </button>
        <button type="button" onClick={onToggle} aria-pressed={autonomous} className="rounded-full border border-line px-3 py-1 text-xs font-semibold text-fg">
          {autonomous ? "Autonomous on" : "Ask before each step"}
        </button>
        <span className="text-[11px] text-muted">Offers always need a human.</span>
        {frozen ? null : (
          <button type="button" onClick={onReplay} className="rounded-full border border-line px-3 py-1 text-xs font-semibold text-muted">
            Replay from the start
          </button>
        )}
        <span className="text-[11px] text-muted">Voice stays in this browser. No key, and no audio is uploaded.</span>
      </div>
      {micNote ? <p className="mt-2 text-xs text-muted">{micNote}</p> : null}
    </form>
  );
}

function CandidateDrawer({
  person,
  call,
  reduced,
  onClose,
}: {
  person: FloorCandidate;
  call: FloorCall | null;
  reduced: boolean;
  onClose: () => void;
}) {
  const titleId = useId();
  const closeRef = useRef<HTMLButtonElement>(null);
  useEffect(() => {
    closeRef.current?.focus();
  }, [person.id]);

  return (
    <motion.div
      className="fixed inset-0 z-[70] flex justify-end bg-ink/50"
      initial={reduced ? false : { opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      transition={{ duration: durations.fast, ease }}
      onClick={onClose}
    >
      <motion.aside
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        initial={reduced ? false : { x: 24, opacity: 0 }}
        animate={{ x: 0, opacity: 1 }}
        exit={reduced ? undefined : { x: 24, opacity: 0 }}
        transition={{ duration: durations.base, ease }}
        className="h-full w-full max-w-md overflow-y-auto border-l border-line bg-paper p-5 text-fg shadow-soft"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="kicker text-trust">Demo data</p>
            <h2 id={titleId} className="display mt-1 text-2xl text-fg">
              {person.name}
            </h2>
            <p className="mt-1 text-sm text-muted">Fictional candidate. Not a real person.</p>
          </div>
          <button ref={closeRef} type="button" onClick={onClose} className="rounded-full border border-line px-3 py-1 text-sm text-fg">
            Close
          </button>
        </div>

        <dl className="mt-5 grid grid-cols-2 gap-3 text-sm">
          <Fact k="Source" v={person.source} />
          <Fact k="City" v={person.city} />
          <Fact k="Notice" v={person.notice} />
          <Fact k="Match" v={String(person.match)} />
          <Fact k="Interest" v={INTEREST[person.interest]} />
          <Fact k="BGV" v={BGV[person.bgv]} tone={person.bgv} />
        </dl>

        <h3 className="mt-6 text-xs font-semibold uppercase tracking-[0.14em] text-muted">Scores</h3>
        <ul className="mt-2 space-y-1 text-sm">
          <Score label="AI interview" value={person.scores.ai} />
          <Score label="L1" value={person.scores.l1} />
          <Score label="L2" value={person.scores.l2} />
        </ul>
        <p className="mt-2 text-xs text-muted">Fictional demo scores. Not a real result and not a promise of an outcome.</p>

        <h3 className="mt-6 text-xs font-semibold uppercase tracking-[0.14em] text-muted">Call notes</h3>
        {call ? (
          <p className="mt-2 text-sm leading-relaxed text-fg">
            <span className="mono text-xs text-muted">
              {call.when} · {call.duration} · {call.outcome}
            </span>
            <span className="mt-1 block">{call.snippet}</span>
          </p>
        ) : (
          <p className="mt-2 text-sm text-muted">No sample call on this person yet.</p>
        )}

        {person.dropoutRisk != null ? (
          <p className="mt-4 rounded-[10px] border border-warn/40 bg-warn/10 px-3 py-2 text-sm text-fg">
            Dropout risk {person.dropoutRisk}. Sample alert only. The joining chat is not live.
          </p>
        ) : null}

        <h3 className="mt-6 text-xs font-semibold uppercase tracking-[0.14em] text-muted">Timeline</h3>
        <ol className="mt-2 space-y-2">
          {person.timeline.map((step) => (
            <li key={step.label} className="border-l-2 border-trust pl-3 text-sm text-fg">
              {step.label}
            </li>
          ))}
        </ol>
      </motion.aside>
    </motion.div>
  );
}

function Fact({ k, v, tone }: { k: string; v: string; tone?: BgvStatus }) {
  const color = tone === "verified" ? "text-verify" : tone === "flagged" ? "text-warn" : "text-fg";
  return (
    <div>
      <dt className="text-[11px] uppercase tracking-[0.12em] text-muted">{k}</dt>
      <dd className={`mt-0.5 font-medium ${color}`}>{v}</dd>
    </div>
  );
}

function Score({ label, value }: { label: string; value: number | null }) {
  return (
    <li className="flex items-center justify-between">
      <span className="text-muted">{label}</span>
      <span className="mono text-fg">{value == null ? "—" : value}</span>
    </li>
  );
}
