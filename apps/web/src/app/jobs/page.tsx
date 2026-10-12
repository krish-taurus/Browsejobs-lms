import type { Metadata } from "next";
import Link from "next/link";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { buildJobPosting } from "@/lib/job-posting";
import { canonical } from "@/lib/seo";
import "@/components/ap/pages/marketing.css";

export const metadata: Metadata = {
  title: "Open jobs — apply with an interview, not a CV",
  description:
    "Roles hiring directly through BrowseJobs, where you apply by taking that job's mock interview, plus fresh openings from the wider market. Free account to see your match.",
  alternates: { canonical: canonical("/jobs") },
};

/**
 * Two data rhythms share this page, and the slower one used to set the pace.
 *
 * Market roles arrive on a morning sync, so half an hour of staleness costs
 * nothing. Employer postings do not: someone publishes a role in the portal
 * and looks at this page straight away. At 1800s they saw the snapshot taken
 * before their job existed — the board said nobody was hiring while the API
 * was returning their role. A published job that is invisible for half an
 * hour reads as a broken product, and there is no way to tell from the page
 * that it is merely old.
 *
 * A minute keeps the page static and SEO-indexable while making a publish
 * feel immediate.
 */
export const revalidate = 60;

type InternalJob = {
  id: number;
  title: string;
  company: string | null;
  locations: string[];
  remote: boolean;
  skills: string[];
  experience_min_years: number | null;
  experience_max_years: number | null;
  openings: number | null;
  posted_at: string | null;
  description?: string | null;
  expires_at?: string | null;
  ctc_min_paise?: number | null;
  ctc_max_paise?: number | null;
  mock_ready: boolean;
};

type ExternalJob = {
  id: number;
  title: string;
  company: string;
  location: string | null;
  work_mode: string | null;
  skills: string[];
  seniority: string | null;
  posted_at: string | null;
  description?: string | null;
  expires_at?: string | null;
  question_count: number;
};

type Board = {
  internal: InternalJob[];
  external: ExternalJob[];
  counts: { internal: number; external: number };
};

const EMPTY: Board = { internal: [], external: [], counts: { internal: 0, external: 0 } };

async function fetchBoard(): Promise<Board> {
  const base = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";
  try {
    // Must match the page's window. Next caches the fetch independently, so
    // a shorter page revalidate alone changes nothing — the re-render just
    // re-reads the same half-hour-old response.
    const res = await fetch(`${base}/api/v1/job-board`, { next: { revalidate: 60 } });
    if (!res.ok) return EMPTY;
    const body = (await res.json()) as { data: Board };
    return body.data ?? EMPTY;
  } catch {
    return EMPTY;
  }
}

function postedLabel(iso: string | null): string {
  if (!iso) return "recently posted";
  const days = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 86_400_000));
  if (days === 0) return "today";
  if (days === 1) return "yesterday";
  return `${days} days ago`;
}

