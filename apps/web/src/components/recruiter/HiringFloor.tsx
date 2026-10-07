"use client";

import { useEffect, useId, useMemo, useRef, useState, type RefObject } from "react";
import Link from "next/link";
import { useReducedMotion } from "framer-motion";
import { parseHiringPrompt, SAMPLE_PROMPTS, SHOWCASE_MS } from "./demo-data";
import { answerFloorQuestion, getHiringFloorData } from "./getHiringFloorData";
import { createHoloLab, type HoloLab } from "./holo-lab";
import type { AgentState, BgvStatus, FloorCandidate, FloorStageId, Interest, JobBrief } from "./types";
import "./taurus-floor.css";

type Variant = "full" | "embed" | "console" | "shot";
type Sheet = "filters" | "log" | "talk" | "metrics" | "dossier" | null;
type Bubble = { who: "you" | "ai"; text: string };

const ZONE_BY_STAGE: Record<FloorStageId, string> = {
  job: "Sourcing",
  sourcing: "Sourcing",
  calls: "AI Calls",
  ai: "AI Interview",
  l1: "L1",
  l2: "L2",
  human: "Human round",
  bgv: "Pre-BGV",
  offer: "Offer",
  joining: "Joining",
};

const BOT_ZONE: Record<string, string> = {
  scout: "Sourcing",
  caller: "AI Calls",
  interview: "AI Interview",
  l1: "L1",
  l2: "L2",
  scheduler: "Human round",
  bgv: "Pre-BGV",
  offer: "Offer",
  engagement: "Joining",
};

const BOT_SIGN: Record<string, string> = {
  scout: "SRC",
  caller: "CALL",
  interview: "INT",
  l1: "L1",
  l2: "L2",
  scheduler: "SCH",
  bgv: "BGV",
  offer: "OFR",
  engagement: "ENG",
};

const CANVAS_STATE: Record<AgentState, string> = {
  idle: "idle",
  working: "working",
  thinking: "thinking",
  approval: "waiting",
  error: "error",
};

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

const SOURCES = ["BrowseJobs pool", "Client file", "Email"] as const;

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

function callsign(name: string): string {
  return name
    .replace(/^Sample\s+/, "")
    .split(/\s+/)
    .map((part) => part[0] ?? "")
    .join("")
    .slice(0, 3)
    .toUpperCase();
}

function useMaxWidth(px: number): boolean {
  const [match, setMatch] = useState(false);
  useEffect(() => {
    const mq = window.matchMedia(`(max-width: ${px}px)`);
    const apply = () => setMatch(mq.matches);
    apply();
    mq.addEventListener("change", apply);
    return () => mq.removeEventListener("change", apply);
  }, [px]);
  return match;
}

function useClock(): Date | null {
  const [now, setNow] = useState<Date | null>(null);
  useEffect(() => {
    const tick = () => setNow(new Date());
    tick();
    const id = window.setInterval(tick, 1000);
    return () => window.clearInterval(id);
  }, []);
  return now;
}

