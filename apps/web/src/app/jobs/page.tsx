import Link from "next/link";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { pageMetadata } from "@/lib/seo";

export const metadata = pageMetadata({
  title: "Fresh IT jobs — updated every morning",
  description:
    "Live openings from the last 7 days across the roles we train for. Sign in to see your match score, likely interview questions, and a CV rebuilt for the exact JD.",
  path: "/jobs",
});

// The board refreshes with the morning feed sync — re-render at most every 30 min.
export const revalidate = 1800;

type PublicJob = {
  id: number;
  title: string;
  company: string;
  location: string | null;
  work_mode: string | null;
  role_title: string | null;
  seniority: string | null;
  skills: string[];
  summary: string;
  posted_at: string | null;
  teaser_questions: string[];
  question_count: number;
  real_question_count: number;
};

type Offer = { sku: string; name: string; price_paise: number };

async function fetchJobs(): Promise<{ jobs: PublicJob[]; windowDays: number; offers: Offer[] }> {
  const base = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";
  try {
    const res = await fetch(`${base}/api/v1/jobs`, { next: { revalidate: 1800 } });
    if (!res.ok) return { jobs: [], windowDays: 7, offers: [] };
    const body = (await res.json()) as { data: { jobs: PublicJob[]; window_days: number; kit_offers: Offer[] } };
    return { jobs: body.data.jobs, windowDays: body.data.window_days, offers: body.data.kit_offers ?? [] };
  } catch {
    return { jobs: [], windowDays: 7, offers: [] };
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
  const { jobs, windowDays, offers } = await fetchJobs();
  const kit = offers.find((o) => o.sku === "job-kit");
  const kitMentor = offers.find((o) => o.sku === "job-kit-mentor");

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "ItemList",
    itemListElement: jobs.slice(0, 25).map((j, i) => ({
      "@type": "ListItem",
      position: i + 1,
      item: {
        "@type": "JobPosting",
        title: j.title,
        datePosted: j.posted_at ?? undefined,
        hiringOrganization: { "@type": "Organization", name: j.company },
        jobLocation: j.location
          ? { "@type": "Place", address: { "@type": "PostalAddress", addressLocality: j.location, addressCountry: "IN" } }
          : undefined,
        description: j.summary,
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
            Fresh openings. Every morning.
          </h1>
          <p className="mt-4 max-w-2xl text-muted">
            Every role here was posted in the last{" "}
            <span className="mono text-ink">{windowDays} days</span>. Create a free account to see
            your match score for each one, the questions that interview is likely to ask, and a CV
            rebuilt for the exact JD. We never auto-apply on your behalf — you stay in control.
          </p>
          {kit && (
            <p className="mt-3 max-w-2xl text-sm text-muted">
              Every job has an <span className="font-semibold text-ink">Interview Kit</span> — the full
              likely-questions paper, unlimited AI mocks on that exact JD, and your JD-rebuilt CV — for{" "}
              <span className="mono text-ink">₹{Math.round(kit.price_paise / 100)}</span>
              {kitMentor && (
                <>
                  {" "}· or <span className="mono text-ink">₹{Math.round(kitMentor.price_paise / 100)}</span> with a
                  live 1:1 mentor to prep you for that interview
                </>
              )}
              . Free while you&apos;re enrolled in any BrowseJobs program.
            </p>
          )}
          <div className="mt-6 flex flex-wrap gap-3">
            <Link
              href="/register"
              className="rounded-full bg-trust px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-deep"
            >
              Check my match — free
            </Link>
            <Link
              href="/#free-steps"
              className="rounded-full border border-line bg-white px-6 py-3 text-sm font-semibold text-ink transition-colors hover:border-trust"
            >
              Book free masterclass
            </Link>
          </div>
        </ScrollReveal>

        {jobs.length === 0 ? (
          <ScrollReveal delay={0.08}>
            <div className="mt-12 rounded-[22px] border border-line bg-white p-10 text-center">
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
          <div className="mt-12 grid gap-4 md:grid-cols-2">
            {jobs.map((job, i) => (
              <ScrollReveal key={job.id} delay={Math.min(i, 6) * 0.04}>
                <div className="flex h-full flex-col rounded-[14px] border border-line bg-white p-5 shadow-soft">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="font-semibold text-ink">{job.title}</p>
                      <p className="mono mt-0.5 text-[11px] uppercase tracking-widest text-muted">
                        {[job.company, job.location, job.work_mode].filter(Boolean).join(" · ")}
                      </p>
                    </div>
                    <span className="mono shrink-0 rounded-full bg-sky px-2.5 py-1 text-[10px] uppercase tracking-widest text-deep">
                      {postedLabel(job.posted_at)}
                    </span>
                  </div>

                  {job.skills.length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-1.5">
                      {job.skills.map((s) => (
                        <span key={s} className="mono rounded-full bg-paper px-2 py-0.5 text-[10px] text-ink-2">
                          {s}
                        </span>
                      ))}
                    </div>
                  )}

                  {job.teaser_questions.length > 0 && (
                    <div className="mt-3 rounded-[10px] bg-paper p-3">
                      <p className="mono text-[10px] uppercase tracking-widest text-muted">
                        This interview will likely ask
                        {job.real_question_count > 0 && ` · ${job.real_question_count} from real interviews`}
                      </p>
                      <ul className="mt-1.5 space-y-1">
                        {job.teaser_questions.map((q) => (
                          <li key={q} className="text-xs text-ink-2">
                            · {q}
                          </li>
                        ))}
                      </ul>
                      <p className="mt-1.5 text-[11px] text-muted">
                        {job.question_count > 3
                          ? `${job.question_count - 3} more in the full paper — sign in to unlock it, plus a mock on this exact JD.`
                          : "Sign in for the full paper — and a mock interview on this exact JD."}
                      </p>
                    </div>
                  )}

                  <div className="mt-auto pt-4">
                    <Link
                      href="/register"
                      className="inline-block rounded-full border border-line bg-white px-4 py-2 text-xs font-semibold text-trust transition-colors hover:border-trust"
                    >
                      See my match & prepare →
                    </Link>
                  </div>
                </div>
              </ScrollReveal>
            ))}
          </div>
        )}

        <ScrollReveal delay={0.1}>
          <p className="mt-10 max-w-2xl text-xs text-muted">
            Openings are aggregated from public postings and refreshed daily; listings belong to the
            hiring companies. We prepare you for them — every promise in writing, and nobody can
            guarantee employment: the market decides.
          </p>
        </ScrollReveal>
      </div>
    </MarketingShell>
  );
}
