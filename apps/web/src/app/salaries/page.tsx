import type { Metadata } from "next";
import Link from "next/link";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { DISCLAIMER } from "@/content/landing";
import "@/components/ap/pages/marketing.css";
import { salaryPages } from "@/content/salaries";
import { canonical } from "@/lib/seo";

export const metadata: Metadata = {
  title: "Tech Salaries in India — Role & City Benchmarks",
  description:
    "What Data Engineers, Analysts and DevOps Engineers actually earn across Bengaluru, Hyderabad, Pune, Mumbai and NCR — percentile benchmarks from BrowseJobs placement data.",
  alternates: { canonical: canonical("/salaries") },
};

/** /salaries index — every deep-dive page, grouped by role. */
export default function SalariesIndex() {
  const roles = [...new Set(salaryPages.map((p) => p.role))];

  return (
    <MarketingShell>
      <section className="s-white pg-hero">
        <div className="wrap center">
          <p className="eyebrow">Salary intelligence</p>
          <h1 className="h-hero">What tech actually pays, city by city</h1>
          <p className="lead">
            Percentile benchmarks per role, city and experience band — the same numbers our counsellors use when a
            student weighs an offer.
          </p>
        </div>
      </section>

      <section className="s-paper chapter">
        <div className="wrap">
          {roles.map((role) => (
            <div key={role} data-reveal="">
              <h2 className="pg-sub">{role}</h2>
              <ul className="pg-tiles">
                {salaryPages
                  .filter((p) => p.role === role)
                  .map((p) => {
                    const b = p.bands[p.bands.length - 1];
                    return (
                      <li key={p.slug}>
                        <Link href={`/salaries/${p.slug}`} className="pg-tile">
                          <span className="eyebrow-sm">{p.city}</span>
                          <span className="pg-big">₹{b.p50} LPA</span>
                          <span className="more">
                            Full breakdown <span className="chev" aria-hidden="true">›</span>
                          </span>
                        </Link>
                      </li>
                    );
                  })}
              </ul>
            </div>
          ))}
          <p className="fine pg-index-fine">{DISCLAIMER}</p>
        </div>
      </section>
    </MarketingShell>
  );
}
