"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { ApiError } from "@/lib/api";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { controlCls, Field, GhostButton, InkPanel, Label, Pill, PrimaryButton, Skeleton, Tile } from "@/components/employer/ui";
import { Ring, TRUST, VERIFY, VIOLET } from "@/components/employer/charts";
import {
  employerApi,
  type CandidateImport,
  type CandidateSourcesData,
  type ShortlistData,
  type ShortlistEntry,
  type ShortlistSettings,
  type ShortlistSource,
} from "@/lib/employer";

/**
 * Hiring step 2 — the shortlist agent for one JD.
 *
 * It searches up to three sources the client switches on: BrowseJobs
 * students, the client's own candidate database, and their Naukri export
 * (both uploaded here as Excel/CSV), scores everyone the same explainable
 * way, and keeps the top N above a minimum score. It runs by itself when
 * the JD is published, or on "Run now". Nobody is contacted at this step.
 */

const SOURCE_LABEL: Record<ShortlistSource, string> = {
  browsejobs: "BrowseJobs students",
  client_db: "Your candidate database",
  naukri: "Naukri",
};

const SOURCE_SHORT: Record<ShortlistSource, string> = {
  browsejobs: "BrowseJobs",
  client_db: "Your database",
  naukri: "Naukri",
};

function errorText(err: unknown, fallback: string): string {
  return err instanceof ApiError ? (err.firstError ?? err.message) : fallback;
}

function when(iso: string | null): string {
  if (!iso) return "";
  return new Date(iso).toLocaleString("en-IN", { day: "numeric", month: "short", hour: "numeric", minute: "2-digit" });
}

