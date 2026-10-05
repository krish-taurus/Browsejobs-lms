"use client";

import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { PrivacyRequests } from "@/components/portal/PrivacyRequests";

type Pref = { preferred_channel: string; marketing_opt_in: boolean };

const CHANNEL_LABEL: Record<string, string> = {
  whatsapp: "WhatsApp",
  email: "Email",
  inapp: "In-app only",
};

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
      {/* Hero ------------------------------------------------------- */}
      <div className="relative overflow-hidden rounded-2xl border border-line bg-gradient-to-br from-sky to-white p-6">
        <span aria-hidden className="absolute inset-y-0 left-0 w-[3px] bg-trust" />
        <p className="kicker text-trust">Profile</p>
        <h1 className="display mt-1.5 text-2xl text-ink">
          Your account, <span className="text-trust">at a glance.</span>
        </h1>
        <p className="mt-1.5 max-w-md text-sm text-muted">
          Contact details, how we message you, and your data rights under DPDP — all in one place.
        </p>
      </div>

      {/* Stat strip -------------------------------------------------- */}
      <div className="mt-4 grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-line bg-line">
        <StatCell label="Account" value={user?.user_type ?? "—"} accent="var(--bj-trust)" />
        <StatCell label="Channel" value={pref ? (CHANNEL_LABEL[pref.preferred_channel] ?? pref.preferred_channel) : "—"} accent="var(--bj-verify)" />
        <StatCell label="Marketing" value={pref ? (pref.marketing_opt_in ? "On" : "Off") : "—"} accent="var(--bj-amber)" />
      </div>

      {/* Your details -------------------------------------------------- */}
      <p className="kicker mt-6 text-muted">Your details</p>
      <div className="mt-2 divide-y divide-line rounded-2xl border border-line bg-white">
        {rows.map((r) => (
          <div key={r.label} className="flex items-center justify-between px-5 py-4">
            <span className="text-sm text-muted">{r.label}</span>
            <span className="font-medium text-ink">{r.value}</span>
          </div>
        ))}
      </div>

      {/* Message preferences -------------------------------------------------- */}
      {pref && (
        <>
          <p className="kicker mt-6 text-muted">Message preferences</p>
          <div className="mt-2 rounded-2xl border border-line bg-white p-5">
            <label className="flex items-center justify-between gap-3 text-sm">
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
        </>
      )}

      <PrivacyRequests />
    </div>
  );
}

function StatCell({ label, value, accent }: { label: string; value: string; accent: string }) {
  return (
    <div className="relative bg-white p-4">
      <span aria-hidden className="absolute inset-y-0 left-0 w-[3px]" style={{ background: accent }} />
      <p className="kicker text-[10px] text-muted">{label}</p>
      <p className="display mt-1 text-lg capitalize text-ink">{value}</p>
    </div>
  );
}
