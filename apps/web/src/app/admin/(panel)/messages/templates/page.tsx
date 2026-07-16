"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";

type Template = {
  id: number;
  key: string;
  channel: string;
  category: string;
  name: string | null;
  subject: string | null;
  body: string;
  active: boolean;
};

export default function TemplatesPage() {
  const queryClient = useQueryClient();
  const [selected, setSelected] = useState<Template | null>(null);
  const [body, setBody] = useState("");
  const [violations, setViolations] = useState<string[]>([]);
  const [banner, setBanner] = useState<{ kind: "ok" | "err"; text: string } | null>(null);

  const { data } = useQuery({
    queryKey: ["admin", "message-templates"],
    queryFn: () => apiJson<{ data: Template[] }>("/api/v1/admin/message-templates"),
  });

  useEffect(() => {
    if (!selected) return;
    const id = setTimeout(() => {
      apiJson<{ data: { violations: string[] } }>("/api/v1/admin/message-templates/preview", {
        method: "POST",
        body: JSON.stringify({ body }),
      }).then((r) => setViolations(r.data.violations)).catch(() => setViolations([]));
    }, 300);
    return () => clearTimeout(id);
  }, [body, selected]);

  const save = useMutation({
    mutationFn: () =>
      apiJson(`/api/v1/admin/message-templates/${selected!.id}`, {
        method: "PUT",
        body: JSON.stringify({ ...selected, body }),
      }),
    onSuccess: () => {
      setBanner({ kind: "ok", text: "Template saved." });
      void queryClient.invalidateQueries({ queryKey: ["admin", "message-templates"] });
    },
    onError: (err) => setBanner({ kind: "err", text: err instanceof ApiError ? (err.firstError ?? err.message) : "Save failed." }),
  });

  function select(t: Template) {
    setSelected(t);
    setBody(t.body);
    setViolations([]);
    setBanner(null);
  }

  return (
    <div className="mx-auto max-w-5xl">
      <Link href="/admin/messages" className="text-sm text-muted hover:text-ink">← Delivery log</Link>
      <h1 className="display mt-2 text-3xl text-ink">Message templates</h1>

      {banner && <p className={`mt-4 rounded-[10px] px-3 py-2 text-sm ${banner.kind === "ok" ? "bg-verify-bg text-verify" : "bg-warn/10 text-warn"}`}>{banner.text}</p>}

      <div className="mt-6 grid gap-6 md:grid-cols-[280px_1fr]">
        <div className="divide-y divide-line rounded-[14px] border border-line bg-white">
          {data?.data.map((t) => (
            <button key={t.id} onClick={() => select(t)} className={`flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm transition-colors hover:bg-paper ${selected?.id === t.id ? "bg-sky" : ""}`}>
              <span className="mono text-ink">{t.key}</span>
              <span className="mono rounded-full bg-paper px-2 py-0.5 text-[10px] uppercase tracking-widest text-muted">{t.channel}</span>
            </button>
          ))}
        </div>

        {selected ? (
          <div className="rounded-[14px] border border-line bg-white p-5">
            <p className="kicker text-muted">{selected.key} · {selected.channel} · {selected.category}</p>
            <textarea
              value={body}
              onChange={(e) => setBody(e.target.value)}
              rows={6}
              className="mono mt-3 w-full rounded-[10px] border border-line bg-paper px-3 py-2 text-sm text-ink outline-none focus:border-trust"
            />
            <p className="mt-2 text-xs text-muted">Variables like <span className="mono">{"{{name}}"}</span> and <span className="mono">{"{{link}}"}</span> are filled at send.</p>
            {violations.length > 0 && (
              <p className="mt-3 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">
                Banned phrase: {violations.join(", ")} — this can’t be saved.
              </p>
            )}
            <button
              onClick={() => save.mutate()}
              disabled={save.isPending || violations.length > 0}
              className="mt-4 rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              {save.isPending ? "Saving…" : "Save template"}
            </button>
          </div>
        ) : (
          <div className="grid place-items-center rounded-[14px] border border-dashed border-line bg-white p-10 text-sm text-muted">
            Select a template to edit its body.
          </div>
        )}
      </div>
    </div>
  );
}
