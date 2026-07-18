"use client";

import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { PrivacyRequests } from "@/components/portal/PrivacyRequests";

type Pref = { preferred_channel: string; marketing_opt_in: boolean };

export default function ProfilePage() {
  const { user } = useAuth();
  const [pref, setPref] = useState<Pref | null>(null);
  const [saved, setSaved] = useState(false);

  const rows = [
    { label: "Name", value: user?.name },
    { label: "Email", value: user?.email ?? "—" },
    { label: "Phone", value: user?.phone ?? "—" },
    { label: "Account type", value: user?.user_type },
  ];

  useEffect(() => {
    apiJson<{ data: Pref }>("/api/v1/me/message-preferences").then((r) => setPref(r.data)).catch(() => {});
  }, []);

  async function update(next: Pref) {
    setPref(next);
    setSaved(false);
    await apiJson("/api/v1/me/message-preferences", { method: "PUT", body: JSON.stringify(next) });
    setSaved(true);
  }

  return (
    <div className="mx-auto max-w-2xl">
      <p className="kicker text-trust">Profile</p>
      <h1 className="display mt-2 text-3xl text-ink">Your details</h1>

      <div className="mt-8 divide-y divide-line rounded-2xl border border-line bg-white">
        {rows.map((r) => (
          <div key={r.label} className="flex items-center justify-between px-5 py-4">
            <span className="text-sm text-muted">{r.label}</span>
            <span className="font-medium text-ink">{r.value}</span>
          </div>
        ))}
      </div>

      {pref && (
        <div className="mt-6 rounded-2xl border border-line bg-white p-5">
          <p className="kicker text-muted">Message preferences</p>
          <label className="mt-4 flex items-center justify-between gap-3 text-sm">
            <span className="text-ink">Preferred channel</span>
            <select
              value={pref.preferred_channel}
              onChange={(e) => update({ ...pref, preferred_channel: e.target.value })}
              className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
            >
              <option value="whatsapp">WhatsApp</option>
              <option value="email">Email</option>
              <option value="inapp">In-app only</option>
            </select>
          </label>
          <label className="mt-4 flex items-center justify-between gap-3 text-sm">
            <span className="text-ink">Marketing updates <span className="text-muted">(offers, tips)</span></span>
            <input
              type="checkbox"
              checked={pref.marketing_opt_in}
              onChange={(e) => update({ ...pref, marketing_opt_in: e.target.checked })}
              className="h-5 w-5 accent-[var(--bj-trust)]"
            />
          </label>
          {saved && <p className="mt-3 text-xs text-verify">Saved.</p>}
        </div>
      )}

      <PrivacyRequests />
    </div>
  );
}
