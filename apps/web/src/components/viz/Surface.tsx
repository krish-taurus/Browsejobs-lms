"use client";

import { motion, useReducedMotion } from "framer-motion";
import type { ReactNode } from "react";
import { DUR, EASE, INK, SERIES, SURFACE } from "./tokens";

/**
 * Dark command-deck surfaces. Depth comes from three real layers —
 * canvas, card, raised — plus hairlines and a single accent glow, rather
 * than from stacked drop shadows, which read as mud on a dark canvas.
 */

export function Deck({ children }: { children: ReactNode }) {
  return (
    <div className="relative min-h-screen" style={{ background: SURFACE.base, color: INK.primary }}>
      {/* Ambient field: a fixed grid and two soft glows. Purely decorative,
          so it is hidden from assistive tech and never intercepts pointers. */}
      <div aria-hidden className="pointer-events-none fixed inset-0 overflow-hidden">
        <div
          className="absolute inset-0 opacity-[0.35]"
          style={{
            backgroundImage:
              "linear-gradient(rgba(255,255,255,0.028) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.028) 1px, transparent 1px)",
            backgroundSize: "56px 56px",
            maskImage: "radial-gradient(ellipse 90% 60% at 50% 0%, #000 40%, transparent 100%)",
          }}
        />
        <div
          className="absolute -left-40 -top-56 h-[520px] w-[620px] rounded-full blur-[170px]"
          style={{ background: SERIES[0], opacity: 0.13 }}
        />
        <div
          className="absolute -right-40 top-1/3 h-[440px] w-[520px] rounded-full blur-[170px]"
          style={{ background: SERIES[3], opacity: 0.09 }}
        />
      </div>
      <div className="relative">{children}</div>
    </div>
  );
}

export function Card({
  children,
  className = "",
  index = 0,
  accent,
  glow = false,
}: {
  children: ReactNode;
  className?: string;
  index?: number;
  accent?: string;
  glow?: boolean;
}) {
  const reduce = useReducedMotion();
  return (
    <motion.section
      initial={reduce ? false : { opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: DUR.base, ease: EASE, delay: (index % 5) * 0.06 }}
      className={`relative overflow-hidden rounded-2xl border p-5 md:p-6 ${className}`}
      style={{
        background: accent
          ? `linear-gradient(158deg, ${accent}14, ${SURFACE.card} 62%)`
          : SURFACE.card,
        borderColor: accent ? `${accent}33` : SURFACE.line,
      }}
    >
      {accent && (
        <span
          aria-hidden
          className="pointer-events-none absolute inset-x-0 top-0 h-px"
          style={{ background: `linear-gradient(90deg, ${accent}, transparent 65%)` }}
        />
      )}
      {glow && accent && (
        <span
          aria-hidden
          className="pointer-events-none absolute -right-16 -top-20 h-56 w-64 rounded-full blur-[90px]"
          style={{ background: accent, opacity: 0.16 }}
        />
      )}
      <div className="relative">{children}</div>
    </motion.section>
  );
}

/** Micro-label above a value. */
export function Kicker({ children, color }: { children: ReactNode; color?: string }) {
  return (
    <p
      className="text-[10px] font-semibold uppercase tracking-[0.22em]"
      style={{ color: color ?? INK.muted }}
    >
      {children}
    </p>
  );
}

/** The one number a card is about. */
export function Hero({
  value,
  suffix,
  sub,
}: {
  value: ReactNode;
  suffix?: string;
  sub?: ReactNode;
}) {
  return (
    <div>
      <p className="font-display mt-2 text-4xl font-bold leading-none tracking-tight md:text-[2.75rem]">
        {value}
        {suffix && (
          <span className="ml-1 text-xl font-semibold" style={{ color: INK.muted }}>
            {suffix}
          </span>
        )}
      </p>
      {sub && (
        <p className="mt-2 text-[13px] leading-snug" style={{ color: INK.secondary }}>
          {sub}
        </p>
      )}
    </div>
  );
}

export function PageTitle({
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
          initial={reduce ? false : { opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: DUR.base, ease: EASE }}
          className="text-[11px] font-semibold uppercase tracking-[0.3em]"
          style={{ color: SERIES[0] }}
        >
          {kicker}
        </motion.p>
        <motion.h1
          initial={reduce ? false : { opacity: 0, y: 18 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: DUR.slow, ease: EASE, delay: 0.06 }}
          className="font-display mt-2.5 text-3xl font-bold leading-[1.05] tracking-[-0.03em] md:text-[2.9rem]"
        >
          {title}
          {highlight && (
            <>
              {" "}
              <span
                className="bg-clip-text text-transparent"
                style={{ backgroundImage: `linear-gradient(100deg, ${SERIES[0]}, ${SERIES[3]})` }}
              >
                {highlight}
              </span>
            </>
          )}
        </motion.h1>
        {sub && (
          <motion.p
            initial={reduce ? false : { opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: DUR.slow, delay: 0.16 }}
            className="mt-3 max-w-2xl text-[15px] leading-relaxed"
            style={{ color: INK.secondary }}
          >
            {sub}
          </motion.p>
        )}
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  );
}

export function PrimaryAction({
  children,
  href,
  onClick,
  disabled,
  type = "button",
}: {
  children: ReactNode;
  href?: string;
  onClick?: () => void;
  disabled?: boolean;
  type?: "button" | "submit";
}) {
  const cls =
    "group inline-flex items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold text-white transition-all disabled:opacity-45";
  const style = {
    background: `linear-gradient(100deg, ${SERIES[0]}, #3f7ce8)`,
    boxShadow: `0 10px 34px ${SERIES[0]}4d`,
  };

  if (href) {
    return (
      <a href={href} className={cls} style={style}>
        {children}
        <span aria-hidden className="transition-transform group-hover:translate-x-0.5">
          →
        </span>
      </a>
    );
  }
  return (
    <button type={type} onClick={onClick} disabled={disabled} className={cls} style={style}>
      {children}
    </button>
  );
}

export function GhostAction({
  children,
  href,
  onClick,
  disabled,
}: {
  children: ReactNode;
  href?: string;
  onClick?: () => void;
  disabled?: boolean;
}) {
  const cls =
    "inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium transition-colors disabled:opacity-45";
  const style = { borderColor: SURFACE.lineStrong, color: INK.secondary };

  if (href) {
    return (
      <a href={href} className={cls} style={style}>
        {children}
      </a>
    );
  }
  return (
    <button onClick={onClick} disabled={disabled} className={cls} style={style}>
      {children}
    </button>
  );
}

export function Shimmer({ className = "" }: { className?: string }) {
  return (
    <div
      className={`animate-pulse rounded-2xl ${className}`}
      style={{ background: "rgba(255,255,255,0.045)" }}
    />
  );
}
