import type { Metadata } from "next";
import Link from "next/link";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { Kicker } from "@/components/brand/Kicker";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { salaryPages } from "@/content/salaries";

export const metadata: Metadata = {
  title: "Tech Salaries in India — Role & City Benchmarks",
  description:
    "What Data Engineers, Analysts and DevOps Engineers actually earn across Bengaluru, Hyderabad, Pune, Mumbai and NCR — percentile benchmarks from BrowseJobs placement data.",
};

/** /salaries index — every deep-dive page, grouped by role. */
export default function SalariesIndex() {
  const roles = [...new Set(salaryPages.map((p) => p.role))];

  return (
    <MarketingShell>
      <section className="mx-auto max-w-6xl px-5 py-16 md:py-24">
        <ScrollReveal>
          <Kicker>Salary intelligence</Kicker>
          <h1 className="display mt-3 max-w-3xl text-3xl text-ink md:text-6xl">
            What tech actually pays, city by city
          </h1>
          <p className="mt-4 max-w-2xl text-lg text-ink2/70">
            Percentile benchmarks per role, city and experience band — the same
            numbers our counsellors use when a student weighs an offer.
          </p>
        </ScrollReveal>

        {roles.map((role, ri) => (
          <ScrollReveal key={role} delay={ri * 0.05}>
            <h2 className="display mt-12 text-xl text-ink md:text-2xl">{role}</h2>
            <div className="mt-4 grid gap-4 md:grid-cols-3">
              {salaryPages
                .filter((p) => p.role === role)
                .map((p) => {
                  const b = p.bands[p.bands.length - 1];
                  return (
                    <Link
                      key={p.slug}
                      href={`/salaries/${p.slug}`}
                      className="group rounded-[14px] border border-line bg-white p-5 shadow-soft transition-all duration-300 hover:-translate-y-1 hover:border-trust/40"
                    >
                      <span className="mono text-[10px] uppercase tracking-[0.18em] text-muted">{p.city}</span>
                      <p className="mono mt-1.5 text-2xl font-semibold text-ink">₹{b.p50} LPA</p>
                      <span className="mt-2 flex items-center gap-1.5 text-sm font-semibold text-trust">
                        Full breakdown
                        <span className="transition-transform duration-300 group-hover:translate-x-1">→</span>
                      </span>
                    </Link>
                  );
                })}
            </div>
          </ScrollReveal>
        ))}

        <Disclaimer className="mt-10" />
      </section>
    </MarketingShell>
  );
}
