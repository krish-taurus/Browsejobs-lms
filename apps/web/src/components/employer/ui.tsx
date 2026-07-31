"use client";

import { motion, useReducedMotion } from "framer-motion";
import type { ReactNode } from "react";

/**
 * Shared surface primitives for the employer portal, in the same visual
 * dialect as the marketing site: 24px radii, tinted accent fills with a
 * top-rule, ghost numerals, ink panels with a glow, and one signature
 * easing. Motion is opt-out under prefers-reduced-motion.
 */

export const EASE = [0.16, 1, 0.3, 1] as const;

/** Section header: kicker + oversized display heading + optional sub. */
export function PageHead({
  kicker,
  title,
  highlight,
  sub,
  action,
}: {
  kicker: string;
  title: string;
  highlight?: string;
  sub?: string;
  action?: ReactNode;
}) {
  const reduce = useReducedMotion();
  return (
    <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
      <div>
        <motion.p
          initial={reduce ? false : { opacity: 0, y: 14 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, ease: EASE }}
          className="text-[11px] font-semibold uppercase tracking-[0.3em] text-[#4d8ef7]"
        >
          {kicker}
        </motion.p>
        <motion.h1
          initial={reduce ? false : { opacity: 0, y: 22 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.75, ease: EASE, delay: 0.08 }}
          className="font-display mt-2.5 text-3xl font-bold leading-[1.05] tracking-[-0.03em] md:text-5xl"
        >
          {title}
          {highlight && (
            <>
              {" "}
              <span className="bg-gradient-to-r from-[#4d8ef7] via-[#7ba9fa] to-[#9d6bf5] bg-clip-text text-transparent">
                {highlight}
              </span>
            </>
          )}
        </motion.h1>
        {sub && (
          <motion.p
            initial={reduce ? false : { opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.7, delay: 0.2 }}
            className="mt-3 max-w-xl text-[15px] leading-relaxed text-white/55"
          >
            {sub}
          </motion.p>
        )}
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  );
}

/**
 * Bento tile: tinted gradient fill keyed to an accent, hairline border
 * in the same hue, and an accent top-rule that fades out to the right.
 */
export function Tile({
  accent = "#1b6df0",
  className = "",
  children,
  index = 0,
  ghost,
  hover = true,
}: {
  accent?: string;
  className?: string;
  children: ReactNode;
  index?: number;
  /** Oversized ghost numeral/label behind the content. */
  ghost?: string | number;
  hover?: boolean;
}) {
  const reduce = useReducedMotion();
  return (
    <motion.div
      initial={reduce ? false : { opacity: 0, y: 28, scale: 0.98 }}
      animate={{ opacity: 1, y: 0, scale: 1 }}
      transition={{ duration: 0.7, ease: EASE, delay: (index % 4) * 0.07 }}
      whileHover={hover && !reduce ? { y: -4 } : undefined}
      className={`relative overflow-hidden rounded-3xl border p-6 shadow-[0_18px_50px_rgba(0,0,0,0.45)] ${className}`}
      style={{
        background: `linear-gradient(158deg, ${accent}14, #0a0f1c 62%)`,
        borderColor: `${accent}33`,
      }}
    >
      <span
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-0 h-[3px]"
        style={{ background: `linear-gradient(90deg, ${accent}, ${accent}00 72%)` }}
      />
      {ghost !== undefined && (
        <span
          aria-hidden
          className="font-display pointer-events-none absolute -right-3 -top-8 text-[6.5rem] font-bold leading-none"
          style={{ color: `${accent}1f` }}
        >
          {ghost}
        </span>
      )}
      <div className="relative">{children}</div>
    </motion.div>
  );
}

/** Dark panel with a soft accent glow — the loudest surface available. */
export function InkPanel({
  children,
  glow = "#1b6df0",
  className = "",
}: {
  children: ReactNode;
  glow?: string;
  className?: string;
}) {
  const reduce = useReducedMotion();
  return (
    <motion.div
      initial={reduce ? false : { opacity: 0, y: 28 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.75, ease: EASE }}
      className={`relative overflow-hidden rounded-3xl bg-[#111827] p-7 text-white shadow-[0_30px_90px_rgba(10,18,32,0.28)] md:p-8 ${className}`}
    >
      <div
        aria-hidden
        className="pointer-events-none absolute -right-24 -top-28 h-[320px] w-[420px] rounded-full blur-[130px]"
        style={{ background: glow, opacity: 0.22 }}
      />
      <span
        aria-hidden
        className="pointer-events-none absolute left-1/2 top-0 h-px w-2/3 -translate-x-1/2 bg-gradient-to-r from-transparent via-white/25 to-transparent"
      />
      <div className="relative">{children}</div>
    </motion.div>
  );
}

/** Micro-label above a value. */
export function Label({ children, dark = false }: { children: ReactNode; dark?: boolean }) {
  return (
    <p
      className={`text-[10px] font-bold uppercase tracking-[0.2em] ${
        dark ? "text-white/40" : "text-white/45"
      }`}
    >
      {children}
    </p>
  );
}

