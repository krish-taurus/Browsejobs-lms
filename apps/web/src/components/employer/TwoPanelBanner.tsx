import Image from "next/image";
import type { ReactNode } from "react";

/**
 * The wide emerald banner shared by the Dashboard and Team pages: a copy
 * panel on the left, a photograph on the right, and a curved emerald
 * boundary drawn over the photo's left edge on desktop. Both pages open with
 * the same "Good people." line, then diverge on the italic second line —
 * shared here rather than duplicated per page.
 */
export function TwoPanelBanner({
  topLine,
  italicLine,
  subLine,
  imageSrc,
  imageAlt,
  objectPosition = "50% 35%",
  statusSlot,
}: {
  topLine: string;
  italicLine: string;
  subLine: string;
  imageSrc: string;
  imageAlt: string;
  objectPosition?: string;
  /** Optional line under the copy — the dashboard's review-status dot, for instance. Omitted entirely on pages with nothing honest to say here. */
  statusSlot?: ReactNode;
}) {
  return (
    <div
      className="relative isolate h-auto overflow-hidden rounded-[var(--bj-dash-radius)] sm:h-[var(--bj-dash-hero-height)]"
      style={{ background: "var(--bj-dash-hero)" }}
    >
      {/* Photo — full-bleed on desktop behind the right ~60%; its own row on mobile. */}
      <div className="relative h-44 w-full sm:absolute sm:inset-y-0 sm:right-0 sm:h-full sm:w-[62%]">
        <Image
          src={imageSrc}
          alt={imageAlt}
          fill
          sizes="(min-width: 640px) 62vw, 100vw"
          className="object-cover"
          style={{ objectPosition }}
          priority
        />
      </div>

      {/* Curved boundary — emerald drawn over the photo's left edge on desktop only. */}
      <svg
        aria-hidden
        className="pointer-events-none absolute inset-y-0 hidden sm:block"
        style={{ left: "calc(38% - 70px)" }}
        width="140"
        height="100%"
        viewBox="0 0 140 208"
        preserveAspectRatio="none"
      >
        <path d="M0,0 C 90,58 90,150 0,208 L140,208 L140,0 Z" fill="var(--bj-dash-hero)" />
      </svg>

      <div className="relative z-10 max-w-full p-6 text-white sm:absolute sm:inset-y-0 sm:left-0 sm:flex sm:max-w-[54%] sm:flex-col sm:justify-center sm:p-8">
        <p className="bj-dash-serif text-3xl leading-[1.05] sm:text-[length:var(--bj-dash-hero-copy-size)]">
          {topLine}
        </p>
        <p className="bj-dash-serif text-3xl italic leading-[1.05] sm:text-[length:var(--bj-dash-hero-copy-size)]">
          {italicLine}
        </p>
        <p className="mt-3 text-sm text-white/80 sm:text-base">
          {subLine}
        </p>
        {statusSlot !== undefined && (
          <div className="mt-4 flex items-center gap-2 text-sm text-white/90">
            {statusSlot}
          </div>
        )}
      </div>
    </div>
  );
}
