import type { Metadata } from "next";
import Link from "next/link";
import { MarketingShell } from "@/components/landing/MarketingShell";
import "@/components/ap/pages/marketing.css";
import { skillPages } from "@/content/skills";
import { canonical } from "@/lib/seo";

export const metadata: Metadata = {
  title: "Tech Skills in Demand in India — What Interviews Ask",
  description:
    "Which skills Indian tech interviews test right now — demand direction, real interview questions, hiring cities and the salaries behind SQL, Python, Spark, Kubernetes and more.",
  alternates: { canonical: canonical("/skills") },
};

/** /skills index — the engine's tracked skills as a browsable set. */
export default function SkillsIndex() {
  return (
    <MarketingShell>
      <section className="s-white pg-hero">
        <div className="wrap center">
          <p className="eyebrow">Skill intelligence</p>
          <h1 className="h-hero">The skills interviews test right now</h1>
          <p className="lead">
            Tracked by the engine across ~50 interviews a day — demand direction, the questions companies actually ask,
            and what the roles pay.
          </p>
        </div>
      </section>

      <section className="s-paper chapter">
        <div className="wrap">
          <ul className="pg-tiles" data-reveal-kids="">
            {skillPages.map((s) => (
              <li key={s.slug}>
                <Link href={`/skills/${s.slug}`} className="pg-tile">
                  <span className="pg-tile-row">
                    <span className="h-card">{s.name}</span>
                    <span className={`pg-tag${s.direction === "rising" ? " is-rising" : ""}`}>
                      {s.direction === "rising" ? "rising ↑" : "steady →"}
                    </span>
                  </span>
                  <span className="body">{s.blurb}</span>
                  <span className="more">
                    Deep dive <span className="chev" aria-hidden="true">›</span>
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </section>
    </MarketingShell>
  );
}