/** Status pill. Semantic colour rules: green = verified/kept, red = danger only. */
export function Pill({
  tone = "neutral",
  children,
}: {
  tone?: "neutral" | "trust" | "verify" | "warn" | "amber" | "dark";
  children: ReactNode;
}) {
  const tones: Record<string, string> = {
    neutral: "bg-white/[0.07] text-white/60",
    trust: "bg-[#4d8ef7]/18 text-[#9dc2fb]",
    verify: "bg-[#0da06e]/18 text-[#5fd6a6]",
    warn: "bg-[#e05561]/18 text-[#f2a1a7]",
    amber: "bg-[#b07c00]/22 text-[#e8bf63]",
    dark: "bg-white/10 text-white/70",
  };
  return (
    <span className={`rounded-full px-3 py-1 font-mono text-[10px] font-semibold uppercase tracking-[0.12em] ${tones[tone]}`}>
      {children}
    </span>
  );
}

/** Primary action — solid trust blue with the signature glow. */
export function PrimaryButton({
  children,
  onClick,
  href,
  disabled,
  className = "",
  type = "button",
}: {
  children: ReactNode;
  onClick?: () => void;
  href?: string;
  disabled?: boolean;
  className?: string;
  type?: "button" | "submit";
}) {
  const cls = `group inline-flex items-center justify-center gap-2 rounded-full bg-[#4d8ef7] px-5 py-2.5 text-sm font-semibold text-white shadow-[0_12px_36px_rgba(77,142,247,0.42)] transition-all hover:shadow-[0_16px_46px_rgba(77,142,247,0.58)] disabled:opacity-50 disabled:shadow-none ${className}`;

  if (href) {
    return (
      <a href={href} className={cls}>
        {children}
        <span aria-hidden className="inline-block transition-transform group-hover:translate-x-0.5">→</span>
      </a>
    );
  }

  return (
    <button type={type} onClick={onClick} disabled={disabled} className={cls}>
      {children}
    </button>
  );
}

/** Quiet secondary action. */
export function GhostButton({
  children,
  onClick,
  disabled,
  className = "",
}: {
  children: ReactNode;
  onClick?: () => void;
  disabled?: boolean;
  className?: string;
}) {
  return (
    <button
      onClick={onClick}
      disabled={disabled}
      className={`inline-flex items-center gap-1.5 rounded-full border border-white/[0.14] bg-white/[0.04] px-4 py-2 text-sm font-medium text-white/80 transition-colors hover:border-white/30 disabled:opacity-50 ${className}`}
    >
      {children}
    </button>
  );
}

/** Live pulse dot — used where data updates on its own. */
export function LiveDot({ color = "#0ba860" }: { color?: string }) {
  return (
    <span className="relative flex h-2 w-2">
      <span className="absolute h-full w-full animate-ping rounded-full opacity-60" style={{ background: color }} />
      <span className="relative h-2 w-2 rounded-full" style={{ background: color }} />
    </span>
  );
}

/** Skeleton block matching the tile radius. */
export function Skeleton({ className = "" }: { className?: string }) {
  return <div className={`animate-pulse rounded-3xl bg-white/[0.045] ${className}`} />;
}

/**
 * Shared control surface. Generous height for touch, a visible focus
 * ring (never removed), and the same hairline the tiles use.
 */
export const controlCls =
  "w-full rounded-2xl border border-white/[0.12] bg-white/[0.05] px-4 py-3 text-sm text-white outline-none transition-shadow placeholder:text-white/25 focus:border-[#4d8ef7] focus:ring-4 focus:ring-[#4d8ef7]/25";

/** Labelled form field with optional helper text under the label. */
export function Field({
  label,
  hint,
  htmlFor,
  children,
}: {
  label: string;
  hint?: string;
  htmlFor?: string;
  children: ReactNode;
}) {
  return (
    <div>
      <label htmlFor={htmlFor} className="block text-[13px] font-semibold text-white">
        {label}
      </label>
      {hint && <p className="mb-2 mt-0.5 text-[12px] leading-snug text-white/45">{hint}</p>}
      <div className={hint ? "" : "mt-2"}>{children}</div>
    </div>
  );
}

/** Checkbox styled as a selectable card, so the hit area is a full row. */
export function CheckCard({
  checked,
  onChange,
  title,
  sub,
}: {
  checked: boolean;
  onChange: (v: boolean) => void;
  title: string;
  sub?: string;
}) {
  return (
    <label
      className="flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition-colors"
      style={{
        borderColor: checked ? "#4d8ef75c" : "rgba(255,255,255,0.12)",
        background: checked ? "#4d8ef714" : "rgba(255,255,255,0.03)",
      }}
    >
      <input
        type="checkbox"
        checked={checked}
        onChange={(e) => onChange(e.target.checked)}
        className="mt-0.5 h-4 w-4 accent-[#4d8ef7]"
      />
      <span>
        <span className="block text-[13px] font-semibold text-white">{title}</span>
        {sub && <span className="mt-0.5 block text-[12px] leading-snug text-white/50">{sub}</span>}
      </span>
    </label>
  );
}
