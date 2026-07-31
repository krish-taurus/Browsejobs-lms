"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { motion } from "framer-motion";
import type { ReactNode } from "react";
import { Mark } from "@/components/brand/Wordmark";
import { DUR, EASE, INK, SURFACE } from "@/components/viz/tokens";

/**
 * Candidate shell. Every candidate surface hangs off these tabs, so
 * nothing is more than one tap away and the current section is always
 * visible — previously these pages were reachable only by links buried in
 * page bodies, which meant a candidate could get stranded.
 *
 * Sticky on desktop, and the same row scrolls horizontally on mobile rather
 * than collapsing into a menu: this few items do not need hiding.
 */

const TABS = [
  { href: "/candidate", label: "Dashboard", exact: true },
  { href: "/candidate/jobs", label: "Jobs" },
  { href: "/candidate/verification", label: "Verification" },
];

export default function CandidateLayout({ children }: { children: ReactNode }) {
  const pathname = usePathname();

  return (
    <div style={{ background: SURFACE.base }}>
      <header
        className="sticky top-0 z-40 border-b backdrop-blur-xl"
        style={{ background: `${SURFACE.base}cc`, borderColor: SURFACE.line }}
      >
        <div className="mx-auto flex max-w-6xl items-center gap-4 px-5 py-3">
          <Link href="/candidate" className="flex shrink-0 items-center gap-2">
            <Mark tone="dark" />
            <span className="hidden sm:block">
              <span className="display block leading-none text-white">BrowseJobs</span>
              <span
                className="font-mono text-[9px] uppercase tracking-[0.22em]"
                style={{ color: INK.faint }}
              >
                Candidate
              </span>
            </span>
          </Link>

          <nav className="-mx-1 flex flex-1 gap-1 overflow-x-auto px-1">
            {TABS.map((tab) => {
              const active = tab.exact
                ? pathname === tab.href
                : pathname.startsWith(tab.href);

              return (
                <Link
                  key={tab.href}
                  href={tab.href}
                  aria-current={active ? "page" : undefined}
                  className="relative shrink-0 rounded-full px-4 py-2 text-[13px] font-semibold transition-colors"
                  style={{ color: active ? "#fff" : INK.muted }}
                >
                  {active && (
                    <motion.span
                      layoutId="candidate-tab"
                      transition={{ duration: DUR.fast, ease: EASE }}
                      className="absolute inset-0 rounded-full"
                      style={{ background: "rgba(255,255,255,0.08)" }}
                    />
                  )}
                  <span className="relative">{tab.label}</span>
                </Link>
              );
            })}
          </nav>

          <Link
            href="/portal/notifications"
            className="shrink-0 text-[13px] font-medium transition-colors hover:text-white"
            style={{ color: INK.muted }}
          >
            Alerts
          </Link>
        </div>
      </header>

      {children}
    </div>
  );
}
