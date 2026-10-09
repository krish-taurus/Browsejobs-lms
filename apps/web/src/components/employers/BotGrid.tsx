"use client";

import { motion, useReducedMotion } from "framer-motion";
import { EMPLOYER_BOTS } from "@/content/employer-landing";
import { durations, ease, stagger } from "@/lib/motion";

export function BotGrid() {
  const reduce = useReducedMotion();

  return (
    <ol className="mt-10 grid gap-4 md:grid-cols-2">
      {EMPLOYER_BOTS.map((bot, index) => (
        <motion.li
          key={bot.id}
          className="flex flex-col rounded-[22px] border border-line bg-white p-5 shadow-soft md:p-6"
          initial="hidden"
          whileInView="show"
          viewport={{ once: true, margin: "-40px" }}
          variants={{
            hidden: { opacity: reduce ? 1 : 0, y: reduce ? 0 : 18 },
            show: {
              opacity: 1,
              y: 0,
              transition: { duration: reduce ? 0 : durations.slow, ease, delay: reduce ? 0 : index * stagger },
            },
          }}
        >
          <BotArt id={bot.id} reduce={!!reduce} />
          <p className="mono mt-5 text-[11px] font-semibold uppercase tracking-[0.16em] text-trust">
            {bot.mark}
          </p>
          <h3 className="display mt-2 text-2xl text-ink">{bot.name}</h3>
          <p className="mt-2 text-[15px] leading-relaxed text-ink2">{bot.body}</p>
        </motion.li>
      ))}
    </ol>
  );
}

function BotArt({ id, reduce }: { id: (typeof EMPLOYER_BOTS)[number]["id"]; reduce: boolean }) {
  return (
    <div className="relative h-36 overflow-hidden rounded-[14px] border border-line bg-paper" aria-hidden>
      <svg viewBox="0 0 320 144" className="h-full w-full">
        {id === "screening" && <ScreeningArt reduce={reduce} />}
        {id === "interview" && <InterviewArt reduce={reduce} />}
        {id === "bgv" && <BgvArt reduce={reduce} />}
        {id === "chat" && <ChatArt reduce={reduce} />}
      </svg>
    </div>
  );
}

function ScreeningArt({ reduce }: { reduce: boolean }) {
  const cards = [
    { y: 28, opacity: 0.35, delay: 0 },
    { y: 48, opacity: 0.55, delay: stagger },
    { y: 70, opacity: 1, delay: stagger * 2 },
  ];
  return (
    <>
      {cards.map((card) => (
        <motion.g
          key={card.y}
          initial={reduce ? false : { opacity: 0, y: 10 }}
          whileInView={{ opacity: card.opacity, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: reduce ? 0 : durations.slow, ease, delay: reduce ? 0 : card.delay }}
        >
          <rect x="36" y={card.y} width="168" height="36" rx="8" fill="var(--bj-white)" stroke="var(--bj-line)" />
          <rect x="48" y={card.y + 12} width="72" height="6" rx="3" fill="var(--bj-trust)" opacity={card.opacity} />
          <rect x="128" y={card.y + 12} width="52" height="6" rx="3" fill="var(--bj-line)" />
        </motion.g>
      ))}
      <motion.g
        initial={reduce ? false : { opacity: 0, x: 8 }}
        whileInView={{ opacity: 1, x: 0 }}
        viewport={{ once: true }}
        transition={{ duration: reduce ? 0 : durations.slow, ease, delay: reduce ? 0 : stagger * 3 }}
      >
        <rect x="220" y="58" width="72" height="36" rx="8" fill="var(--bj-sky)" stroke="var(--bj-trust)" />
        <path d="M242 76 l8 8 16-16" fill="none" stroke="var(--bj-trust)" strokeWidth="2.5" strokeLinecap="round" />
      </motion.g>
    </>
  );
}

function InterviewArt({ reduce }: { reduce: boolean }) {
  const bars = [28, 46, 22, 58, 36, 64, 30, 50, 24, 42];
  return (
    <>
      {bars.map((height, i) => (
        <motion.rect
          key={i}
          x={28 + i * 18}
          y={108 - height}
          width="8"
          height={height}
          rx="4"
          fill="var(--bj-trust)"
          style={{ transformBox: "fill-box", transformOrigin: "center bottom" }}
          variants={{
            hidden: { scaleY: reduce ? 1 : 0, opacity: reduce ? 1 : 0 },
            show: {
              scaleY: 1,
              opacity: 1,
              transition: { duration: reduce ? 0 : durations.slow, ease, delay: reduce ? 0 : (i % 5) * stagger },
            },
          }}
        />
      ))}
      <g>
        <circle cx="268" cy="72" r="28" fill="none" stroke="var(--bj-line)" strokeWidth="6" />
        <motion.circle
          cx="268"
          cy="72"
          r="28"
          fill="none"
          stroke="var(--bj-verify)"
          strokeWidth="6"
          strokeLinecap="round"
          variants={{
            hidden: { pathLength: reduce ? 0.75 : 0 },
            show: { pathLength: 0.75, transition: { duration: reduce ? 0 : durations.slower, ease } },
          }}
          transform="rotate(-90 268 72)"
        />
        <text
          x="268"
          y="76"
          textAnchor="middle"
          fontFamily="var(--font-plex-mono), ui-monospace, monospace"
          fontSize="11"
          fill="var(--bj-ink)"
        >
          75%
        </text>
      </g>
    </>
  );
}

function BgvArt({ reduce }: { reduce: boolean }) {
  return (
    <g>
      <motion.path
        d="M160 24 L214 46 V84 C214 112 190 128 160 136 C130 128 106 112 106 84 V46 Z"
        fill="var(--bj-sky)"
        stroke="var(--bj-trust)"
        strokeWidth="2.5"
        initial={reduce ? false : { opacity: 0, scale: 0.92 }}
        whileInView={{ opacity: 1, scale: 1 }}
        viewport={{ once: true }}
        transition={{ duration: reduce ? 0 : durations.slow, ease }}
        style={{ transformOrigin: "160px 80px" }}
      />
      <motion.path
        d="M142 80 l12 12 26-28"
        fill="none"
        stroke="var(--bj-verify)"
        strokeWidth="3.5"
        strokeLinecap="round"
        strokeLinejoin="round"
        initial={reduce ? false : { pathLength: 0 }}
        whileInView={{ pathLength: 1 }}
        viewport={{ once: true }}
        transition={{ duration: reduce ? 0 : durations.slower, ease, delay: reduce ? 0 : stagger }}
      />
    </g>
  );
}

function ChatArt({ reduce }: { reduce: boolean }) {
  const bubbles = [
    { x: 36, y: 28, w: 150, fill: "white", delay: 0 },
    { x: 120, y: 62, w: 164, fill: "sky", delay: stagger },
    { x: 36, y: 96, w: 128, fill: "white", delay: stagger * 2 },
  ];
  return (
    <>
      {bubbles.map((bubble) => (
        <motion.rect
          key={bubble.y}
          x={bubble.x}
          y={bubble.y}
          width={bubble.w}
          height="28"
          rx="14"
          fill={bubble.fill === "white" ? "var(--bj-white)" : "var(--bj-sky)"}
          stroke="var(--bj-line)"
          initial={reduce ? false : { opacity: 0, y: 12 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: reduce ? 0 : durations.base, ease, delay: reduce ? 0 : bubble.delay }}
        />
      ))}
    </>
  );
}
