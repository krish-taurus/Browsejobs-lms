"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError, apiJson } from "@/lib/api";

type Checkin = { id: number; milestone: string; months: number | null };
type Form = { still_employed: boolean; current_company: string; current_lpa: string; would_refer: boolean; notes: string };
const emptyForm: Form = { still_employed: true, current_company: "", current_lpa: "", would_refer: true, notes: "" };

export function AlumniCheckins() {
  const [items, setItems] = useState<Checkin[] | null>(null);
  const [openId, setOpenId] = useState<number | null>(null);
  const [form, setForm] = useState<Form>(emptyForm);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  const load = useCallback(() => {
    apiJson<{ data: Checkin[] }>("/api/v1/me/alumni-checkins").then((r) => setItems(r.data)).catch(() => setItems([]));
  }, []);
  useEffect(() => { load(); }, [load]);

  async function submit(id: number) {
    setBusy(true);
    setError(null);
    try {
      await apiJson(`/api/v1/me/alumni-checkins/${id}/respond`, {
        method: "POST",
        body: JSON.stringify({
          still_employed: form.still_employed,
          current_company: form.current_company.trim() || null,
          current_lpa: form.current_lpa ? Number(form.current_lpa) : null,
          would_refer: form.would_refer,
          notes: form.notes.trim() || null,
        }),
      });
      setOpenId(null);
      setForm(emptyForm);
      setDone(true);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not save your response.");
    } finally {
      setBusy(false);
    }
  }

  if (!items || items.length === 0) {
    return done ? (
      <section className="mt-8 rounded-[14px] border border-line bg-white p-5">
        <p className="text-sm text-verify">Thanks for the update — it helps the next batch and our proof stats.</p>
      </section>
    ) : null;
  }

  return (
    <section className="mt-8 rounded-[22px] border border-line bg-white p-6">
      <p className="kicker text-trust">Alumni check-in</p>
      <h2 className="display mt-1 text-xl text-ink">How&apos;s it going since you were placed?</h2>
      <p className="mt-1 text-sm text-muted">A quick update at your {items[0].months}-month mark — it sharpens our advice and referral pipeline.</p>

      {error && <p className="mt-3 text-sm text-warn">{error}</p>}

      {items.map((c) => (
        <div key={c.id} className="mt-4 rounded-[14px] border border-line bg-paper p-4">
          {openId === c.id ? (
            <div className="space-y-3">
              <label className="flex items-center gap-2 text-sm text-ink">
                <input type="checkbox" checked={form.still_employed} onChange={(e) => setForm({ ...form, still_employed: e.target.checked })} className="accent-trust" />
                Still employed
              </label>
              <div className="grid gap-2 sm:grid-cols-2">
                <input value={form.current_company} onChange={(e) => setForm({ ...form, current_company: e.target.value })} placeholder="Current company (optional)" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
                <input value={form.current_lpa} onChange={(e) => setForm({ ...form, current_lpa: e.target.value })} inputMode="decimal" placeholder="Current CTC in LPA (optional)" className="rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
              </div>
              <label className="flex items-center gap-2 text-sm text-ink">
                <input type="checkbox" checked={form.would_refer} onChange={(e) => setForm({ ...form, would_refer: e.target.checked })} className="accent-trust" />
                Happy to refer others / a junior from BrowseJobs
              </label>
              <textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} rows={2} placeholder="Anything you'd like us to know (optional)" className="w-full resize-none rounded-[10px] border border-line bg-white px-3 py-2 text-sm outline-none focus:border-trust" />
              <div className="flex gap-2">
                <button onClick={() => submit(c.id)} disabled={busy} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">
                  {busy ? "Saving…" : "Submit"}
                </button>
                <button onClick={() => setOpenId(null)} className="rounded-full border border-line bg-white px-5 py-2 text-sm text-ink">Later</button>
              </div>
            </div>
          ) : (
            <button onClick={() => setOpenId(c.id)} className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white">
              Share my {c.months}-month update
            </button>
          )}
        </div>
      ))}
    </section>
  );
}
