"use client";

import Link from "next/link";
import { use, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError, apiJson } from "@/lib/api";
import { BatchClasses } from "@/components/admin/BatchClasses";
import { BatchDispatch } from "@/components/admin/BatchDispatch";
import { BatchTrainers } from "@/components/admin/BatchTrainers";

type Member = {
  id: number;
  status: string;
  student: { id: number; name: string; email: string | null; phone: string | null };
};

type BatchDetail = {
  id: number;
  number: string;
  type: string;
  capacity: number | null;
  course: { id: number; code: string; name: string };
  members: Member[];
};

type BatchRow = { id: number; number: string };

const STATUS_STYLES: Record<string, string> = {
  reserved: "bg-sky text-deep",
  payment_pending: "bg-amber/15 text-ink2",
  enrolled: "bg-verify-bg text-verify",
  completed: "bg-verify-bg text-verify",
  dropped: "bg-warn/10 text-warn",
  transferred: "bg-paper text-muted",
};

export default function AdminBatchDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [add, setAdd] = useState({ name: "", phone: "", email: "" });
  const [csv, setCsv] = useState("");
  const [importSummary, setImportSummary] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "batch", id],
    queryFn: () => apiJson<{ data: BatchDetail }>(`/api/v1/admin/batches/${id}`),
  });

  const { data: allBatches } = useQuery({
    queryKey: ["admin", "batches"],
    queryFn: () => apiJson<{ data: BatchRow[] }>("/api/v1/admin/batches"),
  });

  const refresh = () => queryClient.invalidateQueries({ queryKey: ["admin", "batch", id] });

  const onError = (err: unknown) =>
    setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Request failed.");

  const addMember = useMutation({
    mutationFn: () =>
      apiJson(`/api/v1/admin/batches/${id}/members`, {
        method: "POST",
        body: JSON.stringify({ name: add.name, phone: add.phone || null, email: add.email || null }),
      }),
    onSuccess: () => {
      setAdd({ name: "", phone: "", email: "" });
      setError(null);
      void refresh();
    },
    onError,
  });

  const importCsv = useMutation({
    mutationFn: () =>
      apiJson<{ data: { imported: number; skipped: { row: number; reason: string }[] } }>(
        `/api/v1/admin/batches/${id}/import`,
        { method: "POST", body: JSON.stringify({ csv }) },
      ),
    onSuccess: (res) => {
      setCsv("");
      setError(null);
      setImportSummary(
        `Imported ${res.data.imported}; skipped ${res.data.skipped.length}` +
          (res.data.skipped.length
            ? ` (${res.data.skipped.map((s) => `row ${s.row}: ${s.reason}`).join(" · ")})`
            : ""),
      );
      void refresh();
    },
    onError,
  });

  const act = useMutation({
    mutationFn: ({ path, body }: { path: string; body: object }) =>
      apiJson(path, { method: "POST", body: JSON.stringify(body) }),
    onSuccess: () => {
      setError(null);
      void refresh();
    },
    onError,
  });

  const complete = useMutation({
    mutationFn: () =>
      apiJson<{ data: { converted: number; skipped: unknown[] } }>(`/api/v1/admin/batches/${id}/complete`, {
        method: "POST",
        body: JSON.stringify({}),
      }),
    onSuccess: (res) => {
      setError(null);
      setImportSummary(`Bootcamp completed — ${res.data.converted} attendee(s) converted to the paid batch.`);
      void refresh();
    },
    onError,
  });

  const completeMasterclass = useMutation({
    mutationFn: () =>
      apiJson<{ data: { attendees: number } }>(`/api/v1/admin/batches/${id}/complete-masterclass`, {
        method: "POST",
        body: JSON.stringify({}),
      }),
    onSuccess: (res) => {
      setError(null);
      setImportSummary(`Masterclass completed — a review request went to ${res.data.attendees} attendee(s).`);
      void refresh();
    },
    onError,
  });

  const publishSelfPaced = useMutation({
    mutationFn: () =>
      apiJson<{ data: { price_paise: number } }>(`/api/v1/admin/batches/${id}/publish-self-paced`, {
        method: "POST",
        body: JSON.stringify({}),
      }),
    onSuccess: () => {
      setError(null);
      setImportSummary("Published as a self-paced recorded course — it's now in the store.");
    },
    onError,
  });

  const batch = data?.data;
  const inputCls =
    "rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust";

  function transfer(member: Member) {
    const options = (allBatches?.data ?? []).filter((b) => b.id !== Number(id));
    const target = window.prompt(
      `Transfer ${member.student.name} to which batch?\n` +
        options.map((b) => `${b.id}: ${b.number}`).join("\n"),
    );
    if (!target) return;
    const reason = window.prompt("Reason for transfer?") ?? "";
    if (!reason) return;
    act.mutate({
      path: `/api/v1/admin/members/${member.id}/transfer`,
      body: { target_batch_id: Number(target), reason },
    });
  }

  function remove(member: Member) {
    const reason = window.prompt(`Reason for removing ${member.student.name}?`);
    if (!reason) return;
    act.mutate({ path: `/api/v1/admin/members/${member.id}/remove`, body: { reason } });
  }

  return (
    <div className="mx-auto max-w-5xl">
      <Link href="/admin/batches" className="text-sm text-trust hover:underline">← All batches</Link>
      <p className="kicker mt-4 text-trust">Batch · {batch?.course.name}</p>
      <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
        <h1 className="display mono text-3xl text-ink">{batch?.number ?? "…"}</h1>
        {batch?.type === "bootcamp" && (
          <button
            onClick={() => {
              if (window.confirm("Complete this bootcamp and convert all attendees to the linked paid batch?")) complete.mutate();
            }}
            disabled={complete.isPending}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white hover:bg-deep disabled:opacity-50"
          >
            {complete.isPending ? "Converting…" : "Complete bootcamp & convert"}
          </button>
        )}
        {batch?.type === "masterclass" && (
          <button
            onClick={() => {
              if (window.confirm("Complete this masterclass and send a review request to every attendee?")) completeMasterclass.mutate();
            }}
            disabled={completeMasterclass.isPending}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white hover:bg-deep disabled:opacity-50"
          >
            {completeMasterclass.isPending ? "Sending…" : "Complete masterclass & request reviews"}
          </button>
        )}
        {batch?.type === "paid" && (
          <button
            onClick={() => {
              if (window.confirm("Publish this completed batch's recordings as a self-paced course?")) publishSelfPaced.mutate();
            }}
            disabled={publishSelfPaced.isPending}
            className="rounded-full border border-line px-5 py-2 text-sm font-semibold text-ink hover:bg-paper disabled:opacity-50"
          >
            {publishSelfPaced.isPending ? "Publishing…" : "Publish as self-paced"}
          </button>
        )}
      </div>

      {error && (
        <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">
          {error} <button onClick={() => setError(null)} className="mono underline">dismiss</button>
        </p>
      )}
      {importSummary && (
        <p className="mt-4 rounded-[10px] bg-verify-bg px-3 py-2 text-sm text-ink">
          {importSummary}{" "}
          <button onClick={() => setImportSummary(null)} className="mono underline">dismiss</button>
        </p>
      )}

      <div className="mt-8 grid gap-5 lg:grid-cols-2">
        {/* Silo add */}
        <div className="rounded-[14px] border border-line bg-white p-5">
          <p className="kicker text-muted">Add a student</p>
          <form
            onSubmit={(e) => {
              e.preventDefault();
              addMember.mutate();
            }}
            className="mt-3 space-y-2.5"
          >
            <input required value={add.name} onChange={(e) => setAdd({ ...add, name: e.target.value })} placeholder="Name" className={`${inputCls} w-full`} />
            <div className="flex gap-2.5">
              <input value={add.phone} onChange={(e) => setAdd({ ...add, phone: e.target.value })} placeholder="Phone" className={`${inputCls} flex-1`} />
              <input value={add.email} onChange={(e) => setAdd({ ...add, email: e.target.value })} placeholder="Email" type="email" className={`${inputCls} flex-1`} />
            </div>
            <button
              disabled={addMember.isPending || !add.name || (!add.phone && !add.email)}
              className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              {addMember.isPending ? "Adding…" : "Add to roster"}
            </button>
          </form>
        </div>

        {/* CSV import */}
        <div className="rounded-[14px] border border-line bg-white p-5">
          <p className="kicker text-muted">Bulk import (CSV)</p>
          <form
            onSubmit={(e) => {
              e.preventDefault();
              importCsv.mutate();
            }}
            className="mt-3 space-y-2.5"
          >
            <textarea
              value={csv}
              onChange={(e) => setCsv(e.target.value)}
              placeholder={"name,phone,email\nAsha,+9190000001,asha@x.com"}
              rows={4}
              className={`${inputCls} mono w-full text-xs`}
            />
            <button
              disabled={importCsv.isPending || !csv.trim()}
              className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              {importCsv.isPending ? "Importing…" : "Import rows"}
            </button>
          </form>
        </div>
      </div>

      {/* Roster */}
      {isLoading ? (
        <div className="mt-6 space-y-3">
          {Array.from({ length: 3 }).map((_, i) => <div key={i} className="shimmer h-14 rounded-[14px]" />)}
        </div>
      ) : (
        <div className="mt-6 divide-y divide-line rounded-[14px] border border-line bg-white">
          {batch?.members.length === 0 && (
            <p className="px-5 py-8 text-center text-sm text-muted">
              Roster is empty — add a student or import a CSV above.
            </p>
          )}
          {batch?.members.map((m) => (
            <div key={m.id} className="flex flex-wrap items-center gap-3 px-5 py-3.5">
              <span className="display grid h-9 w-9 place-items-center rounded-full bg-ink text-xs text-white">
                {m.student.name.charAt(0).toUpperCase()}
              </span>
              <span className="min-w-40 flex-1">
                <span className="block font-semibold text-ink">{m.student.name}</span>
                <span className="mono block text-xs text-muted">
                  {m.student.phone ?? m.student.email ?? "—"}
                </span>
              </span>
              <span
                className={`mono rounded-full px-2.5 py-1 text-[10px] uppercase tracking-widest ${
                  STATUS_STYLES[m.status] ?? "bg-paper text-muted"
                }`}
              >
                {m.status.replace("_", " ")}
              </span>
              {m.status !== "dropped" && m.status !== "transferred" && (
                <span className="flex gap-3 text-xs font-semibold">
                  {batch?.type === "bootcamp" && (m.status === "enrolled" || m.status === "reserved") && (
                    <button
                      onClick={() => act.mutate({ path: `/api/v1/admin/members/${m.id}/convert`, body: {} })}
                      className="text-verify hover:underline"
                    >
                      Convert to paid
                    </button>
                  )}
                  <button onClick={() => transfer(m)} className="text-trust hover:underline">Transfer</button>
                  <button onClick={() => remove(m)} className="text-warn hover:underline">Remove</button>
                </span>
              )}
            </div>
          ))}
        </div>
      )}

      <BatchTrainers batchId={id} />
      <BatchClasses batchId={id} />
      <BatchDispatch batchId={id} />
    </div>
  );
}
