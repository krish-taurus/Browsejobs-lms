"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState, type ReactNode } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { useAuth } from "@/lib/auth";
import { useFeeStatus } from "@/lib/fee-status";
import { navGroups, primaryTabs } from "@/components/portal/nav";
import { CommandPalette } from "@/components/portal/CommandPalette";
import { FeeBlockedScreen } from "@/components/portal/FeeBlockedScreen";

function NavIcon({ path, active }: { path: string; active: boolean }) {
  return (
    <svg
      viewBox="0 0 24 24"
      className={`h-5 w-5 ${active ? "text-trust" : "text-muted"}`}
      fill="none"
    >
      <path
        d={path}
        stroke="currentColor"
        strokeWidth="1.7"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export function PortalShell({ children }: { children: ReactNode }) {
  const { user, loading, logout } = useAuth();
  const { status: fee } = useFeeStatus();
  const pathname = usePathname();
  const router = useRouter();
  const reduce = useReducedMotion();
  const [moreOpen, setMoreOpen] = useState(false);

  useEffect(() => {
    if (!loading && !user) router.replace("/student");
  }, [loading, user, router]);

  useEffect(() => { setMoreOpen(false); }, [pathname]);

  if (loading || !user) {
    return (
      <div className="grid min-h-screen place-items-center">
        <div className="shimmer h-12 w-12 rounded-full" />
      </div>
    );
  }

  // Hard fee block: the whole portal is replaced by the fee screen (PRD §6.8).
  if (fee?.block === "hard") {
    return <FeeBlockedScreen status={fee} onSignOut={() => logout().then(() => router.replace("/student"))} />;
  }

  return (
    <div className="min-h-screen md:grid md:grid-cols-[240px_1fr]">
      <CommandPalette />

      {/* Desktop sidebar */}
      <aside className="hidden border-r border-line bg-white md:flex md:flex-col">
        <div className="flex items-center gap-2 px-6 py-5">
          <span className="grid h-8 w-8 place-items-center rounded-lg bg-ink text-white display">
            B
          </span>
          <span className="display text-ink">BrowseJobs</span>
        </div>
        <nav className="flex-1 space-y-4 overflow-y-auto px-3 pb-4">
          {navGroups.map((group) => (
            <div key={group.label}>
              <p className="mono px-3 pb-1 text-[10px] uppercase tracking-widest text-muted/70">{group.label}</p>
              <div className="space-y-0.5">
                {group.items.map((item) => {
                  const active = pathname === item.href;
                  return (
                    <Link
                      key={item.href}
                      href={item.href}
                      className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                        active ? "bg-sky text-ink" : "text-muted hover:bg-paper"
                      }`}
                    >
                      <NavIcon path={item.icon} active={active} />
                      {item.label}
                    </Link>
                  );
                })}
              </div>
            </div>
          ))}
        </nav>
        <button
          onClick={() => logout().then(() => router.replace("/student"))}
          className="m-3 rounded-lg px-3 py-2.5 text-left text-sm text-muted hover:bg-paper"
        >
          Sign out
        </button>
      </aside>

      <div className="flex min-h-screen flex-col">
        {/* Top bar */}
        <header className="flex items-center justify-between border-b border-line bg-white/80 px-5 py-3 backdrop-blur-md">
          <p className="text-sm text-muted">
            Hi, <span className="font-semibold text-ink">{user.name}</span>
          </p>
          <kbd className="mono hidden rounded border border-line px-2 py-1 text-xs text-muted sm:inline">
            ⌘K
          </kbd>
        </header>

        <motion.main
          key={pathname}
          initial={reduce ? false : { opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: durations.base, ease }}
          className="flex-1 px-5 py-8 pb-24 md:px-8 md:pb-8"
        >
          {children}
        </motion.main>
      </div>

      {/* Mobile "More" sheet — the full grouped menu (bottom bar only pins a few). */}
      {moreOpen && (
        <div className="fixed inset-0 z-40 bg-ink/40 md:hidden" onClick={() => setMoreOpen(false)}>
          <div
            className="absolute inset-x-0 bottom-0 max-h-[75vh] overflow-y-auto rounded-t-[22px] bg-white px-5 pb-24 pt-5"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="grid grid-cols-2 gap-x-4 gap-y-4">
              {navGroups.map((group) => (
                <div key={group.label}>
                  <p className="mono pb-1 text-[10px] uppercase tracking-widest text-muted">{group.label}</p>
                  <div className="space-y-0.5">
                    {group.items.map((item) => (
                      <Link
                        key={item.href}
                        href={item.href}
                        className={`flex items-center gap-2 py-1.5 text-sm font-medium ${pathname === item.href ? "text-trust" : "text-ink"}`}
                      >
                        <NavIcon path={item.icon} active={pathname === item.href} />
                        {item.label}
                      </Link>
                    ))}
                  </div>
                </div>
              ))}
            </div>
            <button onClick={() => logout().then(() => router.replace("/student"))} className="mt-5 text-sm font-medium text-muted">
              Sign out
            </button>
          </div>
        </div>
      )}

      {/* Mobile bottom tabs: a few primary destinations + More. */}
      <nav className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-line bg-white md:hidden">
        {primaryTabs.map((item) => {
          const active = pathname === item.href;
          return (
            <Link key={item.href} href={item.href} className="flex flex-col items-center gap-1 py-2.5">
              <NavIcon path={item.icon} active={active} />
              <span className={`text-[10px] ${active ? "text-trust" : "text-muted"}`}>{item.short ?? item.label}</span>
            </Link>
          );
        })}
        <button onClick={() => setMoreOpen((o) => !o)} className="flex flex-col items-center gap-1 py-2.5">
          <NavIcon path="M4 6h16M4 12h16M4 18h16" active={moreOpen} />
          <span className={`text-[10px] ${moreOpen ? "text-trust" : "text-muted"}`}>More</span>
        </button>
      </nav>
    </div>
  );
}
