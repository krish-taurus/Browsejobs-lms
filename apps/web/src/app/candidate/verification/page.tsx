"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { motion } from "framer-motion";
import { Radial, StatusChip } from "@/components/viz/Chart";
import {
  Card,
  Deck,
  GhostAction,
  Kicker,
  PageTitle,
  PrimaryAction,
  Shimmer,
} from "@/components/viz/Surface";
import { DUR, EASE, INK, SERIES, SURFACE } from "@/components/viz/tokens";
import { ApiError, apiJson } from "@/lib/api";
import { candidateApi, type VerificationCheck, type VerificationSummary } from "@/lib/candidate";
import { DocumentVault } from "@/components/candidate/DocumentVault";

const TONE: Record<VerificationCheck["status"], "good" | "warning" | "critical" | "neutral"> = {
  verified: "good",
  pending: "warning",
  failed: "critical",
  expired: "warning",
  not_started: "neutral",
};

export default function VerificationPage() {
  const [summary, setSummary] = useState<VerificationSummary | null>(null);
  const [failed, setFailed] = useState(false);

  const load = useCallback(() => {
    candidateApi
      .verification()
      .then((r) => setSummary(r.data))
      .catch(() => setFailed(true));
  }, []);

  useEffect(load, [load]);

  return (
    <Deck>
      <main className="mx-auto max-w-5xl space-y-6 px-5 py-12 md:py-16">
        <PageTitle
          kicker="Verification"
          title="Prove it once."
          highlight="Every employer sees it."
          sub="A CV is a claim. These checks are the difference between saying something and being able to show it — which is the one advantage a CV cannot give you."
          action={<GhostAction href="/candidate">Back to dashboard</GhostAction>}
        />

        {failed ? (
          <Card>
            <p className="text-sm" style={{ color: INK.secondary }}>
              Could not load your checks. Refresh to try again.
            </p>
          </Card>
        ) : !summary ? (
          <div className="space-y-4">
            <Shimmer className="h-40" />
            <Shimmer className="h-64" />
          </div>
        ) : (
          <>
            <BadgePanel summary={summary} />
            <ResumeCard />
            <DocumentVault />
            <div className="space-y-3">
              {summary.checks.map((check, i) => (
                <CheckRow key={check.kind} check={check} index={i} onDone={load} />
              ))}
            </div>
            <p className="text-[12px] leading-relaxed" style={{ color: INK.faint }}>
              Documents you upload are reviewed by a person and are never shown to employers. What an
              employer sees is the result of a check — verified or not — never the document behind it.
            </p>
          </>
        )}
      </main>
    </Deck>
  );
}

function BadgePanel({ summary }: { summary: VerificationSummary }) {
  const done = summary.badge;
  return (
    <Card accent={done ? SERIES[1] : SERIES[0]} glow>
      <div className="flex flex-wrap items-center gap-6">
        <Radial
          value={(summary.verified_count / summary.total_count) * 100}
          size={124}
          color={done ? SERIES[1] : SERIES[0]}
        >
          <span className="font-mono text-xl font-bold">
            {summary.verified_count}
            <span style={{ color: INK.muted }}>/{summary.total_count}</span>
          </span>
        </Radial>
        <div className="min-w-[240px] flex-1">
          <Kicker>Verified badge</Kicker>
          <p className="font-display mt-2 text-2xl font-bold leading-tight">
            {done ? "Your profile is verified." : "Not verified yet."}
          </p>
          <p className="mt-2 max-w-lg text-[14px] leading-relaxed" style={{ color: INK.secondary }}>
            {done
              ? "Employers see a verified stamp next to your name, and your checks are re-run before they expire."
              : `The badge needs ${summary.missing_for_badge.join(" and ")}. The other checks strengthen your profile but are not required.`}
          </p>

          {summary.observed_uplift ? (
            <p className="mt-3 text-[13px]" style={{ color: INK.secondary }}>
              Across {summary.observed_uplift.sample} graded applications,{" "}
              <span className="font-mono font-semibold" style={{ color: SERIES[1] }}>
                {summary.observed_uplift.verified_rate}%
              </span>{" "}
              of verified candidates reached a shortlist or beyond, against{" "}
              <span className="font-mono font-semibold">{summary.observed_uplift.unverified_rate}%</span>{" "}
              of unverified ones. That is a difference between two groups, not a promise about you.
            </p>
          ) : (
            <p className="mt-3 text-[13px]" style={{ color: INK.muted }}>
              We will show what verification is worth here once enough applications have been decided
              to measure it honestly. Until then we would be guessing.
            </p>
          )}
        </div>
      </div>
    </Card>
  );
}

