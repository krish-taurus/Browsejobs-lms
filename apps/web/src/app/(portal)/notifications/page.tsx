"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Notification = {
  id: number;
  title: string;
  body: string | null;
  url: string | null;
  read_at: string | null;
  created_at: string | null;
};

export default function NotificationsPage() {
  const [items, setItems] = useState<Notification[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    apiJson<{ data: Notification[]; unread: number }>("/api/v1/me/notifications")
      .then((r) => setItems(r.data))
      .catch(() => setItems([]))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  async function markAll() {
    await apiJson("/api/v1/me/notifications/read", { method: "POST", body: JSON.stringify({}) });
    load();
  }

  return (
    <div className="mx-auto max-w-2xl">
      <div className="flex items-center justify-between">
        <div>
          <p className="kicker text-trust">Alerts</p>
          <h1 className="display mt-2 text-3xl text-ink">Notifications</h1>
        </div>
        {items.some((i) => !i.read_at) && (
          <button onClick={markAll} className="text-sm text-trust hover:underline">Mark all read</button>
        )}
      </div>

      {loading ? (
        <div className="mt-6 space-y-3">{Array.from({ length: 4 }).map((_, i) => <div key={i} className="shimmer h-16 rounded-2xl" />)}</div>
      ) : items.length === 0 ? (
        <div className="mt-6"><EmptyState title="You’re all caught up" body="Class reminders, fee notices and updates will show up here." /></div>
      ) : (
        <div className="mt-6 space-y-3">
          {items.map((n) => {
            const body = (
              <div className={`rounded-2xl border p-4 ${n.read_at ? "border-line bg-white" : "border-trust/30 bg-sky"}`}>
                <div className="flex items-center justify-between gap-2">
                  <span className="font-semibold text-ink">{n.title}</span>
                  {!n.read_at && <span className="mono text-[10px] uppercase tracking-widest text-trust">New</span>}
                </div>
                {n.body && <p className="mt-1 text-sm text-muted">{n.body}</p>}
              </div>
            );
            return n.url ? <Link key={n.id} href={n.url}>{body}</Link> : <div key={n.id}>{body}</div>;
          })}
        </div>
      )}
    </div>
  );
}
