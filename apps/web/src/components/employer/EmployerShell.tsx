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
import { Mark } from "@/components/brand/Wordmark";
import { CommandPalette } from "@/components/employer/CommandPalette";

const NAV = [
  { href: "/employer/dashboard", label: "Dashboard" },
  { href: "/employer/jobs", label: "Jobs" },
  { href: "/employer/team", label: "Team" },
];

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
    <div className="grid min-h-screen place-items-center bg-[#05070d] px-4">
      <form onSubmit={submit} className="w-full max-w-md rounded-panel border border-line bg-[#0a0f1c] p-8 shadow-soft">
        <p className="mono text-[11px] uppercase tracking-widest text-trust">Set up your workspace</p>
        <h1 className="display mt-2 text-2xl text-ink">Welcome to BrowseJobs for employers</h1>
        <p className="mt-2 text-sm text-muted">
          Name your company workspace. You can invite your team right after.
        </p>
        <label className="mt-6 block text-sm font-medium text-ink" htmlFor="ws-name">Company name</label>
        <input
          id="ws-name"
          value={name}
          onChange={(e) => setName(e.target.value)}
          required
          className="mt-1 w-full rounded-input border border-line px-3 py-2 text-sm"
          placeholder="Acme Technologies"
        />
        <label className="mt-4 block text-sm font-medium text-ink" htmlFor="ws-industry">Industry (optional)</label>
        <input
          id="ws-industry"
          value={industry}
          onChange={(e) => setIndustry(e.target.value)}
          className="mt-1 w-full rounded-input border border-line px-3 py-2 text-sm"
          placeholder="IT Services"
        />
        {error && <p className="mt-3 text-sm text-warn">{error}</p>}
        <button
          disabled={busy || name.trim() === ""}
          className="mt-6 w-full rounded-input bg-trust px-4 py-2.5 text-sm font-semibold text-white shadow-soft disabled:opacity-50"
        >
          {busy ? "Creating…" : "Create workspace"}
        </button>
      </form>
    </div>
  );
}

function Guarded({ children }: { children: ReactNode }) {
  const { user, loading, logout } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const reduce = useReducedMotion();
  const [menuOpen, setMenuOpen] = useState(false);
  const [workspaces, setWorkspaces] = useState<Workspace[] | null>(null);
  const [activeId, setActiveId] = useState<number | null>(null);

  useEffect(() => { setMenuOpen(false); }, [pathname]);

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
      <div className="grid min-h-screen place-items-center">
        <div className="shimmer h-12 w-12 rounded-full" />
      </div>
    );
  }

  if (workspaces.length === 0) {
    return <CreateWorkspaceCard onCreated={loadWorkspaces} />;
  }

  const workspace = workspaces.find((w) => w.id === activeId) ?? workspaces[0];

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
      <div className="min-h-screen bg-[#05070d] font-body text-white antialiased selection:bg-[#4d8ef7] selection:text-white md:grid md:grid-cols-[248px_1fr]">
        <aside className="relative hidden overflow-hidden bg-[#0a0f1c] text-white md:flex md:flex-col">
          <div className="flex items-center gap-2 px-6 py-5">
            <Mark tone="dark" />
            <div>
              <span className="display block leading-none">BrowseJobs</span>
              <span className="font-mono text-[9px] uppercase tracking-[0.22em] text-white/40">Employer</span>
            </div>
          </div>

          {workspaces.length > 1 ? (
            <select
              value={workspace.id}
              onChange={(e) => setActiveId(Number(e.target.value))}
              aria-label="Switch workspace"
              className="mx-3 mb-3 rounded-xl border border-white/10 bg-white/[0.05] px-3 py-2 text-sm text-white"
            >
              {workspaces.map((w) => (
                <option key={w.id} value={w.id} className="text-[#0a1220]">{w.name}</option>
              ))}
            </select>
          ) : (
            <p className="px-6 pb-3 text-sm font-semibold text-white/80">{workspace.name}</p>
          )}

          <nav className="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4 pt-2">
            {NAV.map((item) => {
              const active = pathname.startsWith(item.href);
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={`group relative flex items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all ${
                    active
                      ? "bg-[#0a0f1c]/[0.08] text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]"
                      : "text-white/45 hover:bg-white/[0.04] hover:text-white"
                  }`}
                >
                  {item.label}
                </Link>
              );
            })}
          </nav>

          <p className="px-6 pb-2 font-mono text-[9px] uppercase tracking-[0.2em] text-white/25">
            ⌘K to jump anywhere
          </p>
          <button
            onClick={() => logout().then(() => router.replace("/employer"))}
            className="m-3 rounded-xl px-3.5 py-2.5 text-left text-sm text-white/45 transition-colors hover:bg-white/[0.04] hover:text-white"
          >
            Sign out
          </button>
        </aside>

        <div className="flex min-h-screen flex-col">
          <header className="sticky top-0 z-30 flex items-center justify-between border-b border-white/[0.07] bg-[#05070d]/80 px-5 py-3.5 backdrop-blur-xl md:px-8">
            <p className="flex items-center gap-2.5 text-sm">
              <span className="font-semibold">{user.name}</span>
              <span className="font-mono text-[10px] uppercase tracking-[0.18em] text-white/45">
                {(workspace.my_role ?? "member").replace("_", " ")}
              </span>
            </p>
            <button
              onClick={() => setMenuOpen((o) => !o)}
              className="rounded-lg border border-line px-3 py-1.5 text-sm font-medium text-ink md:hidden"
              aria-expanded={menuOpen}
            >
              {menuOpen ? "Close" : "Menu"}
            </button>
          </header>

          {menuOpen && (
            <div className="border-b border-line bg-[#0a0f1c] px-5 py-4 md:hidden">
              <div className="space-y-1">
                {NAV.map((item) => (
                  <Link
                    key={item.href}
                    href={item.href}
                    className={`block py-1 text-sm font-medium ${pathname.startsWith(item.href) ? "text-trust" : "text-ink"}`}
                  >
                    {item.label}
                  </Link>
                ))}
              </div>
              <button
                onClick={() => logout().then(() => router.replace("/employer"))}
                className="mt-4 text-sm font-medium text-muted"
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
            {children}
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
