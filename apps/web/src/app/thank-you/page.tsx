import type { Metadata } from "next";
import Link from "next/link";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { courseDetails } from "@/content/courses";
import { contact, courses } from "@/content/landing";
import { thankYouCopy } from "@/content/thank-you";

/**
 * /thank-you — the single confirmation page every public lead form redirects to.
 *
 * Takes two query params, both optional and neither personal (spec §Privacy —
 * no name, phone or email ever goes in the URL):
 *   ?type=<lead_type>   which form was submitted, drives the "what happens next"
 *   ?course=<slug>      which program they asked about, drives the course recap
 *
 * Unknown or missing values fall back to the generic contact copy, so a bare
 * /thank-you still renders something sensible.
 */
export const metadata: Metadata = {
  title: "Thank you — we have your request",
  description: "We've received your request. Here's what happens next.",
  // Post-conversion page: useful to the person who just submitted, useless in search.
  robots: { index: false, follow: true },
};

type Search = { type?: string; course?: string };

export default async function ThankYouPage({ searchParams }: { searchParams: Promise<Search> }) {
  const { type, course } = await searchParams;
  const copy = thankYouCopy(type);

  // Full brochure content exists for the live programs only. Waitlist leads are
  // by definition for programs that aren't open yet, so fall back to the card
  // list — otherwise the person who just asked about Agentic AI is told nothing
  // about it. `card` is the always-present name/tagline; `detail` the extras.
  const card = course ? courses.find((c) => c.slug === course) : undefined;
  const detail = course ? courseDetails.find((c) => c.slug === course) : undefined;

  return (
    <MarketingShell>
      <section className="mx-auto max-w-4xl px-5 pb-16 pt-28 md:pt-36">
        {/* Green is correct here: this is a confirmed, free step (spec §2 colour rules). */}
        <div className="grid h-14 w-14 place-items-center rounded-full bg-verify-bg">
          <svg viewBox="0 0 24 24" className="h-7 w-7 text-verify" fill="none" aria-hidden>
            <path
              d="M5 12.5l4.5 4.5L19 7.5"
              stroke="currentColor"
              strokeWidth="2.2"
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </svg>
        </div>

        <p className="kicker mt-6 text-verify">{copy.kicker}</p>
        <h1 className="display mt-3 text-3xl text-ink md:text-5xl">{copy.title}</h1>
        <p className="mt-5 max-w-2xl text-lg leading-relaxed text-muted">{copy.body}</p>

        {/* ---------------------------- the course ---------------------------- */}
        {card && (
          <div className="mt-12 rounded-panel border border-line bg-white p-6 md:p-8">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div>
                <p className="kicker text-trust">What you asked about</p>
                <h2 className="display mt-2 text-2xl text-ink md:text-3xl">{card.name}</h2>
                <p className="mt-1.5 max-w-xl text-[15px] text-muted">{card.tagline}</p>
              </div>
              {!card.live && (
                <span className="mono rounded-full border border-line px-3 py-1 text-[11px] uppercase tracking-[0.14em] text-muted">
                  Opening soon
                </span>
              )}
            </div>

            {detail && (
            <>
            <dl className="mt-6 grid grid-cols-2 gap-4 border-y border-line py-5 md:grid-cols-4">
              {[
                { k: "Duration", v: detail.duration },
                { k: "Format", v: detail.format },
                { k: "Access", v: detail.access },
                { k: "Projects", v: detail.projectsLabel },
              ].map((f) => (
                <div key={f.k}>
                  <dt className="kicker text-muted" style={{ fontSize: "0.62rem" }}>
                    {f.k}
                  </dt>
                  <dd className="mono mt-1.5 text-sm font-semibold text-ink">{f.v}</dd>
                </div>
              ))}
            </dl>

            <div className="mt-6 grid gap-8 md:grid-cols-2">
              <div>
                <p className="text-sm font-semibold text-ink">What you&rsquo;ll be able to do</p>
                <ul className="mt-3 space-y-2.5">
                  {detail.outcomes.slice(0, 4).map((o) => (
                    <li key={o} className="flex items-start gap-2.5 text-sm leading-relaxed text-muted">
                      <svg viewBox="0 0 24 24" fill="none" className="mt-0.5 h-4 w-4 shrink-0 text-trust" aria-hidden>
                        <path
                          d="M5 12.5l4.5 4.5L19 7.5"
                          stroke="currentColor"
                          strokeWidth="2.4"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                        />
                      </svg>
                      {o}
                    </li>
                  ))}
                </ul>
              </div>
              <div>
                <p className="text-sm font-semibold text-ink">Tools you&rsquo;ll work in</p>
                <div className="mt-3 flex flex-wrap gap-1.5">
                  {detail.tools.slice(0, 12).map((t) => (
                    <span
                      key={t}
                      className="mono rounded-full border border-line bg-paper px-2.5 py-1 text-[11px] text-ink2"
                    >
                      {t}
                    </span>
                  ))}
                </div>
              </div>
            </div>

            <Link
              href={`/courses/${detail.slug}`}
              className="mt-7 inline-block rounded-full border border-line px-6 py-2.5 text-sm font-semibold text-ink transition-colors hover:border-trust hover:text-trust"
            >
              See the full syllabus →
            </Link>
            </>
            )}

            {/* Not-yet-open programs have no brochure page to link to. */}
            {!detail && (
              <p className="mt-5 border-t border-line pt-5 text-sm leading-relaxed text-muted">
                The full syllabus for this program is still being built from live interview transcripts, the same way
                every other BrowseJobs syllabus is. We&rsquo;ll send it to you as soon as it&rsquo;s ready.
              </p>
            )}
          </div>
        )}

        {/* --------------------------- what happens next ---------------------- */}
        <div className="mt-12">
          <p className="kicker text-trust">What happens next</p>
          <ol className="mt-6 space-y-5 border-l-2 border-line pl-6">
            {copy.steps.map((s, i) => (
              <li key={s.title} className="relative">
                <span
                  aria-hidden
                  className="mono absolute -left-[35px] grid h-6 w-6 place-items-center rounded-full bg-trust text-[11px] font-semibold text-white"
                >
                  {i + 1}
                </span>
                <p className="text-[15px] font-semibold text-ink">{s.title}</p>
                <p className="mt-1 max-w-2xl text-sm leading-relaxed text-muted">{s.body}</p>
              </li>
            ))}
          </ol>
        </div>

        {/* -------------------------------- reach us -------------------------- */}
        <div className="mt-12 rounded-panel border border-line bg-paper p-6 md:p-8">
          <p className="text-sm font-semibold text-ink">Need us sooner?</p>
          <p className="mt-1.5 text-sm text-muted">
            Call or message — you don&rsquo;t have to wait for us to reach you.
          </p>
          <div className="mt-4 flex flex-wrap gap-x-8 gap-y-2">
            <a href={`tel:${contact.phone.replace(/\s/g, "")}`} className="mono text-sm font-semibold text-trust hover:underline">
              {contact.phone}
            </a>
            <a href={`mailto:${contact.email}`} className="mono text-sm font-semibold text-trust hover:underline">
              {contact.email}
            </a>
            <span className="mono text-sm text-muted">{contact.hours}</span>
          </div>
        </div>

        <div className="mt-10 flex flex-wrap gap-4">
          <Link
            href="/"
            className="rounded-full bg-trust px-7 py-3 text-sm font-semibold text-white transition-colors hover:bg-deep"
          >
            Back to home
          </Link>
          <Link
            href="/courses"
            className="rounded-full border border-line px-7 py-3 text-sm font-semibold text-ink transition-colors hover:border-trust hover:text-trust"
          >
            Browse all programs
          </Link>
        </div>
      </section>
    </MarketingShell>
  );
}
