"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useRef, useState, type ReactNode } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { useAuth } from "@/lib/auth";
import { useFeeStatus } from "@/lib/fee-status";
import { Mark } from "@/components/brand/Wordmark";
import { isNavParent, navGroups, primaryTabs } from "@/components/portal/nav";
import { CommandPalette } from "@/components/portal/CommandPalette";
import { FeeBlockedScreen } from "@/components/portal/FeeBlockedScreen";
import { FeatureLockOverlay } from "@/components/portal/FeatureLockOverlay";
import { findLockedFeature } from "@/lib/locked-features";

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

/** Rotates to point down when its section is open. */
function ChevronIcon({ open }: { open: boolean }) {
  return (
    <svg
      viewBox="0 0 24 24"
      className={`h-4 w-4 shrink-0 text-muted transition-transform duration-200 ${open ? "rotate-180" : ""}`}
      fill="none"
    >
      <path d="M6 9l6 6 6-6" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

/** The avatar in the top-right corner — click for profile + sign out. */
function ProfileMenu({
  name,
  email,
  onSignOut,
}: {
  name: string;
  email: string | null;
  onSignOut: () => void;
}) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    function onClickOutside(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    function onEscape(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false);
    }
    document.addEventListener("mousedown", onClickOutside);
    document.addEventListener("keydown", onEscape);
    return () => {
      document.removeEventListener("mousedown", onClickOutside);
      document.removeEventListener("keydown", onEscape);
    };
  }, [open]);

  const initial = name.charAt(0).toUpperCase();

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="menu"
        aria-expanded={open}
        className="flex size-9 items-center justify-center rounded-full bg-ink text-sm font-semibold text-white transition hover:opacity-90"
      >
        {initial}
      </button>

      {open && (
        <div
          role="menu"
          className="absolute right-0 top-11 z-50 w-60 overflow-hidden rounded-2xl border border-line bg-white shadow-[0_12px_32px_-8px_rgba(16,24,40,0.18)]"
        >
          <div className="border-b border-line px-4 py-3">
            <p className="truncate text-sm font-semibold text-ink">{name}</p>
            {email && <p className="truncate text-xs text-muted">{email}</p>}
          </div>
          <Link
            href="/profile"
            role="menuitem"
            onClick={() => setOpen(false)}
            className="flex items-center gap-2.5 px-4 py-2.5 text-sm text-ink hover:bg-paper"
          >
            <svg viewBox="0 0 24 24" className="size-4 text-muted" fill="none">
              <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM5 20a7 7 0 0 1 14 0" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
            My Profile
          </Link>
          <button
            type="button"
            role="menuitem"
            onClick={() => {
              setOpen(false);
              onSignOut();
            }}
            className="flex w-full items-center gap-2.5 border-t border-line px-4 py-2.5 text-left text-sm text-warn hover:bg-warn/5"
          >
            <svg viewBox="0 0 24 24" className="size-4" fill="none">
              <path d="M15 17l5-5-5-5M20 12H9M13 5H6a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h7" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
            Sign out
          </button>
        </div>
      )}
    </div>
  );
}