export function HiringFloor({
  variant = "full",
  frozenAtMs = null,
  initialElapsedMs,
  credit = true,
}: {
  variant?: Variant;
  frozenAtMs?: number | null;
  initialElapsedMs?: number;
  /** The employers marketing page must not name Taurus. The demo and homepage may. */
  credit?: boolean;
}) {
  const reduced = useReducedMotion() ?? false;
  const reducedRef = useRef(reduced);
  reducedRef.current = reduced;
  const embed = variant === "embed";
  const frozen = frozenAtMs != null;
  const rootRef = useRef<HTMLDivElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const portraitRef = useRef<HTMLCanvasElement>(null);
  const labRef = useRef<HoloLab | null>(null);
  const logRef = useRef<HTMLDivElement>(null);
  const [elapsed, setElapsed] = useState(frozen ? frozenAtMs : (initialElapsedMs ?? SHOWCASE_MS));
  const [playToken, setPlayToken] = useState(0);
  const [autonomous, setAutonomous] = useState(false);
  const [brief, setBrief] = useState<JobBrief | null>(null);
  const [prompt, setPrompt] = useState("");
  const [talkText, setTalkText] = useState("");
  const [promptError, setPromptError] = useState<string | null>(null);
  const [sheet, setSheet] = useState<Sheet>(null);
  const [openId, setOpenId] = useState<string | null>(null);
  const [botId, setBotId] = useState<string | null>(null);
  const [stageFilter, setStageFilter] = useState<FloorStageId | "all">("all");
  const [sourceFilter, setSourceFilter] = useState<(typeof SOURCES)[number] | "all">("all");
  const [listening, setListening] = useState(false);
  const [handsFree, setHandsFree] = useState(false);
  const [mute, setMute] = useState(false);
  const [offerYes, setOfferYes] = useState(false);
  const [messages, setMessages] = useState<Bubble[]>([
    {
      who: "ai",
      text: "Demo data. Ask how many are at L2, who's interested, or BGV status for Asha. A person always releases the offer.",
    },
  ]);
  const muteRef = useRef(mute);
  muteRef.current = mute;
  const handsRef = useRef(handsFree);
  handsRef.current = handsFree;
  const recRef = useRef<SpeechRec | null>(null);
  const mid = useMaxWidth(1099);
  const narrow = useMaxWidth(760);
  const now = useClock();
  const titleId = useId();

  const elapsedRef = useRef(elapsed);
  elapsedRef.current = elapsed;

  useEffect(() => {
    if (frozen) return;
    let frame = 0;
    let last = 0;
    const origin = performance.now() - elapsedRef.current;
    const tick = (time: number) => {
      if (time - last > 200) {
        last = time;
        setElapsed(time - origin);
      }
      frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frame);
  }, [frozen, playToken]);

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key !== "Escape") return;
      setOpenId(null);
      setBotId(null);
      setSheet((current) => (current === "dossier" || current === "talk" || current === "metrics" ? null : current));
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const floor = useMemo(
    () => getHiringFloorData({ elapsedMs: elapsed, autonomous, brief }),
    [elapsed, autonomous, brief],
  );
  const floorRef = useRef(floor);
  floorRef.current = floor;

  const open = floor.candidates.find((person) => person.id === openId) ?? null;
  const bot = floor.agents.find((agent) => agent.id === botId) ?? null;
  const dossierOpen = Boolean(open || bot);

  useEffect(() => {
    const canvas = canvasRef.current;
    const root = rootRef.current;
    if (!canvas || !root) return;
    const lab = createHoloLab(canvas, {
      reduced: () => reducedRef.current,
      onSelect: (agent) => {
        if (!agent) {
          setOpenId(null);
          setBotId(null);
          setSheet((current) => (current === "dossier" ? null : current));
          return;
        }
        if (agent.id.startsWith("c-")) {
          setOpenId(agent.id.slice(2));
          setBotId(null);
        } else {
          setBotId(agent.id);
          setOpenId(null);
        }
        setSheet("dossier");
      },
    });
    labRef.current = lab;
    const observer = new ResizeObserver(() => lab.resize());
    observer.observe(root);
    return () => {
      observer.disconnect();
      lab.destroy();
      labRef.current = null;
    };
  }, []);

  useEffect(() => {
    const counts: Record<string, number> = {};
    for (const stage of floor.stages) {
      if (stage.id === "job") continue;
      counts[ZONE_BY_STAGE[stage.id].toUpperCase()] = stage.count;
    }
    labRef.current?.sync(
      [
        ...floor.agents.map((agent) => ({
          id: agent.id,
          name: agent.name,
          zone: BOT_ZONE[agent.id] ?? "Sourcing",
          status: CANVAS_STATE[agent.state],
          task: agent.task,
          progress: agent.progress,
          platform: "native",
          role: agent.task,
          callsign: BOT_SIGN[agent.id] ?? agent.name.slice(0, 3).toUpperCase(),
          color: agent.state === "approval" ? "#ffb627" : "#7ee8ff",
        })),
        ...floor.candidates.map((person) => ({
          id: `c-${person.id}`,
          name: person.name,
          zone: ZONE_BY_STAGE[person.stage],
          status: "active",
          task: person.timeline[person.timeline.length - 1]?.label ?? "",
          platform: "native",
          role: person.source,
          callsign: callsign(person.name),
          color: "#7ef0ff",
          token: true,
        })),
      ],
      counts,
    );
    if (openId) labRef.current?.select(`c-${openId}`);
    else if (botId) labRef.current?.select(botId);
  }, [floor, openId, botId]);

  useEffect(() => {
    labRef.current?.setSheet(narrow && sheet != null);
  }, [narrow, sheet]);

  useEffect(() => {
    labRef.current?.setPortrait(dossierOpen ? portraitRef.current : null);
  }, [dossierOpen, openId, botId]);

  useEffect(() => {
    logRef.current?.scrollTo({ top: logRef.current.scrollHeight });
  }, [messages, sheet]);

  function speak(text: string) {
    if (muteRef.current || typeof window === "undefined" || !window.speechSynthesis) return;
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = "en-IN";
    window.speechSynthesis.speak(utterance);
  }

  function submitLine(text: string) {
    const trimmed = text.replace(/\s+/g, " ").trim();
    if (trimmed.length < 8) {
      setPromptError("Name a role. Try: Hire 3 React developers in Bangalore, 2-4 yrs, notice under 30 days.");
      return;
    }
    setPromptError(null);
    const parsed = parseHiringPrompt(trimmed);
    const answer = parsed
      ? `Role set to ${parsed.title} · ${parsed.city} · ${parsed.openings} openings. The sample floor is running. Demo data. AI calls, pre-BGV, offers, and joining are not live yet.`
      : answerFloorQuestion(trimmed, floorRef.current);
    if (parsed) {
      setBrief(parsed);
      if (!frozen) {
        setElapsed(0);
        setPlayToken((value) => value + 1);
      }
    }
    setPrompt("");
    setTalkText("");
    setMessages((rows) => [...rows, { who: "you", text: trimmed }, { who: "ai", text: answer }]);
    speak(answer);
  }

  function toggleMic() {
    const Ctor = speechCtor();
    if (!Ctor) return;
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
      if (!said) return;
      if (handsRef.current) submitLine(said);
      else {
        setTalkText(said);
        setPrompt(said);
      }
    };
    rec.onerror = () => setListening(false);
    rec.onend = () => setListening(false);
    recRef.current = rec;
    try {
      rec.start();
      setListening(true);
    } catch {
      setListening(false);
    }
  }

  function openPerson(id: string) {
    setOpenId(id);
    setBotId(null);
    setSheet("dossier");
    labRef.current?.select(`c-${id}`);
  }

  function toggleSheet(next: Exclude<Sheet, null>) {
    setSheet((current) => (current === next ? null : next));
  }

  const people = floor.candidates.filter((person) => {
    if (stageFilter !== "all" && person.stage !== stageFilter) return false;
    if (sourceFilter !== "all" && person.source !== sourceFilter) return false;
    return true;
  });

  const timeLabel = now
    ? new Intl.DateTimeFormat("en-GB", {
        timeZone: "Asia/Kolkata",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hour12: false,
      }).format(now)
    : "--:--:--";
  const dateLabel = now
    ? new Intl.DateTimeFormat("en-GB", {
        timeZone: "Asia/Kolkata",
        weekday: "short",
        day: "2-digit",
        month: "short",
        year: "numeric",
      }).format(now)
    : "";

  const ticker = [...floor.activity.map((item) => `${item.time} ${item.agent} ${item.text}`), ...floor.calls.map((call) => `${call.name} ${call.outcome}`)];
  const tickerLoop = ticker.length ? [...ticker, ...ticker] : ["Demo data · not live yet"];
  const Title = variant === "full" ? "h1" : "p";
  const leftOpen = !mid || sheet === "filters";
  const rightOpen = !narrow || sheet === "log";
  const noticeOf = (id: string) => floor.candidates.find((person) => person.id === id)?.notice ?? "";

  return (
    <div ref={rootRef} className={`bj-lab is-${variant}`}>
      <canvas id="lab" ref={canvasRef} className="lab" aria-label="Hiring floor" />
      <div className="fx" id="fx-scan" />
      <div className="fx" id="fx-vig" />
      <div className="fx" id="fx-hex" />

      <header id="hud-top">
        <div className="brand">
          <BrandMark />
          <div>
            <Title className="brand-title">BrowseJobs AI Recruiter</Title>
            <p className="brand-sub">
              {credit ? (
                <>
                  Powered by <span className="accent">Taurus AI</span>
                </>
              ) : (
                "Demo data"
              )}
            </p>
          </div>
        </div>
        <div className="counts" aria-label="Hiring metrics">
          <Metric n={floor.metrics.sourced} label="CVs" short="CV" color="#00d4ff" />
          <Metric n={floor.metrics.callsMade} label="CALLS" short="CALL" color="#7ee8ff" />
          <Metric n={floor.metrics.interviewsCleared} label="CLEAR" short="CLR" color="#6ec8e8" />
          <Metric n={floor.metrics.offersWaiting} label="OFFER" short="OFR" color="#ffb627" />
        </div>
        <div className="hud-right">
          {embed ? (
            <Link href="/employers/mission-control-demo" className="full-demo">
              Open the full demo
            </Link>
          ) : null}
          <button type="button" className="usage-btn voice-btn" id="btn-voice" onClick={() => toggleSheet("talk")}>
            <MicIcon /> Talk
          </button>
          <button type="button" className={`usage-btn${sheet === "metrics" ? " on" : ""}`} onClick={() => toggleSheet("metrics")}>
            <span className="lg">Metrics</span>
            <span className="sm">Stats</span>
          </button>
          <div className="mode demo" title="Demo data. Every figure on this floor is sample data.">
            <i />
            DEMO DATA
          </div>
          <div className="clock">
            <div id="clock-time">
              {timeLabel} <small>IST</small>
            </div>
            <div id="clock-date">{dateLabel}</div>
          </div>
        </div>
      </header>

      <div id="ticker">
        <div className="tk-label demo">DEMO · SIMULATED</div>
        <div className="tk-track">
          <div id="tk-inner" className="bj-marquee">
            {tickerLoop.map((line, index) => (
              <span className="tk" key={`${line}-${index}`}>
                <b>Floor</b> {line}
              </span>
            ))}
          </div>
        </div>
      </div>

      <aside id="left-panel" className={`panel${leftOpen ? " open" : ""}`}>
        <div className="panel-head">
          Filters <span className="tag sim">Demo data</span>
        </div>
        <p className="job-line">
          {floor.job.title} · {floor.job.city} · {floor.job.openings} openings
        </p>
        <p className="sub-head">Job</p>
        <div className="chips">
          <span className="chip on">{floor.job.title}</span>
        </div>
        <p className="sub-head">Stage</p>
        <div className="chips">
          <button type="button" className={`chip${stageFilter === "all" ? " on" : ""}`} onClick={() => setStageFilter("all")}>
            All
          </button>
          {floor.stages
            .filter((stage) => stage.id !== "job")
            .map((stage) => (
              <button
                key={stage.id}
                type="button"
                className={`chip${stageFilter === stage.id ? " on" : ""}`}
                onClick={() => setStageFilter(stage.id)}
              >
                {stage.label}
              </button>
            ))}
        </div>
        <p className="sub-head">Source</p>
        <div className="chips">
          <button type="button" className={`chip${sourceFilter === "all" ? " on" : ""}`} onClick={() => setSourceFilter("all")}>
            All
          </button>
          {SOURCES.map((source) => (
            <button key={source} type="button" className={`chip${sourceFilter === source ? " on" : ""}`} onClick={() => setSourceFilter(source)}>
              {source}
            </button>
          ))}
        </div>
        <p className="sub-head">Stages</p>
        <ul aria-label="Stage counts" className="stage-counts">
          {floor.stages.map((stage) => (
            <li key={stage.id}>
              <span className="mono">{stage.count}</span>
              <span>{stage.label}</span>
              {stage.id === "offer" ? <span className="apr">Needs your approval</span> : stage.comingSoon ? <span className="soon">soon</span> : null}
            </li>
          ))}
        </ul>
        <div className="bj-human">
          <button type="button" aria-pressed={autonomous} onClick={() => setAutonomous((value) => !value)}>
            {autonomous ? "Autonomous on" : "Ask before each step"}
          </button>
          <p>Offers always need a human.</p>
          <p className="metric-note">AI calls, pre-BGV, offers, and joining chats are not live yet.</p>
        </div>
        <div className="roster" aria-label="Candidates">
          {people.map((person) => (
            <button key={person.id} type="button" className="r-item" aria-label={`Open ${person.name}`} onClick={() => openPerson(person.id)}>
              <i className="r-dot" style={{ color: person.interest === "interested" ? "#7ee8ff" : "#6ec8e8" }} />
              <span>
                <span className="r-name">{person.name}</span>
                <span className="r-sub">
                  {person.source} · {floor.stages.find((stage) => stage.id === person.stage)?.label}
                </span>
              </span>
              <span className="r-st">{INTEREST[person.interest]}</span>
            </button>
          ))}
        </div>
        <form
          className="bj-ask"
          onSubmit={(event) => {
            event.preventDefault();
            submitLine(prompt);
          }}
        >
          <label>
            Tell the recruiter
            <span className="row">
              <input
                value={prompt}
                onChange={(event) => setPrompt(event.target.value)}
                aria-label="Tell the recruiter"
                placeholder="Hire 3 React developers in Bangalore…"
              />
              <button type="submit">Ask</button>
            </span>
          </label>
          {promptError ? (
            <p className="metric-note" role="alert">
              {promptError}
            </p>
          ) : null}
        </form>
      </aside>

      <aside id="right-panel" className={`panel${rightOpen ? " open" : ""}`}>
        <div className="panel-head">
          Mission log <span className="tag sim">Simulated</span>
        </div>
        <section aria-label="Calls" className="log">
          {floor.calls.length === 0 ? <p className="empty-note">No sample calls yet on this pass.</p> : null}
          {floor.calls.map((call) => (
            <article key={call.id} className="li">
              <time className="li-time">{call.when.replace("Day 1 · ", "")}</time>
              <div>
                <div className="li-head">
                  <span className="li-agent">{call.name}</span>
                  <span className="li-st" style={{ color: call.outcome === "Interested" ? "#7ee8ff" : "#ffb627" }}>
                    {call.outcome}
                  </span>
                </div>
                <p className="li-msg">
                  {call.duration} · notice {noticeOf(call.candidateId)} · {call.snippet}
                </p>
              </div>
            </article>
          ))}
          {floor.activity.map((item) => (
            <article key={item.id} className="li">
              <time className="li-time">{item.time}</time>
              <div>
                <div className="li-head">
                  <span className="li-agent">{item.agent}</span>
                </div>
                <p className="li-msg">{item.text}</p>
              </div>
            </article>
          ))}
        </section>
      </aside>

      {dossierOpen ? (
        <aside id="agent-panel" className="panel open" role="dialog" aria-modal="true" aria-labelledby={titleId}>
          <div className="panel-head">
            {open ? "Candidate" : "Recruiter bot"}
            <button
              type="button"
              className="close-btn"
              onClick={() => {
                setOpenId(null);
                setBotId(null);
                setSheet((current) => (current === "dossier" ? null : current));
              }}
            >
              ×
            </button>
          </div>
          {open ? <CandidateDossier person={open} titleId={titleId} call={floor.calls.find((row) => row.candidateId === open.id) ?? null} portraitRef={portraitRef} /> : null}
          {bot ? <BotDossier name={bot.name} task={bot.task} state={bot.state} progress={bot.progress} titleId={titleId} portraitRef={portraitRef} /> : null}
        </aside>
      ) : null}

      <aside id="usage-panel" className={`panel${sheet === "metrics" ? " open" : ""}`} hidden={sheet !== "metrics"}>
        <div className="panel-head">
          Metrics <span className="tag sim">Demo data</span>
          <button type="button" className="close-btn" onClick={() => setSheet(null)}>
            ×
          </button>
        </div>
        <div className="up-body">
          <p className="metric-note">
            Counted from the sample people on this floor. {floor.metrics.elapsedLabel} of a {floor.metrics.targetLabel}. Demo clock, not a promise that a hire finishes in 3 days.
          </p>
          <MetricCard title="Sourcing" rows={[["CVs sourced", floor.metrics.sourced], ["Ranked", floor.metrics.ranked]]} />
          <MetricCard
            title="AI calls · soon"
            rows={[
              ["Made", floor.metrics.callsMade],
              ["Connected", floor.metrics.connected],
              ["Interested", floor.metrics.interested],
              ["Not interested", floor.metrics.notInterested],
              ["No answer", floor.metrics.noAnswer],
              ["Avg duration", floor.metrics.avgCallDuration],
            ]}
          />
          <MetricCard
            title="Interviews · clear mark 75"
            rows={[
              ["AI interviews taken", floor.metrics.interviewsTaken],
              ["AI interviews cleared", floor.metrics.interviewsCleared],
              ["L1 cleared", floor.metrics.l1Cleared],
              ["L2 cleared", floor.metrics.l2Cleared],
              ["Human round booked", floor.metrics.humanBooked],
            ]}
          />
          <MetricCard
            title="Pre-BGV · soon"
            rows={[
              ["Verified", floor.metrics.bgvVerified],
              ["Pending", floor.metrics.bgvPending],
              ["Flagged", floor.metrics.bgvFlagged],
            ]}
            note="EPFO and DigiLocker are not connected."
          />
          <MetricCard
            title="Offer · soon"
            rows={[
              ["Awaiting approval", floor.metrics.offersWaiting],
              ["Released", floor.metrics.offersReleased],
              ["Accepted", floor.metrics.offersAccepted],
              ["Joined", floor.metrics.joined],
              ["Dropout alerts", floor.metrics.dropoutAlerts],
            ]}
            note="A person always releases the offer. Nothing is emailed."
          />
          {offerYes ? <p className="metric-note">Marked yes in this demo. Nothing was emailed. The offer desk still needs a person.</p> : null}
          <button type="button" className="usage-btn" onClick={() => setOfferYes(true)}>
            Record a yes for the sample offer
          </button>
        </div>
      </aside>

      <section id="voice-panel" className={`panel${sheet === "talk" ? " open" : ""}`} hidden={sheet !== "talk"} aria-label="Talk to Recruiter">
        <div className="panel-head">
          Talk to Recruiter
          <button type="button" className="close-btn" onClick={() => setSheet((current) => (current === "talk" ? null : current))}>
            ×
          </button>
        </div>
        <div className={`vp-status${listening ? " listening" : ""}`}>
          <i />
          <span id="vp-state">{listening ? "Listening…" : "Ready"}</span>
          <span className="vp-hint">Demo data · stays in this browser</span>
        </div>
        <div className="vp-log" ref={logRef}>
          {messages.map((bubble, index) => (
            <div key={`${bubble.who}-${index}`} className={`vm ${bubble.who}`}>
              <small>{bubble.who === "you" ? "You" : "Recruiter"}</small>
              {bubble.text}
            </div>
          ))}
        </div>
        <form
          className="vp-form"
          onSubmit={(event) => {
            event.preventDefault();
            submitLine(talkText);
          }}
        >
          <button type="button" className={`vp-mic${listening ? " on" : ""}`} aria-label="Microphone" aria-pressed={listening} onClick={toggleMic}>
            <MicIcon />
          </button>
          <input
            id="vp-input"
            value={talkText}
            onChange={(event) => setTalkText(event.target.value)}
            placeholder="Ask about the floor…"
            aria-label="Ask the recruiter"
          />
          <button type="submit" className="vp-send">
            Ask
          </button>
        </form>
        <div className="vp-opts">
          <label>
            <input type="checkbox" checked={handsFree} onChange={(event) => setHandsFree(event.target.checked)} /> Hands-free
          </label>
          <label>
            <input type="checkbox" checked={mute} onChange={(event) => setMute(event.target.checked)} /> Mute replies
          </label>
          {SAMPLE_PROMPTS.slice(0, 1).map((line) => (
            <button key={line} type="button" className="vp-link" onClick={() => submitLine(line)}>
              {line}
            </button>
          ))}
        </div>
      </section>

      <div id="legend">
        <span style={{ color: "#00d4ff" }}>
          <i /> Working
        </span>
        <span style={{ color: "#b591ff" }}>
          <i /> Thinking
        </span>
        <span style={{ color: "#ffb627" }}>
          <i /> Needs approval
        </span>
        <span style={{ color: "#ff3d57" }}>
          <i /> Error
        </span>
        <span style={{ color: "#34e8a8" }}>
          <i /> Active
        </span>
        <span style={{ color: "#6ec8e8" }}>
          <i /> Idle
        </span>
        <span className="hint">Click agent · Drag · Scroll zoom</span>
        <button type="button" id="btn-recenter" onClick={() => labRef.current?.recenter()}>
          Recenter
        </button>
      </div>

      <nav id="mnav" aria-label="Floor sections">
        <button type="button" className={sheet === "filters" ? "on" : ""} onClick={() => toggleSheet("filters")}>
          <NavIcon d="M8 8a3 3 0 1 0 0.01 0M17 9a2.4 2.4 0 1 0 0.01 0M2.5 19c.8-3.4 3-5 5.5-5s4.7 1.6 5.5 5M14 18.5c.5-2.4 1.8-3.6 3.4-3.6 1.7 0 3 1.2 3.6 3.6" />
          Candidates
        </button>
        <button type="button" className={sheet === "log" ? "on" : ""} onClick={() => toggleSheet("log")}>
          <NavIcon d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5" />
          Mission log
        </button>
        <button type="button" className={sheet === "talk" ? "on" : ""} onClick={() => toggleSheet("talk")}>
          <NavIcon d="M9 3h6v11a3 3 0 0 1-6 0zM5 11a7 7 0 0 0 14 0M12 18v3" />
          Talk
        </button>
        <button type="button" className={sheet === "metrics" ? "on" : ""} onClick={() => toggleSheet("metrics")}>
          <NavIcon d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
          Metrics
        </button>
        <button type="button" onClick={() => labRef.current?.recenter()}>
          <NavIcon d="M12 5a7 7 0 1 0 0.01 0M12 10a2 2 0 1 0 0.01 0M12 2v3M12 19v3M2 12h3M19 12h3" />
          Recenter
        </button>
      </nav>
    </div>
  );
}