export function ShortlistAgent({ jobId, jobStatus }: { jobId: number; jobStatus: string }) {
  const { workspace } = useWorkspace();
  const canManage = workspace.my_role === "owner" || workspace.my_role === "recruiter";
  const [data, setData] = useState<ShortlistData | null>(null);
  const [draft, setDraft] = useState<ShortlistSettings | null>(null);
  const [busy, setBusy] = useState<"save" | "run" | "invite" | "templates" | number | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [showRemoved, setShowRemoved] = useState(false);

  const apply = useCallback((next: ShortlistData) => {
    setData(next);
    setDraft(next.settings);
  }, []);

  const load = useCallback(() => {
    employerApi
      .shortlist(workspace.id, jobId)
      .then((res) => apply(res.data))
      .catch((err) => setError(errorText(err, "Could not load the shortlist.")));
  }, [workspace.id, jobId, apply]);

  useEffect(load, [load]);

  async function act(kind: "save" | "run" | "invite" | "templates" | number, call: () => Promise<{ data: ShortlistData }>) {
    setBusy(kind);
    setError(null);
    setNotice(null);
    try {
      const next = (await call()).data;
      apply(next);
      if (next.invite_result) {
        const skipped = Object.entries(next.invite_result.skipped).map(([why, n]) => `${n} ${why}`).join(", ");
        setNotice(`WhatsApp invites queued for ${next.invite_result.queued}.${skipped ? ` Skipped: ${skipped}.` : ""}`);
      }
      if (next.template_result) {
        setNotice(`WhatsApp templates: ${Object.entries(next.template_result).map(([name, what]) => `${name} ${what}`).join(" · ")}.`);
      }
    } catch (err) {
      setError(errorText(err, "That did not work. Please try again."));
    } finally {
      setBusy(null);
    }
  }

  if (!data || !draft) {
    return error ? (
      <p className="rounded-3xl border border-[var(--bj-dash-border)] bg-white p-6 text-sm text-[var(--bj-dash-score-below)]">{error}</p>
    ) : (
      <div className="space-y-4"><Skeleton className="h-40" /><Skeleton className="h-64" /></div>
    );
  }

  const dirty = JSON.stringify(draft) !== JSON.stringify(data.settings);
  const toggleSource = (s: ShortlistSource, on: boolean) =>
    setDraft({ ...draft, sources: on ? [...draft.sources, s] : draft.sources.filter((x) => x !== s) });
  const run = data.last_run;

  return (
    <div className="space-y-5">
      <InkPanel glow={VIOLET}>
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <Label dark>Shortlist agent</Label>
            <p className="bj-dash-serif mt-2.5 max-w-2xl text-xl leading-tight tracking-tight md:text-2xl">
              The best people for this role,{" "}
              <span className="bg-gradient-to-r from-[var(--bj-dash-focus)] to-[var(--bj-dash-primary)] bg-clip-text text-transparent">
                from every place you keep candidates.
              </span>
            </p>
            <p className="mt-3 max-w-xl text-sm leading-relaxed text-white/50">
              Searches BrowseJobs students, your own candidate database and your Naukri export. Everyone is scored on
              skills, experience and location, and you see why each person was picked. It runs by itself when you
              publish. Nobody is contacted at this step.
            </p>
          </div>
          {canManage && (
            <PrimaryButton
              disabled={busy !== null || jobStatus === "closed"}
              onClick={() => void act("run", () => employerApi.runShortlist(workspace.id, jobId))}
            >
              {busy === "run" ? "Running…" : "Run shortlist agent now"}
            </PrimaryButton>
          )}
        </div>
        {run && (
          <p className="mt-4 font-mono text-[11px] leading-relaxed text-white/60">
            Last run {when(run.created_at)} · {run.trigger === "publish" ? "on publish" : `by ${run.by ?? "a teammate"}`} · looked at{" "}
            {(Object.entries(run.considered) as [ShortlistSource, { considered: number }][])
              .map(([s, c]) => `${c.considered} from ${SOURCE_SHORT[s]}`)
              .join(", ") || "no sources"}{" "}
            · picked {run.shortlisted_count}
          </p>
        )}
      </InkPanel>

      {error && <p className="text-sm text-[var(--bj-dash-score-below)]">{error}</p>}

      <div className="grid gap-5 lg:grid-cols-2">
        <Tile accent={TRUST} hover={false}>
          <Label>Agent settings</Label>
          <div className="mt-4 grid grid-cols-2 gap-4">
            <Field label="How many to pick" hint="Top N for this role" htmlFor="sl-topn">
              <input
                id="sl-topn" type="number" min={1} max={100} disabled={!canManage}
                value={draft.top_n}
                onChange={(e) => setDraft({ ...draft, top_n: Number(e.target.value) })}
                className={`${controlCls} font-mono`}
              />
            </Field>
            <Field label="Minimum match" hint="0–100; lower shows more people" htmlFor="sl-min">
              <input
                id="sl-min" type="number" min={0} max={100} disabled={!canManage}
                value={draft.min_score}
                onChange={(e) => setDraft({ ...draft, min_score: Number(e.target.value) })}
                className={`${controlCls} font-mono`}
              />
            </Field>
          </div>

          <p className="mt-5 text-[13px] font-semibold text-[var(--bj-dash-ink)]">Search in</p>
          <div className="mt-2 space-y-2">
            {(["browsejobs", "client_db", "naukri"] as ShortlistSource[]).map((s) => (
              <label key={s} className="flex cursor-pointer items-center justify-between gap-3 rounded-2xl border border-[var(--bj-dash-border)] bg-white px-4 py-3">
                <span className="flex items-center gap-3">
                  <input
                    type="checkbox" disabled={!canManage}
                    checked={draft.sources.includes(s)}
                    onChange={(e) => toggleSource(s, e.target.checked)}
                    className="h-4 w-4 accent-[var(--bj-dash-primary)]"
                  />
                  <span className="text-[13px] font-semibold">{SOURCE_LABEL[s]}</span>
                </span>
                <span className="font-mono text-[11px] text-[var(--bj-dash-muted)]">
                  {s === "browsejobs" ? "trained students" : `${data.source_counts[s]} uploaded`}
                </span>
              </label>
            ))}
          </div>

          <label className="mt-4 flex cursor-pointer items-center gap-3 text-[13px]">
            <input
              type="checkbox" disabled={!canManage}
              checked={draft.auto_on_publish}
              onChange={(e) => setDraft({ ...draft, auto_on_publish: e.target.checked })}
              className="h-4 w-4 accent-[var(--bj-dash-primary)]"
            />
            Run by itself when this JD is published
          </label>

          {canManage && (
            <div className="mt-5 flex items-center gap-3">
              <PrimaryButton
                disabled={!dirty || busy !== null}
                onClick={() => void act("save", () => employerApi.saveShortlistSettings(workspace.id, jobId, draft))}
              >
                {busy === "save" ? "Saving…" : "Save settings"}
              </PrimaryButton>
              {dirty && <GhostButton onClick={() => setDraft(data.settings)}>Undo</GhostButton>}
            </div>
          )}
        </Tile>

        <CandidateSources canManage={canManage} onChanged={load} />
      </div>

      <MessageAgentCard
        data={data}
        canManage={canManage}
        busy={busy}
        notice={notice}
        jobClosed={jobStatus === "closed"}
        onInvite={() => {
          const n = data.entries.filter((e) => !e.invite).length;
          if (n === 0) return;
          if (confirm(`Send a WhatsApp interview invite to ${n} ${n === 1 ? "person" : "people"} on this shortlist? Each one picks a day for the first round.`)) {
            void act("invite", () => employerApi.sendInterviewInvites(workspace.id, jobId));
          }
        }}
        onSubmitTemplates={() => void act("templates", () => employerApi.submitInterviewTemplates(workspace.id, jobId))}
      />

      <div className="flex items-baseline justify-between">
        <Label>AI shortlist · {data.entries.length} {data.entries.length === 1 ? "person" : "people"}</Label>
        {data.removed.length > 0 && (
          <button type="button" onClick={() => setShowRemoved((v) => !v)} className="text-[12px] font-semibold text-[var(--bj-dash-primary)]">
            {showRemoved ? "Hide" : "Show"} {data.removed.length} removed
          </button>
        )}
      </div>

      {data.entries.length === 0 ? (
        <Tile accent={VIOLET} className="py-12 text-center" hover={false}>
          <p className="bj-dash-serif text-xl tracking-tight">{run ? "Nobody cleared the bar yet" : "The agent has not run yet"}</p>
          <p className="mx-auto mt-3 max-w-md text-[15px] leading-relaxed text-[var(--bj-dash-muted)]">
            {run
              ? "Upload your candidate database or Naukri export, switch more sources on, or lower the minimum match — then run it again."
              : "It runs when the JD is published. Upload your own candidates first, then press “Run shortlist agent now”."}
          </p>
        </Tile>
      ) : (
        <ul className="space-y-3">
          {data.entries.map((e) => (
            <EntryRow
              key={e.id}
              entry={e}
              canManage={canManage}
              busy={busy === e.id}
              onToggle={() => void act(e.id, () => employerApi.removeShortlistEntry(workspace.id, jobId, e.id))}
            />
          ))}
        </ul>
      )}

      {showRemoved && data.removed.length > 0 && (
        <ul className="space-y-3 opacity-70">
          {data.removed.map((e) => (
            <EntryRow
              key={e.id}
              entry={e}
              canManage={canManage}
              busy={busy === e.id}
              onToggle={() => void act(e.id, () => employerApi.restoreShortlistEntry(workspace.id, jobId, e.id))}
            />
          ))}
        </ul>
      )}
    </div>
  );
}

