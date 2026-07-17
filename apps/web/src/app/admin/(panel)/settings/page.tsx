"use client";

import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type Field = {
  key: string;
  label: string;
  type: "text" | "secret" | "select";
  options: string[] | null;
  set: boolean;
  value: string | null;
  preview: string | null;
};
type Group = { key: string; label: string; help: string | null; fields: Field[] };

const inputCls =
  "w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

export default function AdminSettingsPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  // Only edited fields are held here; unedited secrets stay untouched on the server.
  const [edits, setEdits] = useState<Record<string, Record<string, string>>>({});

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "settings"],
    queryFn: () => apiJson<{ data: Group[] }>("/api/v1/admin/settings"),
  });

  useEffect(() => { setSaved(false); }, [edits]);

  const save = useMutation({
    mutationFn: () => apiJson("/api/v1/admin/settings", { method: "PUT", body: JSON.stringify({ settings: edits }) }),
    onSuccess: () => {
      setError(null);
      setEdits({});
      setSaved(true);
      void qc.invalidateQueries({ queryKey: ["admin", "settings"] });
    },
    onError: (err: unknown) => setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not save."),
  });

  function setField(group: string, key: string, value: string) {
    setEdits((e) => ({ ...e, [group]: { ...(e[group] ?? {}), [key]: value } }));
  }

  const groups = data?.data ?? [];
  const dirty = Object.keys(edits).length > 0;

  return (
    <div className="mx-auto max-w-2xl">
      <p className="kicker text-trust">Platform</p>
      <h1 className="display mt-2 text-3xl text-ink">Settings</h1>
      <p className="mt-1 text-sm text-muted">
        Integration keys for the whole platform. Secrets are encrypted and never shown again once saved —
        leave a secret field blank to keep the current value.
      </p>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}
      {saved && <p className="mt-4 rounded-[10px] bg-verify-bg px-3 py-2 text-sm text-ink">Saved.</p>}

      {isLoading ? (
        <div className="mt-6 space-y-3">{Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-40 rounded-[14px]" />)}</div>
      ) : (
        <div className="mt-6 space-y-6">
          {groups.map((group) => (
            <section key={group.key} className="rounded-[14px] border border-line bg-white p-5">
              <h2 className="display text-lg text-ink">{group.label}</h2>
              {group.help && <p className="mt-1 text-xs text-muted">{group.help}</p>}
              <div className="mt-4 space-y-3">
                {group.fields.map((f) => {
                  const edited = edits[group.key]?.[f.key];
                  return (
                    <label key={f.key} className="block">
                      <span className="mb-1 flex items-center justify-between text-xs font-medium text-ink">
                        {f.label}
                        {f.type === "secret" && f.set && edited === undefined && (
                          <span className="mono text-[10px] text-muted">saved · {f.preview}</span>
                        )}
                      </span>
                      {f.type === "select" ? (
                        <select
                          value={edited ?? f.value ?? ""}
                          onChange={(e) => setField(group.key, f.key, e.target.value)}
                          className={inputCls}
                        >
                          {(f.options ?? []).map((o) => <option key={o} value={o}>{o}</option>)}
                        </select>
                      ) : (
                        <input
                          type={f.type === "secret" ? "password" : "text"}
                          value={f.type === "secret" ? (edited ?? "") : (edited ?? f.value ?? "")}
                          onChange={(e) => setField(group.key, f.key, e.target.value)}
                          placeholder={f.type === "secret" ? (f.set ? "•••••••• (leave blank to keep)" : "Not set") : ""}
                          autoComplete="off"
                          className={`${inputCls} ${f.type === "secret" ? "mono" : ""}`}
                        />
                      )}
                    </label>
                  );
                })}
              </div>
            </section>
          ))}

          <div className="sticky bottom-4 flex justify-end">
            <button
              onClick={() => save.mutate()}
              disabled={!dirty || save.isPending}
              className="rounded-full bg-trust px-6 py-2.5 text-sm font-semibold text-white shadow-lg disabled:opacity-50"
            >
              {save.isPending ? "Saving…" : "Save changes"}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
