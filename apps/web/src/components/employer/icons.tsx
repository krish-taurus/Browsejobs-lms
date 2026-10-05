/**
 * Small line icons for the employer shell — sidebar nav, header controls.
 * Plain 1.6px-stroke outlines on a 20x20 grid so they sit consistently
 * next to text at text-sm/text-base. Kept in one file so the whole set
 * reads as one hand rather than a patchwork of icon libraries.
 */
import type { ReactElement } from "react";

export type IconProps = { className?: string };
export type IconComponent = (props: IconProps) => ReactElement;

export function HomeIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="M3 8.5 10 3l7 5.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M4.5 7.5V16a1 1 0 0 0 1 1H8v-5h4v5h2.5a1 1 0 0 0 1-1V7.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function BriefcaseIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <rect x="2.5" y="6.5" width="15" height="9.5" rx="1.8" stroke="currentColor" strokeWidth="1.6" />
      <path d="M7 6.5V5a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 13 5v1.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
      <path d="M2.5 10.5h15" stroke="currentColor" strokeWidth="1.6" />
    </svg>
  );
}

export function PipelineIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <rect x="3" y="10" width="3" height="6.5" rx="1" stroke="currentColor" strokeWidth="1.6" />
      <rect x="8.5" y="6" width="3" height="10.5" rx="1" stroke="currentColor" strokeWidth="1.6" />
      <rect x="14" y="3" width="3" height="13.5" rx="1" stroke="currentColor" strokeWidth="1.6" />
    </svg>
  );
}

export function UsersIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <circle cx="7.2" cy="7" r="2.6" stroke="currentColor" strokeWidth="1.6" />
      <path d="M2.5 16c.4-3 2.3-4.6 4.7-4.6s4.3 1.6 4.7 4.6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
      <path d="M12.6 5a2.6 2.6 0 0 1 0 5.15" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
      <path d="M14.1 11.5c2 .5 3.2 1.9 3.4 4.15" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
    </svg>
  );
}

export function GearIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <circle cx="10" cy="10" r="2.6" stroke="currentColor" strokeWidth="1.6" />
      <path
        d="M10 3.2v1.4M10 15.4v1.4M16.8 10h-1.4M4.6 10H3.2M14.8 5.2l-1 1M6.2 13.8l-1 1M14.8 14.8l-1-1M6.2 6.2l-1-1"
        stroke="currentColor"
        strokeWidth="1.6"
        strokeLinecap="round"
      />
    </svg>
  );
}

export function HelpIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <circle cx="10" cy="10" r="7.2" stroke="currentColor" strokeWidth="1.6" />
      <path d="M7.7 8a2.3 2.3 0 1 1 3.5 1.95c-.75.47-1.2.9-1.2 1.8" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
      <circle cx="10" cy="14.1" r="0.9" fill="currentColor" />
    </svg>
  );
}

export function CrownIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path
        d="M3 8.2 6 10l4-5.6 4 5.6 3-1.8-1.1 6.6a1 1 0 0 1-1 .83H5.1a1 1 0 0 1-1-.83Z"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export function SearchIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <circle cx="9" cy="9" r="5.5" stroke="currentColor" strokeWidth="1.6" />
      <path d="m17 17-3.6-3.6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
    </svg>
  );
}

export function BellIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path
        d="M5 8.4a5 5 0 0 1 10 0c0 3.4 1.2 4.6 1.9 5.2H3.1c.7-.6 1.9-1.8 1.9-5.2Z"
        stroke="currentColor"
        strokeWidth="1.6"
        strokeLinejoin="round"
      />
      <path d="M8.2 16.4a1.9 1.9 0 0 0 3.6 0" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
    </svg>
  );
}

