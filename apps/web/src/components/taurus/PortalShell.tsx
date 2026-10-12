"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import "@/components/ap/pages/taurus-ui.css";
import { taurusDisplayFont } from "@/components/ap/pages/taurus-font";
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
 * picks which one is on screen. Styles: components/ap/pages/taurus-ui.css.
 */
export function PortalShell({ children }: { children: ReactNode }) {
  const [client] = useState(() => new QueryClient({ defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: false } } }));
  return (
    <QueryClientProvider client={client}>
      <AuthProvider>
        <div className={`tx-ap tx-portal ${taurusDisplayFont.variable}`}>
          <Guarded>{children}</Guarded>
        </div>
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

  const signOut = () => logout().then(() => router.replace("/taurusai/login"));

  if (loading || !user || (!workspaces && !failed)) {
    return (
      <div className="tx-center-state" aria-busy="true">
        <div className="tx-spinner" role="status" aria-label="Loading your workspace" />
      </div>
    );
  }

  const ws = workspaces?.find((w) => w.id === wsId);
  if (failed || !workspaces || !ws) {
    return (
      <div className="tx-center-state">
        <div>
          <h1 className="tx-title">No Taurus workspace yet</h1>
          <p className="tx-sub">Ask the person who invited you to send a fresh invite link.</p>
          <button type="button" onClick={signOut} className="tx-btn tx-btn-secondary" style={{ marginTop: 24 }}>
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
      <header className="tx-bar">
        <div className="tx-bar-inner">
          <div className="tx-bar-left">
            <Link href="/taurusai" className="tx-wordmark" aria-label="Taurus AI">
              <b>Taurus</b>
            </Link>
            {workspaces.length > 1 ? (
              <select id="portal-ws" value={ws.id} onChange={(e) => setWs(Number(e.target.value))} className="tx-select tx-bar-ws" aria-label="Workspace">
                {workspaces.map((w) => (
                  <option key={w.id} value={w.id}>
                    {w.name}
                  </option>
                ))}
              </select>
            ) : (
              <span className="tx-bar-pill">{ws.name}</span>
            )}
          </div>
          <nav className="tx-bar-nav" aria-label="Taurus">
            {nav.map((n) => (
              <Link key={n.href} href={n.href} aria-current={pathname === n.href ? "page" : undefined}>
                {n.label}
              </Link>
            ))}
            <span className="tx-bar-sep" aria-hidden="true" />
            <button type="button" onClick={signOut}>
              Sign out
            </button>
          </nav>
        </div>
      </header>
      {ws.status === "suspended" ? (
        <div className="tx-main">
          <p className="tx-note tx-note--warn tx-narrow">This workspace is paused. Contact BrowseJobs to turn it back on.</p>
        </div>
      ) : (
        <main className="tx-main">{children}</main>
      )}
    </PortalContext.Provider>
  );
}