/** Resume import — the fastest way to fill a profile, so it leads. */
function ResumeCard() {
  const fileRef = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function upload(file: File) {
    setBusy(true);
    setError(null);
    setMessage(null);
    const form = new FormData();
    form.append("file", file);

    try {
      await apiJson("/api/v1/me/cv/profile/import", { method: "POST", body: form });
      setMessage("Parsed. Your profile has been filled in — check it over before you apply.");
    } catch (err) {
      setError(
        err instanceof ApiError
          ? (err.firstError ?? err.message)
          : "That upload did not work. Try a .docx or .txt.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card accent={SERIES[3]}>
      <div className="flex flex-wrap items-center justify-between gap-5">
        <div className="min-w-[260px] flex-1">
          <Kicker>Start here</Kicker>
          <p className="font-display mt-2 text-xl font-bold">Upload your resume</p>
          <p className="mt-2 max-w-lg text-[13px] leading-relaxed" style={{ color: INK.secondary }}>
            We read it once and fill in your skills, work history and education, so you are not
            retyping what you already wrote. Accepts .docx or .txt.
          </p>
          {message && (
            <p className="mt-3 text-[13px]" style={{ color: SERIES[1] }}>
              {message}
            </p>
          )}
          {error && (
            <p className="mt-3 text-[13px]" style={{ color: "#e05561" }}>
              {error}
            </p>
          )}
        </div>
        <div>
          <input
            ref={fileRef}
            type="file"
            accept=".docx,.txt,.md,.rtf"
            className="hidden"
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) void upload(f);
            }}
          />
          <PrimaryAction onClick={() => fileRef.current?.click()} disabled={busy}>
            {busy ? "Reading…" : "Choose file"}
          </PrimaryAction>
        </div>
      </div>
    </Card>
  );
}

function CheckRow({
  check,
  index,
  onDone,
}: {
  check: VerificationCheck;
  index: number;
  onDone: () => void;
}) {
  const [open, setOpen] = useState(false);
  const [reference, setReference] = useState("");
  const [notes, setNotes] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const settled = check.status === "verified";
  const inFlight = check.status === "pending";

  async function submit() {
    setBusy(true);
    setError(null);
    try {
      await candidateApi.submitCheck(check.kind, { reference, notes });
      setOpen(false);
      onDone();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not submit.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: DUR.base, ease: EASE, delay: index * 0.06 }}
      className="overflow-hidden rounded-2xl border"
      style={{ borderColor: SURFACE.line, background: SURFACE.card }}
    >
      <div className="flex flex-wrap items-center gap-x-4 gap-y-2 p-5">
        <div className="min-w-[200px] flex-1">
          <div className="flex flex-wrap items-center gap-2.5">
            <p className="text-[15px] font-semibold">{check.label}</p>
            <StatusChip tone={TONE[check.status]}>{check.status_label}</StatusChip>
            {check.required_for_badge && (
              <span className="font-mono text-[10px]" style={{ color: INK.faint }}>
                REQUIRED FOR BADGE
              </span>
            )}
          </div>
          <p className="mt-1.5 text-[13px]" style={{ color: INK.secondary }}>
            {check.prompt}
          </p>
          <p className="mt-1 text-[12px]" style={{ color: INK.muted }}>
            Why it matters: {check.employer_value}
          </p>
          {check.failure_reason && (
            <p className="mt-2 text-[12px]" style={{ color: "#e05561" }}>
              {check.failure_reason}
            </p>
          )}
        </div>

        {settled ? (
          <span className="font-mono text-[11px]" style={{ color: INK.muted }}>
            {check.expires_at ? `valid to ${check.expires_at.slice(0, 10)}` : "valid"}
          </span>
        ) : inFlight ? (
          <span className="font-mono text-[11px]" style={{ color: INK.muted }}>
            with our team
          </span>
        ) : (
          <GhostAction onClick={() => setOpen((v) => !v)}>
            {open ? "Cancel" : check.status === "failed" ? "Try again" : "Start"}
          </GhostAction>
        )}
      </div>

      {open && (
        <motion.div
          initial={{ height: 0, opacity: 0 }}
          animate={{ height: "auto", opacity: 1 }}
          transition={{ duration: DUR.fast, ease: EASE }}
          className="border-t px-5 py-4"
          style={{ borderColor: SURFACE.line, background: "rgba(255,255,255,0.02)" }}
        >
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="block">
              <span className="text-[12px] font-semibold">Reference number</span>
              <input
                value={reference}
                onChange={(e) => setReference(e.target.value)}
                placeholder="Document or UAN number"
                className="mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none focus:ring-4"
                style={{
                  background: SURFACE.raised,
                  borderColor: SURFACE.lineStrong,
                  color: INK.primary,
                }}
              />
            </label>
            <label className="block">
              <span className="text-[12px] font-semibold">Anything we should know</span>
              <input
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Optional"
                className="mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none focus:ring-4"
                style={{
                  background: SURFACE.raised,
                  borderColor: SURFACE.lineStrong,
                  color: INK.primary,
                }}
              />
            </label>
          </div>
          {error && (
            <p className="mt-3 text-[13px]" style={{ color: "#e05561" }}>
              {error}
            </p>
          )}
          <div className="mt-4 flex items-center gap-3">
            <PrimaryAction onClick={submit} disabled={busy}>
              {busy ? "Submitting…" : "Submit for review"}
            </PrimaryAction>
            <span className="text-[12px]" style={{ color: INK.muted }}>
              A person on our team checks this. You will be told either way.
            </span>
          </div>
        </motion.div>
      )}
    </motion.div>
  );
}