function Metric({ n, label, short, color }: { n: number; label: string; short: string; color: string }) {
  return (
    <span className="cnt" style={{ color }}>
      <i />
      <b>{n}</b>
      <span>{label}</span>
      <s>{short}</s>
    </span>
  );
}

function MetricCard({ title, rows, note }: { title: string; rows: [string, number | string][]; note?: string }) {
  return (
    <div className="uc">
      <div className="uc-top">
        <span className="uc-name">{title}</span>
        <span className="chip-st st-no-usage-api">Demo</span>
      </div>
      <div className="uc-grid">
        {rows.map(([label, value]) => (
          <div key={label}>
            <div className="uc-k">{label}</div>
            <div className="uc-v">{value}</div>
          </div>
        ))}
      </div>
      {note ? <p className="uc-d">{note}</p> : null}
    </div>
  );
}

function CandidateDossier({
  person,
  titleId,
  call,
  portraitRef,
}: {
  person: FloorCandidate;
  titleId: string;
  call: { when: string; duration: string; outcome: string; snippet: string } | null;
  portraitRef: RefObject<HTMLCanvasElement | null>;
}) {
  return (
    <div className="ap-body">
      <div className="ap-top">
        <canvas id="ap-portrait" ref={portraitRef} width={72} height={92} />
        <div>
          <span className="tag sim">Demo data</span>
          <h2 id={titleId} className="ap-name">
            {person.name}
          </h2>
          <p className="ap-val">Fictional candidate. Not a real person.</p>
          <div className="ap-badges">
            <span className="badge zone">{person.source}</span>
            <span className="badge">{BGV[person.bgv]}</span>
          </div>
        </div>
      </div>
      <div className="ap-section ap-row">
        <div>
          <div className="ap-label">City</div>
          <div className="ap-val">{person.city}</div>
        </div>
        <div>
          <div className="ap-label">Notice</div>
          <div className="ap-val">{person.notice}</div>
        </div>
        <div>
          <div className="ap-label">Match</div>
          <div className="ap-val mono">{person.match}</div>
        </div>
        <div>
          <div className="ap-label">Interest</div>
          <div className="ap-val">{INTEREST[person.interest]}</div>
        </div>
      </div>
      <div className="ap-section">
        <div className="ap-label">Scores · clear mark 75 · fictional</div>
        <div className="ap-row">
          <Score label="AI interview" value={person.scores.ai} />
          <Score label="L1" value={person.scores.l1} />
          <Score label="L2" value={person.scores.l2} />
        </div>
      </div>
      <div className="ap-section">
        <div className="ap-label">Call</div>
        {call ? (
          <p className="ap-val">
            {call.when} · {call.duration} · {call.outcome}. {call.snippet}
          </p>
        ) : (
          <p className="ap-val">No sample call on this person yet.</p>
        )}
      </div>
      {person.dropoutRisk != null ? (
        <p className="ap-val">Dropout risk {person.dropoutRisk}. Sample alert. The joining chat is not live.</p>
      ) : null}
      <div className="ap-section">
        <div className="ap-label">Timeline</div>
        <ol className="ap-act">
          {person.timeline.map((step) => (
            <li key={step.label} className="act">
              <i style={{ color: "#00d4ff" }} />
              <p>{step.label}</p>
            </li>
          ))}
        </ol>
      </div>
    </div>
  );
}

