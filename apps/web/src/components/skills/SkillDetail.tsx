import Link from "next/link";
import { ApLeadButton } from "@/components/ap/pages/ApLeadButton";
import { getSkillPage, type SkillPage } from "@/content/skills";
import "@/components/ap/pages/marketing.css";

/**
 * /skills page body in the Apple-direction design: centred hero with the
 * demand direction and hiring cities, "what interviews ask" tiles, role links
 * into the salary pages, related skills and the masterclass close.
 */
export function SkillDetail({ page }: { page: SkillPage }) {
  return (
    <>
      <section className="s-white pg-hero sk-hero">
        <div className="wrap center">
          <nav aria-label="Breadcrumb" className="pg-crumbs">
            <Link href="/skills">Skills</Link>
            <span aria-hidden>{" › "}</span>
            <span aria-current="page">{page.name}</span>
          </nav>
          <p className="eyebrow">Skill intelligence</p>
          <h1 className="h-hero">
            <span>{page.name}</span>{" "}
            <span className="sk-direction">{page.direction === "rising" ? "demand rising ↑" : "demand steady →"}</span>
          </h1>
          <p className="lead">{page.blurb}</p>
          <ul className="pg-chips is-center" aria-label="Hiring cities">
            {page.cities.map((c) => (
              <li key={c}>
                <span>{c}</span>
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section className="s-paper chapter">
        <div className="wrap">
          <div className="pg-head">
            <p className="eyebrow">From the engine</p>
            <h2 className="h-section">What interviews actually ask</h2>
          </div>
          <ol className="pg-tiles is-2" data-reveal-kids="">
            {page.asked.map((q, i) => (
              <li key={q} className="pg-tile">
                <span className="eyebrow-sm">{String(i + 1).padStart(2, "0")}</span>
                <span className="h-card">{q}</span>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="s-white chapter">
        <div className="wrap">
          <div className="pg-head">
            <p className="eyebrow">Where it pays</p>
            <h2 className="h-section">Roles built on {page.name}</h2>
          </div>
          <ul className="rows">
            {page.roles.map((r) => (
              <li key={r.name}>
                <Link href={r.salarySlug ? `/salaries/${r.salarySlug}` : "/salaries"}>
                  <span>
                    {r.name}
                    <small className="ap-row-note">Salary breakdown</small>
                  </span>
                  <span className="chev" aria-hidden="true">
                    ›
                  </span>
                </Link>
              </li>
            ))}
          </ul>
          {page.track && (
            <p className="lead sk-track">
              {page.name} is taught hands-on in the{" "}
              <Link href={page.track.href} className="ap-link">
                {page.track.name} track
              </Link>{" "}
              — rebuilt monthly from what interviews ask.
            </p>
          )}

          <div className="center sk-related">
            <p className="eyebrow-sm">Usually asked together</p>
            <ul className="pg-chips is-center">
              {page.related.map((slug) => {
                const rel = getSkillPage(slug);
                return rel ? (
                  <li key={slug}>
                    <Link href={`/skills/${slug}`}>
                      {rel.name} {rel.direction === "rising" ? "↑" : "→"}
                    </Link>
                  </li>
                ) : null;
              })}
            </ul>
          </div>
        </div>
      </section>

      <section className="s-black chapter pg-closing">
        <div className="wrap">
          <p className="eyebrow">Free, before any fee</p>
          <h2 className="h-section">Learn {page.name} the way interviews test it</h2>
          <div className="cta-row">
            <ApLeadButton />
          </div>
        </div>
      </section>
    </>
  );
}