export default async function PublicJobsPage() {
  const board = await fetchBoard();

  const postings = [
    ...board.internal.map((job) =>
      buildJobPosting({
        id: `internal-${job.id}`,
        title: job.title,
        description: job.description ?? "",
        company: job.company,
        datePosted: job.posted_at,
        locations: job.locations,
        remote: job.remote,
        url: canonical(`/jobs/${job.id}`),
        directApply: true,
        salaryMinPaise: job.ctc_min_paise,
        salaryMaxPaise: job.ctc_max_paise,
      }),
    ),
    ...board.external.map((job) =>
      buildJobPosting({
        id: `external-${job.id}`,
        title: job.title,
        description: job.description ?? "",
        company: job.company,
        datePosted: job.posted_at,
        validThrough: job.expires_at,
        locations: job.location ? [job.location] : [],
        remote:
          (job.work_mode ?? "").toLowerCase() === "remote" ||
          /^remote$/i.test((job.location ?? "").trim()),
      }),
    ),
  ].filter((posting): posting is NonNullable<ReturnType<typeof buildJobPosting>> => posting !== null);

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "ItemList",
    itemListElement: postings.slice(0, 25).map((posting, i) => ({
      "@type": "ListItem",
      position: i + 1,
      item: posting,
    })),
  };

  return (
    <MarketingShell>
      {postings.length > 0 && (
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      )}
      <section className="s-white pg-hero">
        <div className="wrap center">
          <p className="eyebrow">Job board</p>
          <h1 className="h-hero">Two kinds of opening.</h1>
          <p className="lead">
            Companies hiring <span className="jobs-em">directly through BrowseJobs</span> let you apply by taking that
            job&apos;s mock interview — they see a scored interview and a transcript instead of a CV in a pile. Below
            those, fresh openings from the wider market, which we prepare you for and hand off. We never auto-apply on
            your behalf.
          </p>
          <div className="cta-row">
            <Link href="/register" className="btn btn-primary">
              Create a free account
            </Link>
            <Link href="/#free-steps" className="more">
              Book free masterclass <span className="chev" aria-hidden="true">›</span>
            </Link>
          </div>
        </div>
      </section>

      <section className="s-paper chapter jobs-board">
      <div className="wrap">
        {/* Hiring through BrowseJobs — the differentiator leads --------- */}
        <ScrollReveal delay={0.06}>
          <div className="jobs-head">
            <h2 className="pg-sub">Hiring on BrowseJobs</h2>
            <span className="eyebrow-sm jobs-direct">
              Apply with an interview
            </span>
          </div>
        </ScrollReveal>

        {board.internal.length === 0 ? (
          <ScrollReveal delay={0.08}>
            <div className="jobs-empty">
              <p>
                No employers are hiring through BrowseJobs this week. The market roles below are
                still live, and{" "}
                <Link href="/register" className="ap-link">
                  a free account
                </Link>{" "}
                gets you your match score and the likely questions for each.
              </p>
            </div>
          </ScrollReveal>
        ) : (
          <div className="jobs-grid">
            {board.internal.map((job, i) => (
              <ScrollReveal key={job.id} delay={Math.min(i, 6) * 0.04}>
                <div className="jobs-card is-direct">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="font-semibold text-ink">{job.title}</p>
                      <p className="mono mt-0.5 text-[11px] uppercase tracking-widest text-muted">
                        {[
                          job.company,
                          job.remote ? "Remote" : job.locations.join(", "),
                          job.experience_min_years !== null
                            ? `${job.experience_min_years}–${job.experience_max_years ?? "+"} yrs`
                            : null,
                        ]
                          .filter(Boolean)
                          .join(" · ")}
                      </p>
                    </div>
                    <span className="mono shrink-0 rounded-full bg-white px-2.5 py-1 text-[10px] uppercase tracking-widest text-deep">
                      {postedLabel(job.posted_at)}
                    </span>
                  </div>

                  {job.skills.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-1.5">
                      {job.skills.map((s) => (
                        <span
                          key={s}
                          className="mono rounded-full bg-white px-2 py-0.5 text-[10px] text-ink-2"
                        >
                          {s}
                        </span>
                      ))}
                    </div>
                  )}

                  <div className="mt-3 rounded-[10px] bg-white p-3">
                    <p className="mono text-[10px] uppercase tracking-widest text-verify">
                      How you apply
                    </p>
                    <p className="mt-1.5 text-xs text-ink-2">
                      Take this job&apos;s mock interview. Your score and transcript go straight to
                      the employer, and we rebuild your CV against this exact description.
                    </p>
                  </div>

                  {/* Opens the role first. Sending someone straight to
                      registration asked them to commit to a job they had not
                      been allowed to read. */}
                  <div className="mt-auto flex flex-wrap items-center gap-3 pt-4">
                    <Link
                      href={`/jobs/${job.id}`}
                      className="btn btn-primary btn-sm"
                    >
                      View role &amp; apply →
                    </Link>
                    <span className="mono text-[10px] uppercase tracking-widest text-muted">
                      Free to apply
                    </span>
                  </div>
                </div>
              </ScrollReveal>
            ))}
          </div>
        )}

        {/* Wider market ------------------------------------------------ */}
        <ScrollReveal delay={0.06}>
          <div className="jobs-head">
            <h2 className="pg-sub">From the wider market</h2>
            <span className="eyebrow-sm">
              You apply on their site
            </span>
          </div>
        </ScrollReveal>

        {board.external.length === 0 ? (
          <ScrollReveal delay={0.08}>
            <div className="jobs-empty">
              <p>
                The board refreshes with tomorrow morning&apos;s sync. Check back then — or{" "}
                <Link href="/register" className="ap-link">
                  create a free account
                </Link>{" "}
                and we&apos;ll match roles to you as they land.
              </p>
            </div>
          </ScrollReveal>
        ) : (
          <div className="jobs-grid">
            {board.external.map((job, i) => (
              <ScrollReveal key={job.id} delay={Math.min(i, 6) * 0.04}>
                <div className="jobs-card">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="font-semibold text-ink">{job.title}</p>
                      <p className="mono mt-0.5 text-[11px] uppercase tracking-widest text-muted">
                        {[job.company, job.location, job.work_mode].filter(Boolean).join(" · ")}
                      </p>
                    </div>
                    <span className="mono shrink-0 rounded-full bg-paper px-2.5 py-1 text-[10px] uppercase tracking-widest text-muted">
                      {postedLabel(job.posted_at)}
                    </span>
                  </div>

                  {job.skills.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-1.5">
                      {job.skills.map((s) => (
                        <span
                          key={s}
                          className="mono rounded-full bg-paper px-2 py-0.5 text-[10px] text-ink-2"
                        >
                          {s}
                        </span>
                      ))}
                    </div>
                  )}

                  <div className="mt-auto flex items-center gap-3 pt-4">
                    <Link
                      href="/register"
                      className="btn btn-secondary btn-sm"
                    >
                      See my match &amp; prepare →
                    </Link>
                    {job.question_count > 0 && (
                      <span className="mono text-[11px] text-muted">
                        {job.question_count} likely questions
                      </span>
                    )}
                  </div>
                </div>
              </ScrollReveal>
            ))}
          </div>
        )}

        <ScrollReveal delay={0.1}>
          <p className="fine jobs-fine">
            Market openings are aggregated from public postings and refreshed daily; those listings
            belong to the hiring companies. Nobody can guarantee employment — the market decides.
            What we put in writing is the process.
          </p>
        </ScrollReveal>
      </div>
      </section>
    </MarketingShell>
  );
}