function BotDossier({
  name,
  task,
  state,
  progress,
  titleId,
  portraitRef,
}: {
  name: string;
  task: string;
  state: AgentState;
  progress: number;
  titleId: string;
  portraitRef: RefObject<HTMLCanvasElement | null>;
}) {
  const label = state === "approval" ? "Needs your approval" : state;
  const tone = state === "approval" ? "#ffb627" : state === "error" ? "#ff3d57" : state === "thinking" ? "#b591ff" : "#00d4ff";
  return (
    <div className="ap-body">
      <div className="ap-top">
        <canvas id="ap-portrait" ref={portraitRef} width={72} height={92} />
        <div>
          <span className="tag sim">Demo data</span>
          <h2 id={titleId} className="ap-name">
            {name}
          </h2>
          <p className="ap-status" style={{ color: tone }}>
            <i /> {label}
          </p>
        </div>
      </div>
      <div className="ap-section">
        <div className="ap-label">Now</div>
        <p className="ap-val strong">{task}</p>
        <div className="ap-prog">
          <div className="ap-bar">
            <i style={{ width: `${Math.round(progress)}%` }} />
          </div>
          <span id="ap-pct">{Math.round(progress)}%</span>
        </div>
      </div>
      <p className="metric-note">A person always releases the offer. This bot does not email anyone.</p>
    </div>
  );
}

function Score({ label, value }: { label: string; value: number | null }) {
  const cleared = value != null && value >= 75;
  return (
    <div>
      <div className="ap-label">{label}</div>
      <div className="ap-val mono">
        {value == null ? "—" : value}
        {cleared ? " · cleared" : ""}
      </div>
    </div>
  );
}

function NavIcon({ d }: { d: string }) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d={d} fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function BrandMark() {
  return (
    <svg viewBox="0 0 32 32" aria-hidden="true">
      <circle cx="16" cy="16" r="14" fill="none" stroke="#00d4ff" strokeWidth="1.4" />
      <circle cx="16" cy="16" r="6" fill="none" stroke="#c9a227" strokeWidth="1.2" />
      <circle cx="16" cy="16" r="2" fill="#00d4ff" />
    </svg>
  );
}

function MicIcon() {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" width="16" height="16">
      <rect x="9" y="3" width="6" height="11" rx="3" fill="none" stroke="currentColor" strokeWidth="1.6" />
      <path d="M6 11a6 6 0 0 0 12 0M12 17v4" fill="none" stroke="currentColor" strokeWidth="1.6" />
    </svg>
  );
}
