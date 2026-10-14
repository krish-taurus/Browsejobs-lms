"use client";

import { useCallback, useEffect, useId, useRef, useState, type RefObject } from "react";
import Link from "next/link";
import { useReducedMotion } from "framer-motion";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { employerApi, type RecruiterFloorAgent, type RecruiterFloorData, type RecruiterFloorPerson } from "@/lib/employer";
import { createHoloLab, type HoloLab } from "./holo-lab";
import "./taurus-floor.css";

/**
 * The AI Recruiter floor on live data, laid out as the hiring pipeline the
 * agents run: job intake on WhatsApp → shortlist → BGV → final shortlist →
 * interview invites → L1 → L2 → client report.
 *
 * Same hologram and chrome as the public demo (HiringFloor), but every
 * agent, person, count and log line comes from the workspace's real rows
 * (GET recruiter-floor, refreshed every 15 seconds). Agents that are not
 * built yet are labelled so, never animated as if working.
 */

const REFRESH_MS = 15_000;

const SIGN: Record<string, string> = {
  intake: "JOB",
  shortlist: "SRT",
  bgv: "BGV",
  final: "FIN",
  invites: "MSG",
  l1: "L1",
  l2: "L2",
  report: "RPT",
};

const CANVAS_STATE: Record<RecruiterFloorAgent["state"], string> = {
  idle: "idle",
  working: "working",
  thinking: "thinking",
  approval: "waiting",
  error: "error",
};

function initials(name: string): string {
  return name.split(/\s+/).map((p) => p[0] ?? "").join("").slice(0, 3).toUpperCase();
}

