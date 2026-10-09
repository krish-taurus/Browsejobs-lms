"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { AuthProvider, useAuth } from "@/lib/auth";
import { listMyWorkspaces, type TaurusWorkspace } from "@/lib/taurus-floor/live";

type PortalState = { workspaces: TaurusWorkspace[]; ws: TaurusWorkspace; setWs: (id: number) => void };
const PortalContext = createContext<PortalState | null>(null);

export function usePortal(): PortalState {
  const ctx = useContext(PortalContext);
  if (!ctx) throw new Error("usePortal outside PortalShell");
  return ctx;
}

const WS_KEY = "taurus-ws";

/**
 * The Taurus client portal (/taurusai/app). A signed-in member only ever
 * sees the workspaces they belong to — the API enforces it; this shell just
 * picks which one is on screen.
 */
export function PortalShell({ children }: { children: ReactNode }) {
  const [client] = useState(() => new QueryClient({ defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: false } } }));
  return (
    <QueryClientProvider client={client}>
      <AuthProvider>
        <Guarded>{children}</Guarded>
      </AuthProvider>
    </QueryClientProvider>
  );
}

function Guarded({ children }: { children: ReactNode }) {
  const { user, loading, logout } = useAuth();
  const router = useRouter();
  const pathname = usePathname();
  const [workspaces, setWorkspaces] = useState<TaurusWorkspace[] | null>(null);
  const [wsId, setWsId] = useState<number | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    if (!loading && !user) router.replace("/taurusai/login");
  }, [loading, user, router]);

  useEffect(() => {
    if (!user) return;
    listMyWorkspaces()
      .then((list) => {
        setWorkspaces(list);
        let saved: number | null = null;
        try {
          saved = Number(localStorage.getItem(WS_KEY)) || null;
        } catch {
          /* ignore */
        }
        setWsId((list.find((w) => w.id === saved) ?? list.find((w) => w.status === "active") ?? list[0])?.id ?? null);
      })
      .catch(() => setFailed(true));
  }, [user]);

  const setWs = (id: number) => {
    setWsId(id);
    try {
      localStorage.setItem(WS_KEY, String(id));
    } catch {
      /* ignore */
    }
  };

  if (loading || !user || (!workspaces && !failed)) {
    return (
      <div className="grid min-h-screen place-items-center bg-ink">
        <div className="shimmer h-12 w-12 rounded-full" />
      </div>
    );
  }

  const ws = workspaces?.find((w) => w.id === wsId);
  if (failed || !workspaces || !ws) {
    return (
      <div className="grid min-h-screen place-items-center bg-ink px-5 text-center text-white">
        <div>
          <p className="display text-2xl">No Taurus workspace yet</p>
          <p className="mt-2 text-sm text-white/60">Ask the person who invited you to send a fresh invite link.</p>
          <button type="button" onClick={() => logout().then(() => router.replace("/taurusai/login"))} className="mt-6 rounded-full border border-white/20 px-5 py-2 text-sm font-semibold">
            Sign out
          </button>
        </div>
      </div>
    );
  }

  const canManage = ws.role === "owner" || ws.role === "admin";
  const nav = [
    { href: "/taurusai/app", label: "Command centre" },
    ...(canManage ? [{ href: "/taurusai/app/settings", label: "Brain & voice" }] : []),
  ];

  return (
    <PortalContext.Provider value={{ workspaces, ws, setWs }}>
      <div className="min-h-screen bg-paper">
        <header className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-ink px-5 py-3 text-white">
          <div className="flex items-center gap-3">
            <Link href="/taurusai" className="display text-lg tracking-[0.2em]">
              TAURUS
            </Link>
            {workspaces.length > 1 ? (
              <select
                id="portal-ws"
                value={ws.id}
                onChange={(e) => setWs(Number(e.target.value))}
                className="rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-sm text-white"
                aria-label="Workspace"
              >
                {workspaces.map((w) => (
                  <option key={w.id} value={w.id} className="text-ink">
                    {w.name}
                  </option>
                ))}
              </select>
            ) : (
              <span className="rounded-full border border-white/15 px-3 py-1 text-sm text-white/80">{ws.name}</span>
            )}
          </div>
          <nav className="flex items-center gap-1">
            {nav.map((n) => (
              <Link
                key={n.href}
                href={n.href}
                className={`rounded-full px-3 py-1.5 text-sm font-medium ${pathname === n.href ? "bg-white/15 text-white" : "text-white/65 hover:text-white"}`}
              >
                {n.label}
              </Link>
            ))}
            <button type="button" onClick={() => logout().then(() => router.replace("/taurusai/login"))} className="ml-2 rounded-full px-3 py-1.5 text-sm text-white/65 hover:text-white">
              Sign out
            </button>
          </nav>
        </header>
        {ws.status === "suspended" ? (
          <p className="m-5 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">This workspace is paused. Contact BrowseJobs to turn it back on.</p>
        ) : (
          <main className="p-3 md:p-5">{children}</main>
        )}
      </div>
    </PortalContext.Provider>
  );
}