export function ChevronDownIcon({ className = "size-3.5" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="m5.5 8 4.5 4.5L14.5 8" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function ChevronRightIcon({ className = "size-4" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="m7.5 4.5 5.5 5.5-5.5 5.5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function TrendUpIcon({ className = "size-3.5" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="m3.5 13.5 4.6-4.8 3 3 5.4-6" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M13 5.7h3.5v3.5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function ClockIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <circle cx="10" cy="10" r="7.2" stroke="currentColor" strokeWidth="1.6" />
      <path d="M10 5.8V10l3 1.9" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function DocumentCheckIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="M6 2.5h5.5L15 6v10.5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
      <path d="M11.3 2.6V6h3.4" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
      <path d="m6.8 11.3 2 2 3.4-4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function TrophyIcon({ className = "size-5" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="M6.5 3.5h7v4.2a3.5 3.5 0 0 1-7 0Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
      <path d="M6.5 4.5H4.2a1 1 0 0 0-1 1.15c.25 1.7 1.15 2.85 2.9 3.15M13.5 4.5h2.3a1 1 0 0 1 1 1.15c-.25 1.7-1.15 2.85-2.9 3.15" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
      <path d="M10 10.7v2.6M7.3 16.5h5.4M8.4 13.3h3.2v1.3a1.6 1.6 0 0 1-1.6 1.6h0a1.6 1.6 0 0 1-1.6-1.6Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
    </svg>
  );
}

/** Small diagonal "view/open" arrow — summary-strip and card link affordances. */
export function ArrowUpRightIcon({ className = "size-3.5" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="M6 14 14 6M8 6h6v6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

/** Filled-ring checkmark — the review queue's "all caught up" state. */
export function CheckCircleIcon({ className = "size-5" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <circle cx="10" cy="10" r="7.5" stroke="currentColor" strokeWidth="1.6" />
      <path d="m6.8 10.2 2 2 4.4-4.8" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

/** Plain document — hiring-manager role icon. */
export function DocumentIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="M6 2.5h5.5L15 6v10.5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
      <path d="M11.3 2.6V6h3.4" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
      <path d="M7.3 10.5h5.4M7.3 13.2h5.4" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
    </svg>
  );
}

/** Shield with a check — invitation-security card. */
export function ShieldCheckIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="M10 2.3 16 4.5v4.8c0 4.1-2.6 6.9-6 8.4-3.4-1.5-6-4.3-6-8.4V4.5Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
      <path d="m7 10 2.1 2.1L13.3 8" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

/** Location pin — job cards and the closed-roles table. */
export function LocationPinIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="M10 18s6-5.2 6-10a6 6 0 0 0-12 0c0 4.8 6 10 6 10Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
      <circle cx="10" cy="8" r="2.2" stroke="currentColor" strokeWidth="1.6" />
    </svg>
  );
}

/** Outline star — the Graded stage. */
export function StarIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="M10 2.8 12.4 7.7l5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4-3.9-3.8 5.4-.8Z" stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round" />
    </svg>
  );
}

/** Calendar — the L1/L2 interview stages. */
export function CalendarIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <rect x="3" y="4.2" width="14" height="12.8" rx="1.8" stroke="currentColor" strokeWidth="1.6" />
      <path d="M3 8h14M6.5 2.5v3M13.5 2.5v3" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
    </svg>
  );
}

/** Outline diamond — the Offer stage. */
export function DiamondIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="M5.5 4h9L17 8.3 10 17 3 8.3 5.5 4Z" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" />
      <path d="M3 8.3h14M7.5 4 10 8.3 12.5 4" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round" />
    </svg>
  );
}

/** Simple robot head — the Taurus AI nav item. */
export function RobotIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <rect x="4" y="6" width="12" height="10" rx="3" stroke="currentColor" strokeWidth="1.6" />
      <path d="M10 6V3.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
      <circle cx="10" cy="2.7" r="1" fill="currentColor" />
      <circle cx="7.5" cy="11" r="1.15" fill="currentColor" />
      <circle cx="12.5" cy="11" r="1.15" fill="currentColor" />
      <path d="M7.8 14h4.4" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
    </svg>
  );
}

/** Send arrow — the composer's submit button. */
export function SendIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <path d="M17 3 3 9.3l5.8 2.1L11 17l6-14Z" stroke="currentColor" strokeWidth="1.5" strokeLinejoin="round" />
      <path d="m8.8 11.4 3.6-3.6" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
    </svg>
  );
}

/** Microphone — voice input controls. */
export function MicIcon({ className = "size-[18px]" }: IconProps) {
  return (
    <svg viewBox="0 0 20 20" fill="none" className={className} aria-hidden="true">
      <rect x="7.3" y="2.5" width="5.4" height="9.5" rx="2.7" stroke="currentColor" strokeWidth="1.6" />
      <path d="M4.5 9.5A5.5 5.5 0 0 0 15.5 9.5M10 15v2.5M7.3 17.5h5.4" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
    </svg>
  );
}
