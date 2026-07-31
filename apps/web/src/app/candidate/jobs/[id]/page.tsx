"use client";

import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { StatusChip } from "@/components/viz/Chart";
import {
  Card,
  Deck,
  GhostAction,
  Kicker,
  PageTitle,
  PrimaryAction,
  Shimmer,
} from "@/components/viz/Surface";
import { INK, SERIES, SURFACE } from "@/components/viz/tokens";
import { ApiError } from "@/lib/api";
import { candidateApi, jobApi, STAGE_LABELS, type PublicJob } from "@/lib/candidate";

type Application = { id: number; employer_job_id: number; stage: string; mock_score: number | null };

type Offer = { sku: string; name: string; price_paise: number; grant_amount: number };

export default function CandidateJobDetailPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const jobId = Number(params.id);

  const [job, setJob] = useState<PublicJob | null>(null);
  const [application, setApplication] = useState<Application | null>(null);
  const [failed, setFailed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [offers, setOffers] = useState<Offer[] | null>(null);

  const load = useCallback(() => {
    jobApi
      .show(jobId)
      .then((r) => setJob(r.data))
      .catch(() => setFailed(true));

    jobApi
      .myApplications()
      .then((r) => setApplication(r.data.find((a) => a.employer_job_id === jobId) ?? null))
      .catch(() => setApplication(null));
  }, [jobId]);

  useEffect(load, [load]);

  async function apply() {
    setBusy(true);
    setError(null);
    try {
      await candidateApi.applyToJob(jobId);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not apply.");
    } finally {
      setBusy(false);
    }
  }

  async function startMock() {
    setBusy(true);
    setError(null);
    setOffers(null);
    try {
      const res = await candidateApi.startMock(jobId);
      router.push(`/mock/${res.data.mock_id}`);
    } catch (err) {
      // An empty wallet is an offer, not a failure — show the packs inline.
      if (err instanceof ApiError && err.status === 402) {
        const body = err.body as unknown as { error?: { offers?: Offer[] } };
        setOffers(body.error?.offers ?? []);
      } else {
        setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the mock.");
      }
    } finally {
      setBusy(false);
    }
  }

  if (failed) {
    return (
      <Deck>
        <main className="mx-auto max-w-4xl px-5 py-16">
          <Card>
            <p className="text-sm" style={{ color: INK.secondary }}>
              This role is not available. It may have been closed.
            </p>
            <div className="mt-4">
              <GhostAction href="/candidate/jobs">Back to jobs</GhostAction>
            </div>
          </Card>
        </main>
      </Deck>
    );
  }

  if (!job) {
    return (
      <Deck>
        <main className="mx-auto max-w-4xl space-y-4 px-5 py-16">
          <Shimmer className="h-28" />
          <Shimmer className="h-64" />
        </main>
      </Deck>
    );
  }

  const applied = application !== null;
  const graded = application?.mock_score != null;

  return (
    <Deck>
      <main className="mx-auto max-w-4xl space-y-6 px-5 py-12 md:py-16">
        <PageTitle
          kicker={job.company?.name ?? "Hiring on BrowseJobs"}
          title={job.title}
          sub={[
            job.remote ? "Remote" : (job.locations ?? []).join(", "),
            job.experience_min_years !== null
              ? `${job.experience_min_years}–${job.experience_max_years ?? "+"} years`
              : null,
            job.openings ? `${job.openings} opening${job.openings === 1 ? "" : "s"}` : null,
          ]
            .filter(Boolean)
            .join("  ·  ")}
          action={<GhostAction href="/candidate/jobs">All jobs</GhostAction>}
        />

        {/* The one thing to do, and its current state ----------------- */}
        <Card accent={SERIES[0]} glow>
          <Kicker color={SERIES[0]}>How this role works</Kicker>
          {!applied ? (
            <>
              <p className="font-display mt-2.5 text-2xl font-bold leading-tight">
                Apply, then take this job&apos;s mock.
              </p>
              <p className="mt-2.5 max-w-xl text-[14px] leading-relaxed" style={{ color: INK.secondary }}>
                Applying is free and rebuilds your CV against this exact description. The mock is
                what the employer ranks you on — and you can retake it as often as your pack allows,
                with your best attempt standing.
              </p>
              <div className="mt-5 flex flex-wrap items-center gap-3">
                <PrimaryAction onClick={apply} disabled={busy}>
                  {busy ? "Applying…" : "Apply — free"}
                </PrimaryAction>
              </div>
            </>
          ) : (
            <>
              <div className="mt-2.5 flex flex-wrap items-center gap-3">
                <p className="font-display text-2xl font-bold leading-tight">
                  {graded ? "Your score is with the employer." : "Take the mock to be ranked."}
                </p>
                <StatusChip tone={graded ? "good" : "warning"}>
                  {STAGE_LABELS[application.stage] ?? application.stage}
                </StatusChip>
              </div>
              <p className="mt-2.5 max-w-xl text-[14px] leading-relaxed" style={{ color: INK.secondary }}>
                {graded ? (
                  <>
                    Your best attempt scored{" "}
                    <span className="font-mono font-bold" style={{ color: SERIES[0] }}>
                      {application.mock_score}
                    </span>
                    . Retaking can only improve it — a weaker attempt never replaces this one.
                  </>
                ) : (
                  "Until you take it, the employer has nothing to rank you on. It takes about twenty minutes, camera on."
                )}
              </p>
              <div className="mt-5 flex flex-wrap items-center gap-3">
                <PrimaryAction onClick={startMock} disabled={busy}>
                  {busy ? "Starting…" : graded ? "Retake the mock" : "Take the mock"}
                </PrimaryAction>
                <GhostAction href="/candidate">Your dashboard</GhostAction>
              </div>
            </>
          )}

          {error && (
            <p className="mt-4 text-[13px]" style={{ color: "#e05561" }}>
              {error}
            </p>
          )}

          {offers && (
            <div
              className="mt-5 rounded-xl border p-4"
              style={{ borderColor: SURFACE.lineStrong, background: "rgba(255,255,255,0.03)" }}
            >
              <p className="text-[13px] font-semibold">You are out of mock credits.</p>
              <p className="mt-1 text-[12px]" style={{ color: INK.muted }}>
                Your application stands — this only affects retaking the interview.
              </p>
              <ul className="mt-3 space-y-2">
                {offers.length === 0 ? (
                  <li className="text-[12px]" style={{ color: INK.muted }}>
                    No packs are on sale right now. Ask support and we will sort it out.
                  </li>
                ) : (
                  offers.map((o) => (
                    <li key={o.sku} className="flex items-center justify-between gap-4">
                      <span className="text-[13px]">
                        {o.name}
                        <span className="ml-2 font-mono text-[11px]" style={{ color: INK.muted }}>
                          {o.grant_amount} mocks
                        </span>
                      </span>
                      <span className="font-mono text-[13px] font-bold">
                        ₹{Math.round(o.price_paise / 100)}
                      </span>
                    </li>
                  ))
                )}
              </ul>
              <div className="mt-4">
                <GhostAction href="/store">Open the store</GhostAction>
              </div>
            </div>
          )}
        </Card>

        {job.skills && job.skills.length > 0 && (
          <Card>
            <Kicker>What the mock will test</Kicker>
            <div className="mt-3 flex flex-wrap gap-1.5">
              {job.skills.map((s) => (
                <span
                  key={s}
                  className="rounded-full px-3 py-1.5 font-mono text-[11px] uppercase tracking-[0.08em]"
                  style={{ background: `${SERIES[0]}1c`, color: "#9dc2fb" }}
                >
                  {s}
                </span>
              ))}
            </div>
          </Card>
        )}

        <Card>
          <Kicker>The role</Kicker>
          <div
            className="mt-3 whitespace-pre-wrap text-[14px] leading-relaxed"
            style={{ color: INK.secondary }}
          >
            {job.description}
          </div>
        </Card>

        <p className="text-[12px] leading-relaxed" style={{ color: INK.faint }}>
          Nobody can guarantee employment — the market decides. What we put in writing is the
          process: your mock is scored against this description, the employer sees the score and the
          transcript, and you are told at every stage where you stand.
        </p>
      </main>
    </Deck>
  );
}