function PortalShellInner({ children, lockedFeatures }: { children: ReactNode; lockedFeatures: string[] }) {
  const { user, loading, logout } = useAuth();
  const { status: fee } = useFeeStatus();
  const pathname = usePathname();
  const router = useRouter();
  const reduce = useReducedMotion();
  const [moreOpen, setMoreOpen] = useState(false);
  // Collapsible nav sections (e.g. "Mock Interviews"): closed by default so
  // the sidebar isn't permanently showing every sub-link, but a section
  // whose child route you're actually on opens itself — landing on
  // /interviews should never hide the link you're currently standing on.
  const [expandedParents, setExpandedParents] = useState<Set<string>>(() => new Set());
  const toggleParent = (label: string) =>
    setExpandedParents((prev) => {
      const next = new Set(prev);
      if (next.has(label)) next.delete(label);
      else next.add(label);
      return next;
    });

  // A locked feature keeps its route and its place in the menu; only the screen
  // itself is withheld.
  const lockedSlug = findLockedFeature(pathname, lockedFeatures);

  useEffect(() => {
    if (!loading && !user) router.replace("/student");
  }, [loading, user, router]);

  useEffect(() => { setMoreOpen(false); }, [pathname]);

  // Auto-open whichever parent owns the current route. Only ever adds —
  // collapsing a section you're not on shouldn't get undone by a re-render.
  useEffect(() => {
    for (const group of navGroups) {
      for (const entry of group.items) {
        if (isNavParent(entry) && entry.children.some((c) => pathname === c.href)) {
          setExpandedParents((prev) => (prev.has(entry.label) ? prev : new Set(prev).add(entry.label)));
        }
      }
    }
  }, [pathname]);

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

      {/* Desktop sidebar: sticky and full height, so it stays put while the page scrolls */}
      <aside className="hidden border-r border-line bg-white md:sticky md:top-0 md:flex md:h-screen md:flex-col md:self-start">
        <div className="flex items-center gap-2 px-6 py-5">
          <Mark />
          <span className="display text-ink">BrowseJobs</span>
        </div>
        <nav className="flex-1 space-y-4 overflow-y-auto px-3 pb-4">
          {navGroups.map((group) => (
            <div key={group.label}>
              <p className="mono px-3 pb-1 text-[10px] uppercase tracking-widest text-muted/70">{group.label}</p>
              <div className="space-y-0.5">
                {group.items.map((entry) => {
                  if (isNavParent(entry)) {
                    const anyChildActive = entry.children.some((c) => pathname === c.href);
                    const open = expandedParents.has(entry.label);

                    return (
                      <div key={entry.label}>
                        <button
                          type="button"
                          onClick={() => toggleParent(entry.label)}
                          aria-expanded={open}
                          className="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-ink hover:bg-paper"
                        >
                          <NavIcon path={entry.icon} active={anyChildActive} />
                          <span className="flex-1 text-left">{entry.label}</span>
                          <ChevronIcon open={open} />
                        </button>
                        {open && (
                          <div className="space-y-0.5 border-l border-line pl-3 ml-5">
                            {entry.children.map((child) => {
                              const active = pathname === child.href;
                              return (
                                <Link
                                  key={child.href}
                                  href={child.href}
                                  className={`block rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                                    active ? "bg-sky text-ink" : "text-muted hover:bg-paper"
                                  }`}
                                >
                                  {child.label}
                                </Link>
                              );
                            })}
                          </div>
                        )}
                      </div>
                    );
                  }

                  const active = pathname === entry.href;
                  return (
                    <Link
                      key={entry.href}
                      href={entry.href}
                      className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                        active ? "bg-sky text-ink" : "text-muted hover:bg-paper"
                      }`}
                    >
                      <NavIcon path={entry.icon} active={active} />
                      {entry.label}
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
        {/* Top bar: pinned to the top, the page content scrolls under it */}
        <header className="sticky top-0 z-20 flex items-center justify-between border-b border-line bg-white/80 px-5 py-3 backdrop-blur-md">
          <p className="text-sm text-muted">
            Hi, <span className="font-semibold text-ink">{user.name}</span>
          </p>
          <div className="flex items-center gap-3">
            <kbd className="mono hidden rounded border border-line px-2 py-1 text-xs text-muted sm:inline">
              ⌘K
            </kbd>
            <ProfileMenu
              name={user.name}
              email={user.email}
              onSignOut={() => logout().then(() => router.replace("/student"))}
            />
          </div>
        </header>

        <motion.main
          key={pathname}
          initial={reduce ? false : { opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: durations.base, ease }}
          className="flex-1 px-5 py-8 pb-24 md:px-8 md:pb-8"
        >
          {lockedSlug ? (
            <div className="relative">
              {/* The page still renders — it is simply put behind glass, and
                  `inert` keeps clicks, taps and the Tab key out of it. */}
              <div className="pointer-events-none select-none opacity-70 blur-[1.5px]" inert>
                {children}
              </div>
              <FeatureLockOverlay slug={lockedSlug} />
            </div>
          ) : (
            children
          )}
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
                    {group.items.map((entry) =>
                      isNavParent(entry) ? (
                        <div key={entry.label}>
                          <button
                            type="button"
                            onClick={() => toggleParent(entry.label)}
                            aria-expanded={expandedParents.has(entry.label)}
                            className="flex w-full items-center gap-2 py-1.5 text-sm font-medium text-ink"
                          >
                            <NavIcon path={entry.icon} active={entry.children.some((c) => pathname === c.href)} />
                            <span className="flex-1 text-left">{entry.label}</span>
                            <ChevronIcon open={expandedParents.has(entry.label)} />
                          </button>
                          {expandedParents.has(entry.label) && (
                            <div className="ml-6 space-y-0.5 border-l border-line pl-2">
                              {entry.children.map((child) => (
                                <Link
                                  key={child.href}
                                  href={child.href}
                                  className={`block py-1.5 text-sm font-medium ${pathname === child.href ? "text-trust" : "text-ink"}`}
                                >
                                  {child.label}
                                </Link>
                              ))}
                            </div>
                          )}
                        </div>
                      ) : (
                        <Link
                          key={entry.href}
                          href={entry.href}
                          className={`flex items-center gap-2 py-1.5 text-sm font-medium ${pathname === entry.href ? "text-trust" : "text-ink"}`}
                        >
                          <NavIcon path={entry.icon} active={pathname === entry.href} />
                          {entry.label}
                        </Link>
                      ),
                    )}
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

/**
 * Portal shell wrapped in a React Query provider, so portal pages and dashboard
 * widgets (e.g. the class schedule + next-class card) share one query cache.
 */
export function PortalShell({ children, lockedFeatures = [] }: { children: ReactNode; lockedFeatures?: string[] }) {
  const [client] = useState(() => new QueryClient({
    defaultOptions: { queries: { retry: 1, staleTime: 15_000 } },
  }));

  return (
    <QueryClientProvider client={client}>
      <PortalShellInner lockedFeatures={lockedFeatures}>{children}</PortalShellInner>
    </QueryClientProvider>
  );
}