function EntryRow({ entry: e, canManage, busy, onToggle }: { entry: ShortlistEntry; canManage: boolean; busy: boolean; onToggle: () => void }) {
  const removed = e.status === "removed";
  const color = e.match_score >= 70 ? VIOLET : TRUST;
  const sub = [e.current_title, e.current_company].filter(Boolean).join(" · ");
  const facts = e.source === "browsejobs"
    ? [
        e.reasons.training ? `${e.reasons.training.course} · ${e.reasons.training.status}` : null,
        typeof e.reasons.readiness_index === "number" ? `Readiness ${e.reasons.readiness_index}` : null,
        e.reasons.mock_average ? `Mock avg ${e.reasons.mock_average}` : null,
        e.reasons.ai_readiness != null ? `AI readiness ${e.reasons.ai_readiness}` : null,
      ]
    : [e.reasons.experience, e.reasons.location, e.notice_period ? `Notice ${e.notice_period}` : null, e.expected_ctc ? `Expects ${e.expected_ctc}` : null];

  return (
    <li className="rounded-3xl border border-[var(--bj-dash-border)] bg-white px-5 py-4 shadow-[0_1px_2px_rgba(0,0,0,0.04),0_10px_28px_-18px_rgba(0,0,0,0.18)]">
      <div className="flex flex-wrap items-start gap-4">
        <span className="font-mono text-sm font-semibold text-[var(--bj-dash-muted)]">#{e.rank}</span>
        <Ring value={e.match_score} size={52} stroke={7} color={color}>
          <span className="font-display text-xs font-bold">{e.match_score}</span>
        </Ring>
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <p className="font-display text-base font-bold tracking-tight">{e.name}</p>
            <Pill tone={e.source === "browsejobs" ? "trust" : e.source === "naukri" ? "amber" : "dark"}>{SOURCE_SHORT[e.source]}</Pill>
            {e.invite ? <InvitePill invite={e.invite} /> : null}
          </div>
          {sub && <p className="mt-0.5 text-[12px] text-[var(--bj-dash-muted)]">{sub}</p>}
          <p className="mt-1 text-[12px] text-[var(--bj-dash-muted)]">{facts.filter(Boolean).join(" · ")}</p>
          <div className="mt-2 flex flex-wrap gap-1.5">
            {e.reasons.matched_skills.map((s) => (
              <span key={s} className="rounded-full bg-[var(--bj-dash-score-strong-bg)] px-2.5 py-1 font-mono text-[10px] text-[var(--bj-dash-score-strong)]">{s}</span>
            ))}
            {e.reasons.missing_skills.map((s) => (
              <span key={s} className="rounded-full bg-[var(--bj-dash-soft)] px-2.5 py-1 font-mono text-[10px] text-[var(--bj-dash-muted)] line-through">{s}</span>
            ))}
          </div>
          {(e.phone || e.email) && (
            <p className="mt-2 font-mono text-[11px] text-[var(--bj-dash-ink)]">{[e.phone, e.email].filter(Boolean).join(" · ")}</p>
          )}
        </div>
        {canManage && (
          <GhostButton disabled={busy} onClick={onToggle} className="!px-3 !py-1.5 !text-xs">
            {busy ? "…" : removed ? "Put back" : "Remove"}
          </GhostButton>
        )}
      </div>
    </li>
  );
}