function timeOf(iso: string | null): string {
  if (!iso) return "";
  return new Intl.DateTimeFormat("en-GB", { timeZone: "Asia/Kolkata", hour: "2-digit", minute: "2-digit", hour12: false }).format(new Date(iso));
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

type Sheet = "filters" | "log" | "metrics" | "dossier" | null;

export function LiveRecruiterFloor({ embedded = false }: { embedded?: boolean } = {}) {
  const { workspace } = useWorkspace();
  const reduced = useReducedMotion() ?? false;
  const reducedRef = useRef(reduced);
  reducedRef.current = reduced;
  const rootRef = useRef<HTMLDivElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const portraitRef = useRef<HTMLCanvasElement>(null);
  const labRef = useRef<HoloLab | null>(null);
  const [data, setData] = useState<RecruiterFloorData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [jobId, setJobId] = useState<number | null>(null);
  const [zoneFilter, setZoneFilter] = useState<string>("all");
  const [sourceFilter, setSourceFilter] = useState<string>("all");
  const [sheet, setSheet] = useState<Sheet>(null);
  const [openId, setOpenId] = useState<string | null>(null);
  const [botId, setBotId] = useState<string | null>(null);
  const mid = useMaxWidth(1099);
  const narrow = useMaxWidth(760);
  const now = useClock();
  const titleId = useId();

  const load = useCallback(() => {
    employerApi
      .recruiterFloor(workspace.id, jobId)
      .then((res) => {
        setData(res.data);
        setError(null);
      })
      .catch(() => setError("Could not refresh the floor. Retrying…"));
  }, [workspace.id, jobId]);

  useEffect(() => {
    load();
    const id = window.setInterval(load, REFRESH_MS);
    return () => window.clearInterval(id);
  }, [load]);

  useEffect(() => {
    const canvas = canvasRef.current;
    const root = rootRef.current;
    if (!canvas || !root) return;
    const lab = createHoloLab(canvas, {
      live: true,
      reduced: () => reducedRef.current,
      onSelect: (agent) => {
        if (!agent) {
          setOpenId(null);
          setBotId(null);
          setSheet((current) => (current === "dossier" ? null : current));
          return;
        }
        if (agent.id.startsWith("p-")) {
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

  const zoneLabel = useCallback((id: string) => data?.zones.find((z) => z.id === id)?.label ?? id, [data]);

  useEffect(() => {
    if (!data) return;
    const counts: Record<string, number> = {};
    for (const zone of data.zones) counts[zone.label.toUpperCase()] = zone.count;
    labRef.current?.sync(
      [
        ...data.agents.map((agent) => ({
          id: agent.id,
          name: agent.live ? agent.name : `${agent.name} · not live`,
          zone: zoneLabel(agent.zone),
          status: CANVAS_STATE[agent.state],
          task: agent.task,
          progress: agent.progress,
          platform: "native",
          role: agent.task,
          callsign: SIGN[agent.id] ?? agent.name.slice(0, 3).toUpperCase(),
          color: !agent.live ? "#5b6b80" : agent.state === "approval" ? "#ffb627" : "#7ee8ff",
        })),
        ...data.people.map((person) => ({
          id: `p-${person.id}`,
          name: person.name,
          zone: zoneLabel(person.zone),
          status: "active",
          task: person.timeline[person.timeline.length - 1]?.label ?? "",
          platform: "native",
          role: person.source,
          callsign: initials(person.name),
          color: "#7ef0ff",
          token: true,
        })),
      ],
      counts,
    );
    if (openId) labRef.current?.select(`p-${openId}`);
    else if (botId) labRef.current?.select(botId);
  }, [data, openId, botId, zoneLabel]);

  useEffect(() => {
    labRef.current?.setSheet(narrow && sheet != null);
  }, [narrow, sheet]);

  const open = data?.people.find((p) => p.id === openId) ?? null;
  const bot = data?.agents.find((a) => a.id === botId) ?? null;
  const dossierOpen = Boolean(open || bot);

  useEffect(() => {
    labRef.current?.setPortrait(dossierOpen ? portraitRef.current : null);
  }, [dossierOpen, openId, botId]);

  function toggleSheet(next: Exclude<Sheet, null>) {
    setSheet((current) => (current === next ? null : next));
  }

  function openPerson(id: string) {
    setOpenId(id);
    setBotId(null);
    setSheet("dossier");
    labRef.current?.select(`p-${id}`);
  }

  const sources = data ? Array.from(new Set(data.people.map((p) => p.source))) : [];
  const people = (data?.people ?? []).filter(
    (p) => (zoneFilter === "all" || p.zone === zoneFilter) && (sourceFilter === "all" || p.source === sourceFilter),
  );
  const timeLabel = now
    ? new Intl.DateTimeFormat("en-GB", { timeZone: "Asia/Kolkata", hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: false }).format(now)
    : "--:--:--";
  const dateLabel = now
    ? new Intl.DateTimeFormat("en-GB", { timeZone: "Asia/Kolkata", weekday: "short", day: "2-digit", month: "short", year: "numeric" }).format(now)
    : "";
  const ticker = (data?.activity ?? []).slice(0, 12).map((row) => `${timeOf(row.at)} ${row.agent} · ${row.text}`);
  const tickerLoop = ticker.length ? [...ticker, ...ticker] : ["Waiting for the first job. Scan the WhatsApp QR on the AI Recruiter voice page to post one."];
  const leftOpen = !mid || sheet === "filters";
  const rightOpen = !narrow || sheet === "log";

  return (
    <div ref={rootRef} className={`bj-lab ${embedded ? "is-embed" : "is-console"}`}>
      <canvas id="lab" ref={canvasRef} className="lab" aria-label="AI Recruiter floor" />
      <div className="fx" id="fx-scan" />
      <div className="fx" id="fx-vig" />
      <div className="fx" id="fx-hex" />

      <header id="hud-top">
        <div className="brand">
          <BrandMark />
          <div>
            <p className="brand-title">BrowseJobs AI Recruiter</p>
            <p className="brand-sub">{data ? `${data.company} · live` : "Loading…"}</p>
          </div>
        </div>
        <div className="counts" aria-label="Hiring metrics">
          <Metric n={data?.kpis.jobs_live ?? 0} label="JOBS LIVE" short="JOB" color="#00d4ff" />
          <Metric n={data?.kpis.shortlisted ?? 0} label="SHORTLISTED" short="SRT" color="#7ee8ff" />
          <Metric n={data?.kpis.interviews ?? 0} label="INTERVIEWS" short="INT" color="#6ec8e8" />
          <Metric n={data?.kpis.cleared ?? 0} label="CLEARED" short="CLR" color="#34e8a8" />
        </div>
        <div className="hud-right">
          <button type="button" className={`usage-btn${sheet === "metrics" ? " on" : ""}`} onClick={() => toggleSheet("metrics")}>
            <span className="lg">Agents</span>
            <span className="sm">Agents</span>
          </button>
          <div className="mode" title="Live data from your workspace, refreshed every 15 seconds." style={{ color: "#34e8a8", borderColor: "rgba(52,232,168,.45)" }}>
            <i style={{ background: "#34e8a8" }} />
            LIVE
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
        <div className="tk-label" style={{ background: "#34e8a8", color: "#04131c" }}>LIVE</div>
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
          Filters <span className="tag">Live</span>
        </div>
        {error ? <p className="metric-note" role="alert">{error}</p> : null}
        <p className="job-line">
          {data?.job ? `${data.job.title} · ${data.job.where} · ${data.job.openings} openings · ${data.job.experience}` : "No job yet. Post one on WhatsApp."}
        </p>
        <p className="sub-head">Job</p>
        <div className="chips">
          {(data?.jobs ?? []).map((job) => (
            <button
              key={job.id}
              type="button"
              className={`chip${data?.job?.id === job.id ? " on" : ""}`}
              onClick={() => {
                setJobId(job.id);
                setOpenId(null);
              }}
            >
              {job.title}
              {job.status !== "published" ? ` · ${job.status}` : ""}
            </button>
          ))}
        </div>
        <p className="sub-head">Step</p>
        <div className="chips">
          <button type="button" className={`chip${zoneFilter === "all" ? " on" : ""}`} onClick={() => setZoneFilter("all")}>
            All
          </button>
          {(data?.zones ?? []).map((zone) => (
            <button key={zone.id} type="button" className={`chip${zoneFilter === zone.id ? " on" : ""}`} onClick={() => setZoneFilter(zone.id)}>
              {zone.label}
            </button>
          ))}
        </div>
        {sources.length > 0 ? (
          <>
            <p className="sub-head">Source</p>
            <div className="chips">
              <button type="button" className={`chip${sourceFilter === "all" ? " on" : ""}`} onClick={() => setSourceFilter("all")}>
                All
              </button>
              {sources.map((source) => (
                <button key={source} type="button" className={`chip${sourceFilter === source ? " on" : ""}`} onClick={() => setSourceFilter(source)}>
                  {source}
                </button>
              ))}
            </div>
          </>
        ) : null}
        <p className="sub-head">Steps</p>
        <ul aria-label="People in each step" className="stage-counts">
          {(data?.zones ?? []).map((zone) => (
            <li key={zone.id}>
              <span className="mono">{zone.count}</span>
              <span>{zone.label}</span>
              {zone.id === "bgv" ? <span className="apr">Not connected</span> : null}
            </li>
          ))}
        </ul>
        <div className="roster" aria-label="Candidates">
          {people.length === 0 ? <p className="empty-note">{data?.job ? "Nobody in this step yet." : "Candidates appear here once a job is live."}</p> : null}
          {people.map((person) => (
            <button key={person.id} type="button" className="r-item" aria-label={`Open ${person.name}`} onClick={() => openPerson(person.id)}>
              <i className="r-dot" style={{ color: "#7ee8ff" }} />
              <span>
                <span className="r-name">{person.name}</span>
                <span className="r-sub">
                  {person.source} · {zoneLabel(person.zone)}
                </span>
              </span>
              <span className="r-st">{person.match != null ? `${person.match}` : ""}</span>
            </button>
          ))}
        </div>
        {data?.job ? (
          <p className="metric-note">
            <Link href={`/employer/jobs/${data.job.id}?tab=shortlist`} style={{ color: "#7ee8ff" }}>
              Open this job&apos;s AI shortlist →
            </Link>
          </p>
        ) : null}
      </aside>

      <aside id="right-panel" className={`panel${rightOpen ? " open" : ""}`}>
        <div className="panel-head">
          Mission log <span className="tag">Live</span>
        </div>
        <section aria-label="What just happened" className="log">
          {(data?.activity ?? []).length === 0 ? <p className="empty-note">Nothing yet. The log fills in as the agents work.</p> : null}
          {(data?.activity ?? []).map((item) => (
            <article key={item.id} className="li">
              <time className="li-time">{timeOf(item.at)}</time>
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
            {open ? "Candidate" : "Agent"}
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
          {open ? <PersonDossier person={open} zone={zoneLabel(open.zone)} titleId={titleId} portraitRef={portraitRef} /> : null}
          {bot ? <AgentDossier agent={bot} titleId={titleId} portraitRef={portraitRef} /> : null}
        </aside>
      ) : null}

      <aside id="usage-panel" className={`panel${sheet === "metrics" ? " open" : ""}`} hidden={sheet !== "metrics"}>
        <div className="panel-head">
          Agents <span className="tag">Live</span>
          <button type="button" className="close-btn" onClick={() => setSheet(null)}>
            ×
          </button>
        </div>
        <div className="up-body">
          {(data?.agents ?? []).map((agent) => (
            <div key={agent.id} className="uc">
              <div className="uc-top">
                <span className="uc-name">{agent.name}</span>
                <span className="chip-st st-no-usage-api">{agent.live ? agent.state : "not live yet"}</span>
              </div>
              <p className="uc-d">{agent.task}</p>
              {agent.metrics.length > 0 ? (
                <div className="uc-grid">
                  {agent.metrics.map((m) => (
                    <div key={m.label}>
                      <div className="uc-k">{m.label}</div>
                      <div className="uc-v">{m.value ?? "—"}</div>
                    </div>
                  ))}
                </div>
              ) : null}
            </div>
          ))}
        </div>
      </aside>

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
        <span style={{ color: "#5b6b80" }}>
          <i /> Not live yet
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
          Candidates
        </button>
        <button type="button" className={sheet === "log" ? "on" : ""} onClick={() => toggleSheet("log")}>
          Mission log
        </button>
        <button type="button" className={sheet === "metrics" ? "on" : ""} onClick={() => toggleSheet("metrics")}>
          Agents
        </button>
        <button type="button" onClick={() => labRef.current?.recenter()}>
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

function PersonDossier({
  person,
  zone,
  titleId,
  portraitRef,
}: {
  person: RecruiterFloorPerson;
  zone: string;
  titleId: string;
  portraitRef: RefObject<HTMLCanvasElement | null>;
}) {
  return (
    <div className="ap-body">
      <div className="ap-top">
        <canvas id="ap-portrait" ref={portraitRef} width={72} height={92} />
        <div>
          <span className="tag">Live</span>
          <h2 id={titleId} className="ap-name">
            {person.name}
          </h2>
          <div className="ap-badges">
            <span className="badge zone">{person.source}</span>
            <span className="badge">{zone}</span>
          </div>
        </div>
      </div>
      <div className="ap-section ap-row">
        <div>
          <div className="ap-label">Match</div>
          <div className="ap-val mono">{person.match ?? "—"}</div>
        </div>
        <div>
          <div className="ap-label">Screen</div>
          <div className="ap-val mono">{person.scores.screen ?? "—"}</div>
        </div>
        <div>
          <div className="ap-label">L1</div>
          <div className="ap-val mono">{person.scores.l1 ?? "—"}</div>
        </div>
        <div>
          <div className="ap-label">L2</div>
          <div className="ap-val mono">{person.scores.l2 ?? "—"}</div>
        </div>
      </div>
      {person.facts.length > 0 ? <p className="ap-val">{person.facts.join(" · ")}</p> : null}
      {person.matched_skills.length + person.missing_skills.length > 0 ? (
        <div className="ap-section">
          <div className="ap-label">Skills</div>
          <p className="ap-val">
            {person.matched_skills.join(", ")}
            {person.missing_skills.length > 0 ? ` · missing ${person.missing_skills.join(", ")}` : ""}
          </p>
        </div>
      ) : null}
      <div className="ap-section">
        <div className="ap-label">Timeline</div>
        <ol className="ap-act">
          {person.timeline.map((step) => (
            <li key={`${step.label}-${step.at}`} className="act">
              <i style={{ color: "#00d4ff" }} />
              <p>
                {timeOf(step.at)} {step.label}
              </p>
            </li>
          ))}
        </ol>
      </div>
    </div>
  );
}

function AgentDossier({ agent, titleId, portraitRef }: { agent: RecruiterFloorAgent; titleId: string; portraitRef: RefObject<HTMLCanvasElement | null> }) {
  const label = !agent.live ? "Not live yet" : agent.state === "approval" ? "Needs attention" : agent.state;
  const tone = !agent.live ? "#5b6b80" : agent.state === "approval" ? "#ffb627" : agent.state === "thinking" ? "#b591ff" : "#00d4ff";
  return (
    <div className="ap-body">
      <div className="ap-top">
        <canvas id="ap-portrait" ref={portraitRef} width={72} height={92} />
        <div>
          <span className="tag">Live</span>
          <h2 id={titleId} className="ap-name">
            {agent.name}
          </h2>
          <p className="ap-status" style={{ color: tone }}>
            <i /> {label}
          </p>
        </div>
      </div>
      <div className="ap-section">
        <div className="ap-label">Now</div>
        <p className="ap-val strong">{agent.task}</p>
        <div className="ap-prog">
          <div className="ap-bar">
            <i style={{ width: `${Math.round(agent.progress)}%` }} />
          </div>
          <span id="ap-pct">{Math.round(agent.progress)}%</span>
        </div>
      </div>
      {agent.metrics.length > 0 ? (
        <div className="ap-section ap-row">
          {agent.metrics.map((m) => (
            <div key={m.label}>
              <div className="ap-label">{m.label}</div>
              <div className="ap-val mono">{m.value ?? "—"}</div>
            </div>
          ))}
        </div>
      ) : null}
    </div>
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
