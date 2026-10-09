"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { motion, useReducedMotion } from "framer-motion";
import { durations, ease } from "@/lib/motion";
import { AuthProvider, useAuth } from "@/lib/auth";
import { ApiError } from "@/lib/api";
import { employerApi, type Workspace } from "@/lib/employer";
import { EmployerMark } from "@/components/employer/EmployerMark";
import { CommandPalette } from "@/components/employer/CommandPalette";
import {
  BellIcon,
  BriefcaseIcon,
  ChevronDownIcon,
  GearIcon,
  HelpIcon,
  HomeIcon,
  PipelineIcon,
  RobotIcon,
  SearchIcon,
  UsersIcon,
} from "@/components/employer/icons";

const NAV = [
  // Top of the list and the post-login landing page (see app/employer/
  // page.tsx) — talking to it is meant to be the ordinary way in, per the
  // Taurus AI kit's own nav placement (Sept 2026). It previously lived
  // embedded in the Dashboard (see git history), which is now view-only and
  // has no room for a conversation.
  { href: "/employer/taurus-ai", label: "AI Recruiter", icon: RobotIcon, badge: "AI" },
  { href: "/employer/ai-recruiter", label: "AI Recruiter", icon: RobotIcon, badge: "Demo" },
  { href: "/employer/dashboard", label: "Dashboard", icon: HomeIcon },
  { href: "/employer/jobs", label: "Jobs", icon: BriefcaseIcon },
  { href: "/employer/pipeline", label: "Pipeline", icon: PipelineIcon },
  { href: "/employer/hiring-floor", label: "Hiring floor", icon: RobotIcon, badge: "3D" },
  { href: "/employer/team", label: "Team", icon: UsersIcon },
];

/** Sidebar-bottom utility links — real destinations, not decoration. */
const UTILITY_NAV = [
  { href: "/employer/settings", label: "Settings", icon: GearIcon },
  { href: "/employer/help", label: "Help & Support", icon: HelpIcon },
];

const titleCase = (s: string) => s.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());

/** The breadcrumb's second segment — the current section's own nav label, longest-prefix match so a nested route (e.g. /employer/jobs/12) still reads as "Jobs". */
function currentSectionLabel(pathname: string): string {
  const match = [...NAV].sort((a, b) => b.href.length - a.href.length).find((item) => pathname.startsWith(item.href));
  return match?.label ?? "Dashboard";
}

type WorkspaceState = {
  workspace: Workspace;
  workspaces: Workspace[];
  switchWorkspace: (id: number) => void;
  refreshWorkspaces: () => Promise<void>;
};

const WorkspaceContext = createContext<WorkspaceState | null>(null);

export function useWorkspace(): WorkspaceState {
  const ctx = useContext(WorkspaceContext);
  if (!ctx) throw new Error("useWorkspace outside EmployerShell");
  return ctx;
}