/* --------------------------- Candidate sources --------------------------- */

function CandidateSources({ canManage, onChanged }: { canManage: boolean; onChanged: () => void }) {
  const { workspace } = useWorkspace();
  const [data, setData] = useState<CandidateSourcesData | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    employerApi.candidateSources(workspace.id).then((res) => setData(res.data)).catch(() => setData({ counts: { client_db: 0, naukri: 0 }, imports: [] }));
  }, [workspace.id]);

  useEffect(load, [load]);

  const done = (next: CandidateSourcesData) => {
    setData(next);
    onChanged();
  };

  return (
    <Tile accent={VERIFY} hover={false}>
      <Label>Your candidate sources</Label>
      <p className="mt-2 text-[13px] leading-relaxed text-[var(--bj-dash-muted)]">
        Shared by every JD in this workspace. Re-uploading the same people updates them instead of adding duplicates.
      </p>
      {error && <p className="mt-3 text-sm text-[var(--bj-dash-score-below)]">{error}</p>}
      {data === null ? (
        <Skeleton className="mt-4 h-40" />
      ) : (
        <div className="mt-4 space-y-4">
          <SourceUpload
            source="client_db"
            title="Your candidate database"
            hint="Excel (.xlsx) or CSV with columns like Name, Mobile, Email, Skills, Experience, Location."
            count={data.counts.client_db}
            last={data.imports.find((i) => i.source === "client_db")}
            canManage={canManage}
            onDone={done}
            onError={setError}
          />
          <SourceUpload
            source="naukri"
            title="Naukri"
            hint="In Naukri RMS or Resdex, select candidates and download them as Excel, then upload that file here. Key Skills, Total Experience and Current Location are read automatically."
            count={data.counts.naukri}
            last={data.imports.find((i) => i.source === "naukri")}
            canManage={canManage}
            onDone={done}
            onError={setError}
          />
        </div>
      )}
    </Tile>
  );
}

