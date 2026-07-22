"use client";

import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

/**
 * Per-module trainer allocation for a batch. Pick a lead (default) trainer, then
 * split modules across trainers — e.g. Python by one, PySpark by another. A module
 * left on "Lead trainer" falls back to the default.
 */

type Trainer = { id: number; name: string };
type ModuleRow = { id: number; name: string; trainer_id: number | null };
type Payload = { lead_trainer_id: number | null; modules: ModuleRow[]; trainers: Trainer[] };

const select = "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export function BatchTrainers({ batchId }: { batchId: string }) {
  const qc = useQueryClient();
  const { data } = useQuery({
    queryKey: ["admin", "batch-trainers", batchId],
    queryFn: () => apiJson<{ data: Payload }>(`/api/v1/admin/batches/${batchId}/module-trainers`),
  });

  const [lead, setLead] = useState<number | null>(null);
  const [assign, setAssign] = useState<Record<number, number | null>>({});
  const [msg, setMsg] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    if (data?.data) {
      setLead(data.data.lead_trainer_id);
      setAssign(Object.fromEntries(data.data.modules.map((m) => [m.id, m.trainer_id])));
    }
  }, [data]);

  const save = useMutation({
    mutationFn: () =>
      apiJson(`/api/v1/admin/batches/${batchId}/module-trainers`, {
        method: "PUT",
        body: JSON.stringify({
          lead_trainer_id: lead,
          assignments: Object.entries(assign).map(([module_id, trainer_id]) => ({ module_id: Number(module_id), trainer_id })),
        }),
      }),
    onSuccess: () => {
      setErr(null);
      setMsg("Trainers saved.");
      void qc.invalidateQueries({ queryKey: ["admin", "batch-trainers", batchId] });
    },
    onError: (e) => {
      setMsg(null);
      setErr(e instanceof ApiError ? (e.firstError ?? e.message) : "Save failed.");
    },
  });

  if (!data) return null;
  const { modules, trainers } = data.data;

  return (
    <div className="mt-10">
      <h2 className="display text-xl text-ink">Trainers</h2>
      <p className="mt-1 text-sm text-muted">
        Set a lead trainer, then split modules across trainers — e.g. Python by one, PySpark by another.
        A module on “Lead trainer” uses the default. The right trainer is briefed and notified for each class.
      </p>

      <div className="mt-4 space-y-3 rounded-[14px] border border-line bg-white p-5">
        <label className="flex flex-col gap-1 text-xs font-semibold text-muted sm:flex-row sm:items-center sm:justify-between sm:gap-3">
          Lead trainer (default)
          <select className={`${select} sm:w-64`} value={lead ?? ""} onChange={(e) => setLead(e.target.value ? Number(e.target.value) : null)}>
            <option value="">— none —</option>
            {trainers.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
          </select>
        </label>

        {modules.length === 0 ? (
          <p className="text-sm text-muted">This course has no modules yet — add modules in the curriculum first.</p>
        ) : (
          <div className="divide-y divide-line border-t border-line pt-1">
            {modules.map((m) => (
              <div key={m.id} className="flex items-center justify-between gap-3 py-2">
                <span className="text-sm font-medium text-ink">{m.name}</span>
                <select
                  className={`${select} w-52`}
                  value={assign[m.id] ?? ""}
                  onChange={(e) => setAssign((a) => ({ ...a, [m.id]: e.target.value ? Number(e.target.value) : null }))}
                >
                  <option value="">Lead trainer</option>
                  {trainers.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                </select>
              </div>
            ))}
          </div>
        )}

        {err && <p className="rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{err}</p>}
        {msg && <p className="mono text-xs text-verify">{msg}</p>}
        <button
          onClick={() => save.mutate()}
          disabled={save.isPending}
          className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
        >
          {save.isPending ? "Saving…" : "Save trainers"}
        </button>
      </div>
    </div>
  );
}
