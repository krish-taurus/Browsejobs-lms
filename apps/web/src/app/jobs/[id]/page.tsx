import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { MarketingShell } from "@/components/landing/MarketingShell";

/**
 * One role, readable without an account (PRD-E F10).
 *
 * The board used to offer "Apply with a mock" straight from a card, with no
 * way to read the description first — asking someone to sit an interview for
 * a job they had not been allowed to read. This page is the missing step.
 *
 * Both routes in are offered honestly: applying is free and always was, and
 * the mock is what puts a scored interview in front of the employer rather
 * than a CV in a pile. Neither is hidden behind the other.
 */

export const revalidate = 60;

type Job = {
  id: number;
  title: string;
  description: string;
  role_family: string | null;
  skills: string[] | null;
  experience_min_years: number | null;
  experience_max_years: number | null;
  locations: string[] | null;
  remote: boolean;
  ctc_min_paise?: number | null;
  ctc_max_paise?: number | null;
  openings: number | null;
  company?: { name: string; industry: string | null; company_size: string | null };
  published_at: string | null;
};

async function fetchJob(id: string): Promise<{ data: Job; meta: { mock_ready: boolean } } | null> {
  const base = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";
  try {
    const res = await fetch(`${base}/api/v1/job-board/${id}`, { next: { revalidate: 60 } });
    if (!res.ok) return null;
    return await res.json();
  } catch {
    return null;
  }
}

export async function generateMetadata({ params }: { params: Promise<{ id: string }> }): Promise<Metadata> {
  const { id } = await params;
  const payload = await fetchJob(id);

  if (payload === null) return { title: "Role not found" };

  const job = payload.data;
  const company = job.company?.name ?? "an employer";

  return {
    title: `${job.title} at ${company} — apply with an interview`,
    description: job.description.slice(0, 155),
  };
}

/** Lakhs, the unit an Indian candidate actually reads a salary in. */
function ctc(paise: number | null | undefined): string | null {
  if (!paise) return null;
  return `₹${(paise / 100 / 100000).toFixed(1)}L`;
}

function experience(job: Job): string {
  if (job.experience_min_years === null) return "Any experience";
  const max = job.experience_max_years === null ? "+" : `–${job.experience_max_years}`;
  return `${job.experience_min_years}${max} yrs`;
}

export default async function PublicJobPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const payload = await fetchJob(id);

  if (payload === null) notFound();

  const job = payload.data;
  const pay = [ctc(job.ctc_min_paise), ctc(job.ctc_max_paise)].filter(Boolean).join(" – ");

  return (
    <MarketingShell>
      {/* Extra top padding clears the floating nav, which otherwise sits
          over the kicker and the back link. */}
      <article className="mx-auto max-w-4xl px-6 pb-16 pt-28 md:pb-24 md:pt-36">
        <Link
          href="/jobs"
          className="mono text-[11px] uppercase tracking-[0.2em] text-muted transition-colors hover:text-ink"
        >
          ← All jobs
        </Link>

        <header className="mt-6">
          <p className="mono text-[11px] uppercase tracking-[0.3em] text-verify">Hiring on BrowseJobs</p>
          <h1 className="display mt-3 text-4xl text-ink md:text-5xl">{job.title}</h1>
          <p className="mt-3 text-lg text-ink-2">
            {[job.company?.name, job.remote ? "Remote" : job.locations?.[0]].filter(Boolean).join(" · ")}
          </p>

          <div className="mono mt-5 flex flex-wrap gap-x-6 gap-y-2 text-[12px] uppercase tracking-widest text-muted">
            <span>{experience(job)}</span>
            {job.openings !== null && <span>{job.openings} opening{job.openings === 1 ? "" : "s"}</span>}
            {/* Only shown when the employer chose to publish it — the API
                withholds it otherwise, so an absent figure is not a gap. */}
            {pay !== "" && <span>{pay}</span>}
            {job.company?.industry && <span>{job.company.industry}</span>}
          </div>
        </header>

        {job.skills && job.skills.length > 0 && (
          <div className="mt-8 flex flex-wrap gap-2">
            {job.skills.map((s) => (
              <span key={s} className="mono rounded-full bg-sky px-3 py-1 text-[11px] text-deep">
                {s}
              </span>
            ))}
          </div>
        )}

        <div className="mt-10 whitespace-pre-line text-[15px] leading-relaxed text-ink-2">
          {job.description}
        </div>

        {/* Both ways in, side by side ---------------------------------- */}
        <section className="mt-14 rounded-[22px] border border-line bg-paper p-7 md:p-9">
          <h2 className="display text-2xl text-ink">Two ways to apply</h2>

          <div className="mt-7 grid gap-5 md:grid-cols-2">
            <div className="rounded-[14px] border border-trust/30 bg-white p-6">
              <p className="mono text-[10px] uppercase tracking-widest text-trust">Recommended</p>
              <p className="display mt-2 text-lg text-ink">Apply with a mock interview</p>
              <p className="mt-2 text-sm leading-relaxed text-muted">
                Sit this job&apos;s interview. Your score and transcript go to the employer instead of
                a CV in a pile, and we rebuild your CV against this exact description.
              </p>
              <Link
                href={`/register?next=${encodeURIComponent(`/candidate/jobs/${job.id}`)}`}
                className="mt-5 inline-flex rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white"
              >
                Apply with a mock
              </Link>
            </div>

            <div className="rounded-[14px] border border-line bg-white p-6">
              <p className="mono text-[10px] uppercase tracking-widest text-muted">Also fine</p>
              <p className="display mt-2 text-lg text-ink">Apply without one</p>
              <p className="mt-2 text-sm leading-relaxed text-muted">
                Sign in and apply directly. It costs nothing and never has. You can sit the interview
                later — the employer sees it whenever you do.
              </p>
              <Link
                href={`/student?next=${encodeURIComponent(`/candidate/jobs/${job.id}`)}`}
                className="mt-5 inline-flex rounded-full border border-line px-5 py-2.5 text-sm font-semibold text-ink"
              >
                Sign in and apply
              </Link>
            </div>
          </div>

          <p className="mt-6 text-[12px] leading-relaxed text-muted">
            Applying is free either way. A mock package buys preparation and retakes, never access to
            an employer — and nobody can promise you this job. The employer decides.
          </p>
        </section>
      </article>
    </MarketingShell>
  );
}