function SourceUpload({
  source, title, hint, count, last, canManage, onDone, onError,
}: {
  source: "client_db" | "naukri";
  title: string;
  hint: string;
  count: number;
  last: CandidateImport | undefined;
  canManage: boolean;
  onDone: (d: CandidateSourcesData) => void;
  onError: (m: string | null) => void;
}) {
  const { workspace } = useWorkspace();
  const input = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);

  async function upload(file: File) {
    setBusy(true);
    onError(null);
    try {
      onDone((await employerApi.importCandidates(workspace.id, source, file)).data);
    } catch (err) {
      onError(errorText(err, "Could not read that file."));
    } finally {
      setBusy(false);
      if (input.current) input.current.value = "";
    }
  }

  async function clear() {
    if (!confirm(`Remove all ${count} candidates from ${title}? You can upload them again later.`)) return;
    setBusy(true);
    try {
      onDone((await employerApi.clearCandidates(workspace.id, source)).data);
    } catch (err) {
      onError(errorText(err, "Could not remove them."));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="rounded-2xl border border-[var(--bj-dash-border)] p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-[13px] font-semibold">{title}</p>
          <p className="mt-0.5 text-[12px] leading-snug text-[var(--bj-dash-muted)]">{hint}</p>
        </div>
        <span className="shrink-0 font-mono text-sm font-semibold">{count}</span>
      </div>
      {last && (
        <p className="mt-2 font-mono text-[11px] text-[var(--bj-dash-muted)]">
          Last upload {when(last.created_at)} · {last.file_name} · {last.created} added · {last.updated} updated
          {last.skipped > 0 ? ` · ${last.skipped} skipped` : ""}
        </p>
      )}
      {last && last.errors.length > 0 && (
        <details className="mt-1 text-[11px] text-[var(--bj-dash-muted)]">
          <summary className="cursor-pointer">Why rows were skipped</summary>
          <ul className="mt-1 list-disc pl-4">{last.errors.map((m) => <li key={m}>{m}</li>)}</ul>
        </details>
      )}
      {canManage && (
        <div className="mt-3 flex items-center gap-3">
          <input
            ref={input}
            type="file"
            accept=".xlsx,.csv"
            className="hidden"
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) void upload(f);
            }}
          />
          <GhostButton disabled={busy} onClick={() => input.current?.click()} className="!py-1.5 !text-xs">
            {busy ? "Working…" : count > 0 ? "Upload more" : "Upload file"}
          </GhostButton>
          {count > 0 && (
            <button type="button" disabled={busy} onClick={() => void clear()} className="text-[11px] font-semibold text-[var(--bj-dash-muted)] hover:text-[var(--bj-dash-score-below)]">
              Remove all
            </button>
          )}
        </div>
      )}
    </div>
  );
}

/* --------------------------- Message agent --------------------------- */

