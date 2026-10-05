"use client";

import { motion, useReducedMotion } from "framer-motion";
import type { ReactNode } from "react";

/**
 * Shared surface primitives for the employer portal: white cards, a single
 * saturated Facebook-blue accent, and one signature easing. Motion is
 * opt-out under prefers-reduced-motion.
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
          className="text-[11px] font-semibold uppercase tracking-[0.3em] text-[var(--bj-dash-primary)]"
        >
          {kicker}
        </motion.p>
        <motion.h1
          initial={reduce ? false : { opacity: 0, y: 22 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.75, ease: EASE, delay: 0.08 }}
          className="bj-dash-serif mt-2.5 text-3xl leading-[1.05] tracking-[-0.03em] text-[var(--bj-dash-ink)] md:text-5xl"
        >
          {title}
          {highlight && (
            <>
              {" "}
              <span className="text-[var(--bj-dash-primary)]">{highlight}</span>
            </>
          )}
        </motion.h1>
        {sub && (
          <motion.p
            initial={reduce ? false : { opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.7, delay: 0.2 }}
            className="mt-3 max-w-xl text-[15px] leading-relaxed text-[var(--bj-dash-muted)]"
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
  accent = "var(--bj-dash-primary)",
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
      className={`relative overflow-hidden rounded-3xl border border-[var(--bj-dash-border)] bg-white p-6 text-[var(--bj-dash-ink)] shadow-[0_1px_2px_rgba(0,0,0,0.04),0_10px_28px_-18px_rgba(0,0,0,0.18)] ${className}`}
    >
      <span
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-0 h-[3px]"
        style={{ background: `linear-gradient(90deg, ${accent}, ${accent}00 72%)` }}
      />
      {ghost !== undefined && (
        <span
          aria-hidden
          className="font-display pointer-events-none absolute -right-1 -top-3 text-[3.75rem] font-bold leading-none"
          style={{ color: `${accent}14` }}
        >
          {ghost}
        </span>
      )}
      <div className="relative">{children}</div>
    </motion.div>
  );
}

/**
 * Dark gradient-mesh panel — the loudest surface available, reserved for the
 * one thing that matters most. A near-black base carries two soft blurred
 * colour blobs: `glow` (each caller's own accent) and a fixed violet
 * companion, so every InkPanel gets the same depth without losing the accent
 * that told the four other pages using this component apart.
 */
export function InkPanel({
  children,
  glow = "var(--bj-dash-primary)",
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
      className={`relative overflow-hidden rounded-3xl bg-[var(--bj-dash-hero)] p-7 text-white shadow-[0_24px_60px_-24px_rgba(13,17,23,0.5)] md:p-8 ${className}`}
    >
      <div
        aria-hidden
        className="pointer-events-none absolute -right-24 -top-28 h-[360px] w-[460px] rounded-full blur-[130px]"
        style={{ background: glow, opacity: 0.32 }}
      />
      <div
        aria-hidden
        className="pointer-events-none absolute -left-20 -bottom-24 h-[300px] w-[380px] rounded-full blur-[130px]"
        style={{ background: "var(--bj-dash-focus)", opacity: 0.16 }}
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
        dark ? "text-white/70" : "text-[var(--bj-dash-muted)]"
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
    neutral: "bg-[var(--bj-dash-soft)] text-[var(--bj-dash-muted)]",
    trust: "bg-[var(--bj-dash-soft)] text-[var(--bj-dash-primary)]",
    verify: "bg-[var(--bj-dash-score-strong-bg)] text-[var(--bj-dash-score-strong)]",
    warn: "bg-[var(--bj-dash-score-below-bg)] text-[var(--bj-dash-score-below)]",
    amber: "bg-[var(--bj-dash-score-fair-bg)] text-[var(--bj-dash-score-fair)]",
    dark: "bg-[var(--bj-dash-ink)]/[0.06] text-[var(--bj-dash-ink)]/70",
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
  const cls = `group inline-flex items-center justify-center gap-2 rounded-full bg-[var(--bj-dash-primary)] px-5 py-2.5 text-sm font-semibold text-white shadow-[0_10px_28px_-8px_rgba(26,96,72,0.55)] transition-all hover:bg-[#144f3c] hover:shadow-[0_14px_34px_-8px_rgba(26,96,72,0.62)] disabled:opacity-45 disabled:shadow-none ${className}`;

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
      className={`inline-flex items-center gap-1.5 rounded-full border border-[var(--bj-dash-border)] bg-white px-4 py-2 text-sm font-medium text-[var(--bj-dash-ink)] transition-colors hover:border-[var(--bj-dash-primary)] hover:text-[var(--bj-dash-primary)] disabled:opacity-50 ${className}`}
    >
      {children}
    </button>
  );
}

/** Live pulse dot — used where data updates on its own. */
export function LiveDot({ color = "var(--bj-dash-score-strong)" }: { color?: string }) {
  return (
    <span className="relative flex h-2 w-2">
      <span className="absolute h-full w-full animate-ping rounded-full opacity-60" style={{ background: color }} />
      <span className="relative h-2 w-2 rounded-full" style={{ background: color }} />
    </span>
  );
}

/** Skeleton block matching the tile radius. */
export function Skeleton({ className = "" }: { className?: string }) {
  return <div className={`animate-pulse rounded-3xl bg-[var(--bj-dash-soft)] ${className}`} />;
}

/**
 * Shared control surface. Generous height for touch, a visible focus
 * ring (never removed), and the same hairline the tiles use.
 */
export const controlCls =
  "w-full rounded-2xl border border-[var(--bj-dash-border)] bg-white px-4 py-3 text-sm text-[var(--bj-dash-ink)] outline-none transition-shadow placeholder:text-[var(--bj-dash-muted)] focus:border-[var(--bj-dash-primary)] focus:ring-4 focus:ring-[var(--bj-dash-primary)]/15";

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
      <label htmlFor={htmlFor} className="block text-[13px] font-semibold text-[var(--bj-dash-ink)]">
        {label}
      </label>
      {hint && <p className="mb-2 mt-0.5 text-[12px] leading-snug text-[var(--bj-dash-muted)]">{hint}</p>}
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
        borderColor: checked ? "var(--bj-dash-primary)" : "var(--bj-dash-border)",
        background: checked ? "var(--bj-dash-soft)" : "#ffffff",
      }}
    >
      <input
        type="checkbox"
        checked={checked}
        onChange={(e) => onChange(e.target.checked)}
        className="mt-0.5 h-4 w-4 accent-[var(--bj-dash-primary)]"
      />
      <span>
        <span className="block text-[13px] font-semibold text-[var(--bj-dash-ink)]">{title}</span>
        {sub && <span className="mt-0.5 block text-[12px] leading-snug text-[var(--bj-dash-muted)]">{sub}</span>}
      </span>
    </label>
  );
}