function CreateWorkspaceCard({ onCreated }: { onCreated: () => Promise<void> }) {
  const [name, setName] = useState("");
  const [industry, setIndustry] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await employerApi.createWorkspace({ name, industry: industry || undefined });
      await onCreated();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="bj-employer-dashboard grid min-h-screen place-items-center px-4" style={{ background: "var(--bj-dash-canvas)" }}>
      <form onSubmit={submit} className="w-full max-w-md rounded-panel border p-8 shadow-sm" style={{ borderColor: "var(--bj-dash-border)", background: "var(--bj-dash-surface)" }}>
        <p className="mono text-[11px] uppercase tracking-widest" style={{ color: "var(--bj-dash-primary)" }}>Set up your workspace</p>
        <h1 className="bj-dash-serif mt-2 text-2xl" style={{ color: "var(--bj-dash-ink)" }}>Welcome to BrowseJobs for employers</h1>
        <p className="mt-2 text-sm" style={{ color: "var(--bj-dash-muted)" }}>
          Name your company workspace. You can invite your team right after.
        </p>
        <label className="mt-6 block text-sm font-medium" style={{ color: "var(--bj-dash-ink)" }} htmlFor="ws-name">Company name</label>
        <input
          id="ws-name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          required
          className="mt-1 w-full rounded-input border bg-white px-3 py-2 text-sm outline-none transition-shadow"
          style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
          placeholder="Acme Technologies"
        />
        <label className="mt-4 block text-sm font-medium" style={{ color: "var(--bj-dash-ink)" }} htmlFor="ws-industry">Industry (optional)</label>
        <input
          id="ws-industry"
          value={industry}
          onChange={(e) => setIndustry(e.target.value)}
          className="mt-1 w-full rounded-input border bg-white px-3 py-2 text-sm outline-none transition-shadow"
          style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
          placeholder="IT Services"
        />
        {error && <p className="mt-3 rounded-xl bg-[#fde8e9] px-3 py-2 text-sm text-[#e0242c]">{error}</p>}
        <button
          disabled={busy || name.trim() === ""}
          className="mt-6 w-full rounded-input px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition disabled:opacity-45 disabled:shadow-none"
          style={{ background: "var(--bj-dash-primary)" }}
        >
          {busy ? "Creating…" : "Create workspace"}
        </button>
      </form>
    </div>
  );
}

/** What every employer page shows in place of itself while its workspace is suspended. */
function SuspendedNotice({ name }: { name: string }) {
  return (
    <div className="mx-auto flex max-w-lg flex-col items-center gap-4 rounded-panel border border-[#f3d5d6] bg-[#fde8e9] px-8 py-14 text-center">
      <span className="flex size-12 items-center justify-center rounded-full bg-white text-2xl">🔒</span>
      <h1 className="bj-dash-serif text-xl" style={{ color: "var(--bj-dash-ink)" }}>{name} is deactivated</h1>
      <p className="max-w-sm text-sm leading-relaxed text-[#65676b]">
        Your team&rsquo;s access to BrowseJobs has been paused. Nothing has been deleted — your job
        postings, applicants and history are all still here.
      </p>
      <p className="max-w-sm text-sm leading-relaxed text-[#65676b]">
        Please connect with BrowseJobs admin to restore access.
      </p>
      <a
        href="mailto:support@browsejobs.ai?subject=Reactivate%20our%20workspace"
        className="mt-2 rounded-full px-5 py-2.5 text-sm font-semibold text-white transition"
        style={{ background: "var(--bj-dash-primary)" }}
      >
        Contact BrowseJobs admin
      </a>
    </div>
  );
}

