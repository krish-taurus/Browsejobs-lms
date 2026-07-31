import type { Metadata } from "next";
import Link from "next/link";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { ScrollReveal } from "@/components/motion/ScrollReveal";

export const metadata: Metadata = {
  title: "Open jobs — apply with an interview, not a CV",
  description:
    "Roles hiring directly through BrowseJobs, where you apply by taking that job's mock interview, plus fresh openings from the wider market. Free account to see your match.",
};

// Refreshes with the morning feed sync — re-render at most every 30 min.
export const revalidate = 1800;

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
    const res = await fetch(`${base}/api/v1/job-board`, { next: { revalidate: 1800 } });
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

  // Both segments are real JobPostings for search engines; the difference is
  // where an application goes, which is a product distinction, not a schema one.
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "ItemList",
    itemListElement: [
      ...board.internal.map((j) => ({
        title: j.title,
        company: j.company ?? "BrowseJobs hiring partner",
        location: j.remote ? "Remote" : (j.locations[0] ?? null),
        posted: j.posted_at,
      })),
      ...board.external.map((j) => ({
        title: j.title,
        company: j.company,
        location: j.location,
        posted: j.posted_at,
      })),
    ]
      .slice(0, 25)
      .map((j, i) => ({
        "@type": "ListItem",
        position: i + 1,
        item: {
          "@type": "JobPosting",
          title: j.title,
          datePosted: j.posted ?? undefined,
          hiringOrganization: { "@type": "Organization", name: j.company },
          jobLocation: j.location
            ? {
                "@type": "Place",
                address: {
                  "@type": "PostalAddress",
                  addressLocality: j.location,
                  addressCountry: "IN",
                },
              }
            : undefined,
        },
      })),
  };

  return (
    <MarketingShell>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <div className="mx-auto max-w-6xl px-5 py-16 md:py-24">
        <ScrollReveal>
          <p className="kicker text-trust">Job board</p>
          <h1 className="display mt-3 max-w-3xl text-4xl text-ink md:text-5xl">
            Two kinds of opening.
          </h1>
          <p className="mt-4 max-w-2xl text-muted">
            Companies hiring <span className="text-ink">directly through BrowseJobs</span> let you
            apply by taking that job&apos;s mock interview — they see a scored interview and a
            transcript instead of a CV in a pile. Below those, fresh openings from the wider market,
            which we prepare you for and hand off. We never auto-apply on your behalf.
          </p>
          <div className="mt-6 flex flex-wrap gap-3">
            <Link
              href="/register"
              className="rounded-full bg-trust px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-deep"
            >
              Create a free account
            </Link>
            <Link
              href="/#free-steps"
              className="rounded-full border border-line bg-white px-6 py-3 text-sm font-semibold text-ink transition-colors hover:border-trust"
            >
              Book free masterclass
            </Link>
          </div>
        </ScrollReveal>

        {/* Hiring through BrowseJobs — the differentiator leads --------- */}
        <ScrollReveal delay={0.06}>
          <div className="mt-14 flex flex-wrap items-baseline justify-between gap-2">
            <h2 className="display text-2xl text-ink">Hiring on BrowseJobs</h2>
            <span className="mono text-[11px] uppercase tracking-widest text-verify">
              Apply with an interview
            </span>
          </div>
        </ScrollReveal>

        {board.internal.length === 0 ? (
          <ScrollReveal delay={0.08}>
            <div className="mt-5 rounded-[22px] border border-line bg-white p-8 text-center">
              <p className="text-muted">
                No employers are hiring through BrowseJobs this week. The market roles below are
                still live, and{" "}
                <Link href="/register" className="text-trust hover:underline">
                  a free account
                </Link>{" "}
                gets you your match score and the likely questions for each.
              </p>
            </div>
          </ScrollReveal>
        ) : (
          <div className="mt-5 grid gap-4 md:grid-cols-2">
            {board.internal.map((job, i) => (
              <ScrollReveal key={job.id} delay={Math.min(i, 6) * 0.04}>
                <div className="flex h-full flex-col rounded-[14px] border border-trust/25 bg-sky/40 p-5 shadow-soft">
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

                  <div className="mt-auto pt-4">
                    <Link
                      href="/register"
                      className="inline-block rounded-full bg-trust px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-deep"
                    >
                      Apply with a mock →
                    </Link>
                  </div>
                </div>
              </ScrollReveal>
            ))}
          </div>
        )}

        {/* Wider market ------------------------------------------------ */}
        <ScrollReveal delay={0.06}>
          <div className="mt-16 flex flex-wrap items-baseline justify-between gap-2">
            <h2 className="display text-2xl text-ink">From the wider market</h2>
            <span className="mono text-[11px] uppercase tracking-widest text-muted">
              You apply on their site
            </span>
          </div>
        </ScrollReveal>

        {board.external.length === 0 ? (
          <ScrollReveal delay={0.08}>
            <div className="mt-5 rounded-[22px] border border-line bg-white p-8 text-center">
              <p className="text-muted">
                The board refreshes with tomorrow morning&apos;s sync. Check back then — or{" "}
                <Link href="/register" className="text-trust hover:underline">
                  create a free account
                </Link>{" "}
                and we&apos;ll match roles to you as they land.
              </p>
            </div>
          </ScrollReveal>
        ) : (
          <div className="mt-5 grid gap-4 md:grid-cols-2">
            {board.external.map((job, i) => (
              <ScrollReveal key={job.id} delay={Math.min(i, 6) * 0.04}>
                <div className="flex h-full flex-col rounded-[14px] border border-line bg-white p-5 shadow-soft">
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
                      className="inline-block rounded-full border border-line bg-white px-4 py-2 text-xs font-semibold text-trust transition-colors hover:border-trust"
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
          <p className="mt-12 max-w-2xl text-xs text-muted">
            Market openings are aggregated from public postings and refreshed daily; those listings
            belong to the hiring companies. Nobody can guarantee employment — the market decides.
            What we put in writing is the process.
          </p>
        </ScrollReveal>
      </div>
    </MarketingShell>
  );
}
