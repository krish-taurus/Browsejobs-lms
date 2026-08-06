import Link from "next/link";

const POINTS = [
  { k: "Design once", v: "Your skills, your rubric, your rounds" },
  { k: "Applicants arrive graded", v: "Scored against what you asked for" },
  { k: "Credentials checked", v: "DigiLocker identity and education, EPFO employment" },
];

/**
 * The homepage's one employer moment. The landing page is written for
 * candidates, so this band does not compete for the page's primary CTA — it
 * states who the other side is for and links out.
 */
export function EmployersHomeBand() {
  return (
    <section className="px-5 py-16 md:py-24">
      <div className="mx-auto max-w-6xl overflow-hidden rounded-[22px] bg-deck p-8 md:p-14">
        <div className="grid gap-10 lg:grid-cols-[1.1fr_1fr] lg:items-center">
          <div>
            <p className="kicker text-employer">Hiring, not learning?</p>
            <h2 className="display mt-3 text-3xl text-white md:text-5xl">
              Employers get the{" "}
              <span className="bg-gradient-to-r from-trust to-employer bg-clip-text text-transparent">
                other side of this engine
              </span>
            </h2>
            <p className="mt-5 max-w-xl text-[15px] leading-relaxed text-white/55">
              The same interviews that shape the syllabus run the hiring workspace. You design the
              assessment once, and every applicant reaches you already scored against it — with
              the answers, the rubric and the verification outcomes attached.
            </p>
            <Link
              href="/for-employers"
              className="group mt-8 inline-flex items-center gap-2.5 rounded-full bg-trust px-6 py-3 font-semibold text-white transition-colors hover:bg-deep"
            >
              See how hiring works
              <span
                aria-hidden
                className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/15 text-sm transition-transform duration-300 group-hover:translate-x-0.5"
              >
                →
              </span>
            </Link>
          </div>

          <ul className="space-y-3">
            {POINTS.map((p) => (
              <li
                key={p.k}
                className="rounded-2xl border border-white/[0.08] bg-white/[0.03] px-5 py-4"
              >
                <p className="mono text-[10px] uppercase tracking-[0.14em] text-white/40">{p.k}</p>
                <p className="mt-1.5 text-[14px] text-white/75">{p.v}</p>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </section>
  );
}