function Guarded({ children }: { children: ReactNode }) {
  const { user, loading, logout } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const reduce = useReducedMotion();
  const [menuOpen, setMenuOpen] = useState(false);
  const [accountMenuOpen, setAccountMenuOpen] = useState(false);
  const [workspaces, setWorkspaces] = useState<Workspace[] | null>(null);
  const [activeId, setActiveId] = useState<number | null>(null);

  useEffect(() => {
    setMenuOpen(false);
    setAccountMenuOpen(false);
  }, [pathname]);

  const loadWorkspaces = useCallback(async () => {
    const res = await employerApi.workspaces();
    setWorkspaces(res.data);
    setActiveId((current) => {
      if (current && res.data.some((w) => w.id === current)) return current;
      const stored = Number(localStorage.getItem("bj-employer-ws"));
      const match = res.data.find((w) => w.id === stored) ?? res.data[0];
      return match?.id ?? null;
    });
  }, []);

  useEffect(() => {
    if (!loading && !user) router.replace("/employer");
  }, [loading, user, router]);

  useEffect(() => {
    if (user) void loadWorkspaces();
  }, [user, loadWorkspaces]);

  useEffect(() => {
    if (activeId) localStorage.setItem("bj-employer-ws", String(activeId));
  }, [activeId]);

  if (loading || !user || workspaces === null) {
    return (
      <div className="bj-employer-dashboard grid min-h-screen place-items-center" style={{ background: "var(--bj-dash-canvas)" }}>
        <div className="shimmer h-12 w-12 rounded-full" style={{ background: "var(--bj-dash-border)" }} />
      </div>
    );
  }

  if (workspaces.length === 0) {
    return <CreateWorkspaceCard onCreated={loadWorkspaces} />;
  }

  const workspace = workspaces.find((w) => w.id === activeId) ?? workspaces[0];
  const sectionLabel = currentSectionLabel(pathname);

  return (
    <WorkspaceContext.Provider
      value={{
        workspace,
        workspaces,
        switchWorkspace: setActiveId,
        refreshWorkspaces: loadWorkspaces,
      }}
    >
      <CommandPalette workspaceId={workspace.id} />
      <div
        className="bj-employer-dashboard min-h-screen font-body antialiased selection:bg-[var(--bj-dash-primary)] selection:text-white md:grid md:grid-cols-[230px_1fr]"
        style={{ background: "var(--bj-dash-canvas)", color: "var(--bj-dash-ink)" }}
      >
        <aside
          className="relative hidden overflow-hidden border-r bg-white md:flex md:flex-col"
          style={{ borderColor: "var(--bj-dash-border)" }}
        >
          <Link href="/" className="flex items-center gap-2 px-6 py-5" aria-label="BrowseJobs home">
            <EmployerMark />
            <div>
              <span className="bj-dash-serif block text-lg leading-none" style={{ color: "var(--bj-dash-ink)" }}>BrowseJobs</span>
              <span className="font-mono text-[9px] uppercase tracking-[0.22em]" style={{ color: "var(--bj-dash-muted)" }}>Employer</span>
            </div>
          </Link>

          {workspaces.length > 1 ? (
            <select
              value={workspace.id}
              onChange={(e) => setActiveId(Number(e.target.value))}
              aria-label="Switch workspace"
              className="mx-3 mb-3 rounded-xl border bg-white px-3 py-2 text-sm"
              style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
            >
              {workspaces.map((w) => (
                <option key={w.id} value={w.id}>{w.name}</option>
              ))}
            </select>
          ) : (
            <div
              className="mx-3 mb-3 flex items-center justify-between rounded-xl border px-3.5 py-2.5 text-sm font-semibold"
              style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
            >
              {workspace.name}
              <ChevronDownIcon className="size-3.5 shrink-0 text-[var(--bj-dash-muted)]" />
            </div>
          )}

          <nav className="space-y-0.5 overflow-y-auto px-3 pb-4 pt-1">
            {NAV.map((item) => {
              const active = pathname.startsWith(item.href);
              const Icon = item.icon;
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className="group relative flex items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all"
                  style={active
                    ? { background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }
                    : { color: "var(--bj-dash-muted)" }}
                >
                  <Icon className="size-[18px] shrink-0" />
                  {item.label}
                  {"badge" in item && item.badge && (
                    <span
                      className="ml-auto rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide"
                      style={active ? { background: "white", color: "var(--bj-dash-primary)" } : { background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}
                    >
                      {item.badge}
                    </span>
                  )}
                </Link>
              );
            })}
          </nav>

          <div className="mt-auto space-y-0.5 px-3 pb-3">
            {UTILITY_NAV.map((item) => {
              const active = pathname.startsWith(item.href);
              const Icon = item.icon;
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className="flex items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all"
                  style={active
                    ? { background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }
                    : { color: "var(--bj-dash-muted)" }}
                >
                  <Icon className="size-[18px] shrink-0" />
                  {item.label}
                </Link>
              );
            })}
          </div>

          {/* Signed-in profile, sidebar-bottom — a real destination
              (Settings), not decoration. */}
          <Link
            href="/employer/settings"
            className="mx-3 mb-3 flex items-center gap-2.5 rounded-xl border px-3 py-2.5 transition-colors hover:bg-[var(--bj-dash-soft)]"
            style={{ borderColor: "var(--bj-dash-border)" }}
          >
            <span
              className="grid size-8 shrink-0 place-items-center rounded-full text-sm font-semibold text-white"
              style={{ background: "var(--bj-dash-primary)" }}
            >
              {user.name.charAt(0).toUpperCase()}
            </span>
            <span className="min-w-0">
              <span className="block truncate text-sm font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{user.name}</span>
              <span className="block truncate font-mono text-[10px] uppercase tracking-[0.16em]" style={{ color: "var(--bj-dash-muted)" }}>
                {titleCase(workspace.my_role ?? "member")}
              </span>
            </span>
          </Link>
        </aside>

        <div className="flex min-h-screen min-w-0 flex-col">
          <header
            className="sticky top-0 z-30 flex items-center gap-4 border-b bg-white/90 px-5 py-3 backdrop-blur-xl md:px-8"
            style={{ borderColor: "var(--bj-dash-border)" }}
          >
            <div className="min-w-0 shrink-0 text-sm">
              <span style={{ color: "var(--bj-dash-muted)" }}>Workspace</span>
              <span style={{ color: "var(--bj-dash-border)" }} className="mx-2">/</span>
              <span className="font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{sectionLabel}</span>
            </div>

            {/* Same jump-to-anywhere index the ⌘K palette already builds —
                this is a second door onto it, not a second search engine. */}
            <button
              type="button"
              onClick={() => window.dispatchEvent(new Event("bj:open-command-palette"))}
              className="hidden min-w-0 flex-1 items-center gap-2.5 rounded-full border px-4 py-2 text-left text-sm transition-colors hover:bg-white md:flex md:max-w-md"
              style={{ borderColor: "var(--bj-dash-border)", background: "var(--bj-dash-canvas)", color: "var(--bj-dash-muted)" }}
            >
              <SearchIcon className="size-4 shrink-0" />
              <span className="truncate">Search jobs and applicants…</span>
              <span className="ml-auto hidden shrink-0 font-mono text-[10px] uppercase tracking-widest lg:inline">
                ⌘K
              </span>
            </button>

            <div className="ml-auto flex shrink-0 items-center gap-2">
              {/* Always-reachable from the top bar, not just the sidebar —
                  it's the post-login landing page now, so this stays visible
                  even from a page that's scrolled the sidebar out of view. */}
              <Link
                href="/employer/taurus-ai"
                className="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-semibold transition-colors hover:bg-[var(--bj-dash-soft)]"
                style={{ borderColor: "var(--bj-dash-primary)", color: "var(--bj-dash-primary)" }}
              >
                <RobotIcon className="size-4" />
                <span className="hidden sm:inline">AI Recruiter</span>
              </Link>

              <button
                type="button"
                onClick={() => window.dispatchEvent(new Event("bj:open-command-palette"))}
                className="grid size-9 place-items-center rounded-full transition-colors hover:bg-[var(--bj-dash-soft)] md:hidden"
                style={{ color: "var(--bj-dash-muted)" }}
                aria-label="Search"
              >
                <SearchIcon />
              </button>

              <Link
                href="/employer/help"
                className="grid size-9 place-items-center rounded-full transition-colors hover:bg-[var(--bj-dash-soft)]"
                style={{ color: "var(--bj-dash-muted)" }}
                aria-label="Notifications"
                title="Notifications"
              >
                <BellIcon />
              </Link>

              <div className="relative">
                <button
                  type="button"
                  onClick={() => setAccountMenuOpen((o) => !o)}
                  className="flex items-center gap-1 rounded-full py-1 pl-1 pr-1.5 transition-colors hover:bg-[var(--bj-dash-soft)]"
                  aria-expanded={accountMenuOpen}
                  aria-label="Account menu"
                >
                  <span className="grid size-8 place-items-center rounded-full text-sm font-semibold text-white" style={{ background: "var(--bj-dash-primary)" }}>
                    {user.name.charAt(0).toUpperCase()}
                  </span>
                  <ChevronDownIcon className="size-3.5 text-[var(--bj-dash-muted)]" />
                </button>

                {accountMenuOpen && (
                  <>
                    <button
                      type="button"
                      aria-label="Close menu"
                      className="fixed inset-0 z-40 cursor-default"
                      onClick={() => setAccountMenuOpen(false)}
                    />
                    <div
                      className="absolute right-0 top-full z-50 mt-2 w-56 overflow-hidden rounded-2xl border bg-white py-1.5 shadow-lg"
                      style={{ borderColor: "var(--bj-dash-border)" }}
                    >
                      <div className="border-b px-4 py-2.5" style={{ borderColor: "var(--bj-dash-border)" }}>
                        <p className="truncate text-sm font-semibold" style={{ color: "var(--bj-dash-ink)" }}>{user.name}</p>
                        {user.email && <p className="truncate text-xs" style={{ color: "var(--bj-dash-muted)" }}>{user.email}</p>}
                      </div>
                      <Link href="/employer/settings" className="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-[var(--bj-dash-soft)]" style={{ color: "var(--bj-dash-ink)" }}>
                        <GearIcon className="size-4" />Settings
                      </Link>
                      <Link href="/" className="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-[var(--bj-dash-soft)]" style={{ color: "var(--bj-dash-ink)" }}>
                        ← Back to browsejobs.ai
                      </Link>
                      <button
                        onClick={() => logout().then(() => router.replace("/employer"))}
                        className="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm hover:bg-[var(--bj-dash-soft)]"
                        style={{ color: "var(--bj-dash-ink)" }}
                      >
                        Sign out
                      </button>
                    </div>
                  </>
                )}
              </div>

              <button
                onClick={() => setMenuOpen((o) => !o)}
                className="rounded-lg border px-3 py-1.5 text-sm font-medium md:hidden"
                style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
                aria-expanded={menuOpen}
              >
                {menuOpen ? "Close" : "Menu"}
              </button>
            </div>
          </header>

          {menuOpen && (
            <div className="border-b bg-white px-5 py-4 md:hidden" style={{ borderColor: "var(--bj-dash-border)" }}>
              <div className="space-y-1">
                {NAV.map((item) => (
                  <Link
                    key={item.href}
                    href={item.href}
                    className="block py-1 text-sm font-medium"
                    style={{ color: pathname.startsWith(item.href) ? "var(--bj-dash-primary)" : "var(--bj-dash-muted)" }}
                  >
                    {item.label}
                  </Link>
                ))}
              </div>
              <Link
                href="/"
                className="mb-3 mt-3 block text-sm font-medium transition-colors"
                style={{ color: "var(--bj-dash-muted)" }}
              >
                ← Back to browsejobs.ai
              </Link>
              <button
                onClick={() => logout().then(() => router.replace("/employer"))}
                className="mt-4 text-sm font-medium"
                style={{ color: "var(--bj-dash-muted)" }}
              >
                Sign out
              </button>
            </div>
          )}

          <motion.main
            key={pathname + workspace.id}
            initial={reduce ? false : { opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: durations.base, ease }}
            className="flex-1 px-5 py-8 md:px-8 md:py-10"
          >
            {workspace.status === "suspended" ? <SuspendedNotice name={workspace.name} /> : children}
          </motion.main>
        </div>
      </div>
    </WorkspaceContext.Provider>
  );
}

export function EmployerShell({ children }: { children: ReactNode }) {
  const [client] = useState(() => new QueryClient({
    defaultOptions: { queries: { retry: 1, staleTime: 15_000 } },
  }));

  return (
    <QueryClientProvider client={client}>
      <AuthProvider>
        <Guarded>{children}</Guarded>
      </AuthProvider>
    </QueryClientProvider>
  );
}
