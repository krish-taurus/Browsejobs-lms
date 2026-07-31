"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { Card, Kicker, PrimaryAction } from "@/components/viz/Surface";
import { StatusChip } from "@/components/viz/Chart";
import { INK, SERIES } from "@/components/viz/tokens";
import { ApiError, apiJson } from "@/lib/api";
import { candidateApi, type CandidateDocumentRow } from "@/lib/candidate";

/**
 * Documents a candidate uploads towards their verification (PRD-E F9).
 *
 * Each file is uploaded and assessed on its own, so a reviewer sees four
 * separate results rather than a folder. Two things are stated here rather
 * than hidden:
 *
 *  - A file we cannot read says so, with what to upload instead. A PDF or a
 *    photo would otherwise sit in a queue that never moves, and the candidate
 *    would have no way to know why.
 *  - The AI's assessment is written for the reviewer and is never shown here.
 *    A machine's list of "concerns" about someone's payslip, before a person
 *    has even looked, would be alarming and premature.
 */

const READABLE = ".docx,.txt,.md,.rtf,.csv";

export function DocumentVault() {
  const fileRef = useRef<HTMLInputElement>(null);
  const [rows, setRows] = useState<CandidateDocumentRow[] | null>(null);
  const [kinds, setKinds] = useState<{ key: string; label: string }[]>([]);
  const [kind, setKind] = useState("offer_letter");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    candidateApi
      .documents()
      .then((r) => {
        setRows(r.data);
        setKinds(r.meta.kinds);
      })
      .catch(() => setRows([]));
  }, []);

  useEffect(load, [load]);

  async function upload(file: File) {
    setBusy(true);
    setError(null);
    const form = new FormData();
    form.append("kind", kind);
    form.append("file", file);

    try {
      await apiJson("/api/v1/me/verification-documents", { method: "POST", body: form });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "That upload did not work.");
    } finally {
      setBusy(false);
      if (fileRef.current) fileRef.current.value = "";
    }
  }

  async function remove(row: CandidateDocumentRow) {
    setError(null);
    try {
      await candidateApi.deleteDocument(row.id);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not remove that.");
    }
  }

  return (
    <Card accent={SERIES[5]}>
      <Kicker>Your documents</Kicker>
      <h2 className="font-display mt-2 text-xl font-bold">Offer letters, payslips, certificates</h2>
      <p className="mt-2 max-w-2xl text-[13px] leading-relaxed" style={{ color: INK.secondary }}>
        Upload them one at a time and say what each one is. A reviewer reads every file — employers
        only ever see whether the check passed, never the document itself.
      </p>

      <div className="mt-4 flex flex-wrap items-center gap-3">
        <label className="sr-only" htmlFor="doc-kind">
          What this document is
        </label>
        <select
          id="doc-kind"
          value={kind}
          onChange={(e) => setKind(e.target.value)}
          className="rounded-xl border px-3 py-2 text-sm outline-none"
          style={{ borderColor: "rgba(255,255,255,0.14)", background: "rgba(255,255,255,0.05)", color: "#fff" }}
        >
          {kinds.map((k) => (
            <option key={k.key} value={k.key} className="text-[#0a1220]">
              {k.label}
            </option>
          ))}
        </select>

        <input
          ref={fileRef}
          type="file"
          accept={`${READABLE},.pdf,.png,.jpg,.jpeg`}
          className="hidden"
          onChange={(e) => {
            const f = e.target.files?.[0];
            if (f) void upload(f);
          }}
        />
        <PrimaryAction onClick={() => fileRef.current?.click()} disabled={busy}>
          {busy ? "Uploading…" : "Add a document"}
        </PrimaryAction>
      </div>

      <p className="mt-2 text-[12px]" style={{ color: INK.faint }}>
        A .docx or .txt can be read straight away. A PDF or photo is accepted too, but waits for a
        person — we cannot read those yet.
      </p>

      {error && (
        <p className="mt-3 text-[13px]" style={{ color: "#e05561" }}>
          {error}
        </p>
      )}

      {rows !== null && rows.length > 0 && (
        <ul className="mt-5 space-y-2.5">
          {rows.map((row) => (
            <li
              key={row.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-4"
              style={{ borderColor: "rgba(255,255,255,0.08)", background: "rgba(255,255,255,0.02)" }}
            >
              <div className="min-w-0">
                <p className="truncate text-[13px] font-semibold">
                  {row.label} <span style={{ color: INK.muted }}>— {row.original_name}</span>
                </p>
                {!row.machine_readable && (
                  <p className="mt-0.5 text-[12px]" style={{ color: "#e8bf63" }}>
                    We cannot read this format — a person will open it. A .docx or .txt is faster.
                  </p>
                )}
              </div>

              <div className="flex items-center gap-2.5">
                <StatusChip
                  tone={
                    row.review_status === "accepted"
                      ? "good"
                      : row.review_status === "rejected"
                        ? "critical"
                        : "warning"
                  }
                >
                  {row.review_status === "accepted"
                    ? "Accepted"
                    : row.review_status === "rejected"
                      ? "Not accepted"
                      : "With a reviewer"}
                </StatusChip>
                {/* Only offered while nothing has been decided — the API
                    refuses either way, and a button that fails is worse
                    than no button. */}
                {row.review_status === "pending" && (
                  <button
                    type="button"
                    onClick={() => void remove(row)}
                    className="rounded-full border px-3 py-1 text-[11px] transition-colors"
                    style={{ borderColor: "rgba(255,255,255,0.14)", color: INK.secondary }}
                  >
                    Remove
                  </button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}

      {rows !== null && rows.length === 0 && (
        <p className="mt-5 text-[13px]" style={{ color: INK.faint }}>
          Nothing uploaded yet.
        </p>
      )}
    </Card>
  );
}