const INVITE_LABEL: Record<string, string> = {
  pending: "Invite queued",
  sending: "Invite sending",
  sent: "Asked to pick a day",
  scheduled: "Interview booked",
  open: "Interview link sent",
  done: "Interviewed",
  declined: "Declined",
  failed: "Invite failed",
};

function InvitePill({ invite }: { invite: { status: string; day: string | null; error: string | null } }) {
  const tone = invite.status === "declined" || invite.status === "failed" ? "warn" : invite.status === "done" ? "verify" : "trust";
  return (
    <span title={invite.error ?? undefined}>
      <Pill tone={tone}>
        {INVITE_LABEL[invite.status] ?? invite.status}
        {invite.day && invite.status === "scheduled" ? ` · ${invite.day}` : ""}
      </Pill>
    </span>
  );
}

function MessageAgentCard({
  data, canManage, busy, notice, jobClosed, onInvite, onSubmitTemplates,
}: {
  data: ShortlistData;
  canManage: boolean;
  busy: string | number | null;
  notice: string | null;
  jobClosed: boolean;
  onInvite: () => void;
  onSubmitTemplates: () => void;
}) {
  const agent = data.message_agent;
  const notInvited = data.entries.filter((e) => !e.invite).length;
  const statuses = Object.entries(agent.templates);
  const neverSubmitted = statuses.some(([, status]) => status === null);
  const counts = agent.invites;
  const count = (k: string) => counts[k] ?? 0;

  return (
    <Tile accent={VERIFY} hover={false}>
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="max-w-2xl">
          <Label>Message agent · WhatsApp interview invites</Label>
          <p className="mt-2 text-[13px] leading-relaxed text-[var(--bj-dash-muted)]">
            Everyone on the shortlist gets a WhatsApp asking which of the next three working days suits them. Their reply books the
            first round; that morning they get a link, sign in with the same number, and take a spoken AI interview that is recorded
            and scored. L2 follows the same way once L1 is cleared.
          </p>
        </div>
        {canManage && (
          <PrimaryButton disabled={!agent.ready || notInvited === 0 || busy !== null || jobClosed} onClick={onInvite}>
            {busy === "invite" ? "Sending…" : `Send interview invites (${notInvited})`}
          </PrimaryButton>
        )}
      </div>

      <div className="mt-4 flex flex-wrap gap-x-5 gap-y-1 font-mono text-[11px] text-[var(--bj-dash-muted)]">
        <span>Asked {count("sent")}</span>
        <span>Booked {count("scheduled")}</span>
        <span>Links out {count("open")}</span>
        <span>Interviewed {count("done")}</span>
        <span>Declined {count("declined")}</span>
        {count("failed") > 0 ? <span className="text-[var(--bj-dash-score-below)]">Failed {count("failed")}</span> : null}
      </div>

      {!agent.ready && (
        <div className="mt-4 rounded-2xl border border-[var(--bj-dash-border)] bg-[var(--bj-dash-canvas)] p-3 text-[12px] leading-relaxed">
          <p className="font-semibold">Waiting for WhatsApp to approve the interview messages</p>
          <ul className="mt-1 space-y-0.5 text-[var(--bj-dash-muted)]">
            {statuses.map(([name, status]) => (
              <li key={name} className="font-mono">
                {name}: {status ? status.toLowerCase() : "not submitted"}
              </li>
            ))}
          </ul>
          {canManage && neverSubmitted ? (
            <GhostButton disabled={busy !== null} onClick={onSubmitTemplates} className="mt-2 !py-1.5 !text-xs">
              {busy === "templates" ? "Submitting…" : "Submit them to WhatsApp"}
            </GhostButton>
          ) : (
            <p className="mt-1 text-[var(--bj-dash-muted)]">Meta usually approves within minutes to a day. This button turns on by itself.</p>
          )}
        </div>
      )}
      {notice && <p className="mt-3 text-[13px] text-[var(--bj-dash-score-strong)]">{notice}</p>}
    </Tile>
  );
}
