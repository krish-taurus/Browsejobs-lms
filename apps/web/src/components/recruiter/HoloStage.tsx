"use client";

import { LayoutGroup, motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import type { AgentState, FloorAgent, FloorStageId, StageCount } from "./types";

function ink(state: AgentState): string {
  if (state === "error") return "var(--bj-warn)";
  if (state === "approval") return "var(--bj-amber)";
  if (state === "idle") return "var(--bj-muted)";
  return "var(--bj-trust)";
}

function Hologram({ agent, reduced }: { agent: FloorAgent; reduced: boolean }) {
  const color = ink(agent.state);
  const bob = !reduced && (agent.state === "working" || agent.state === "thinking");
  return (
    <div className="flex flex-col items-center">
      <motion.div
        animate={bob ? { y: [0, -6, 0] } : { y: 0 }}
        transition={bob ? { duration: durations.slower * 2.4, repeat: Infinity, ease } : { duration: 0 }}
      >
        <svg width="26" height="44" viewBox="0 0 30 52" aria-hidden>
          <ellipse cx="15" cy="48" rx="10" ry="3" fill={color} opacity="0.45" />
          <path d="M9 30c0-7 12-7 12 0l2 14H7l2-14z" fill={color} opacity="0.9" />
          <circle cx="15" cy="14" r="6.5" fill={color} />
          <circle cx="15" cy="14" r="2.2" fill="white" opacity="0.92" />
        </svg>
      </motion.div>
      <span className="mono max-w-[4.2rem] truncate text-[9px] uppercase tracking-[0.12em] text-fg">{agent.name}</span>
    </div>
  );
}

export function HoloStage({
  stages,
  agents,
  active,
}: {
  stages: StageCount[];
  agents: FloorAgent[];
  active: FloorStageId;
  compact?: boolean;
}) {
  const reduced = useReducedMotion() ?? false;

  return (
    <div
      className="relative overflow-hidden rounded-[22px] border border-line bg-paper px-3 py-4 sm:px-4"
      role="img"
      aria-label="Hiring floor, demo data. Agents stand at each stage."
    >
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0"
        style={{
          background:
            "radial-gradient(ellipse at 50% 70%, color-mix(in srgb, var(--bj-trust) 22%, transparent), transparent 55%)",
        }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 bottom-0 h-2/3 origin-bottom opacity-60"
        style={{
          transform: "perspective(520px) rotateX(62deg)",
          backgroundImage:
            "linear-gradient(color-mix(in srgb, var(--bj-trust) 30%, transparent) 1px, transparent 1px), linear-gradient(90deg, color-mix(in srgb, var(--bj-trust) 30%, transparent) 1px, transparent 1px)",
          backgroundSize: "36px 36px",
          maskImage: "linear-gradient(to top, black 10%, transparent 80%)",
        }}
      />
      {!reduced ? <div aria-hidden className="home-scan pointer-events-none absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-transparent via-trust/15 to-transparent" /> : null}

      <LayoutGroup>
        <ol className="relative grid grid-cols-2 gap-x-3 gap-y-5 sm:grid-cols-5">
          {stages.map((stage) => {
            const here = agents.filter((agent) => agent.stage === stage.id);
            const on = stage.id === active;
            return (
              <li key={stage.id} className="flex flex-col items-center">
                <div className="flex h-16 items-end justify-center gap-1">
                  {here.map((agent) => (
                    <motion.div
                      key={agent.id}
                      layoutId={reduced ? undefined : agent.id}
                      transition={reduced ? { duration: 0 } : { duration: durations.slower, ease }}
                    >
                      <Hologram agent={agent} reduced={reduced} />
                    </motion.div>
                  ))}
                </div>
                <div
                  className={`mt-1 w-full rounded-[10px] border px-1.5 py-1.5 text-center backdrop-blur-md ${on ? "border-trust bg-trust/15" : "border-line bg-surface/75"}`}
                >
                  <p className="mono text-sm leading-none text-fg">{stage.count}</p>
                  <p className="mt-1 truncate text-[10px] font-semibold uppercase tracking-[0.08em] text-muted">{stage.label}</p>
                  {stage.comingSoon ? <p className="mono text-[8px] uppercase tracking-[0.12em] text-muted">Soon</p> : null}
                </div>
              </li>
            );
          })}
        </ol>
      </LayoutGroup>
    </div>
  );
}
