import type { Metadata } from "next";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { ApLeadButton } from "@/components/ap/pages/ApLeadButton";
import { MonoCounter } from "@/components/motion/MonoCounter";
import { ReviewWall } from "@/components/reviews/ReviewWall";
import { DISCLAIMER, reviewAggregates } from "@/content/landing";
import "@/components/ap/pages/marketing.css";
import { canonical } from "@/lib/seo";

export const metadata: Metadata = {
  title: "Reviews",
  description:
    "Real stories. Real people. Real success. Reviews from BrowseJobs students on Google and WhatsApp.",
  alternates: { canonical: canonical("/reviews") },
};

export default function ReviewsPage() {
  return (
    <MarketingShell>
      <section className="s-white pg-hero">
        <div className="wrap center">
          <p className="eyebrow">Reviews</p>
          <h1 className="h-hero">Real stories. Real people. Real success.</h1>
          <p className="lead">
            From Google and WhatsApp — as students wrote them. And when you&apos;re done reading ours, search
            “BrowseJobs” yourself and read the rest.
          </p>
        </div>
      </section>

      {/* Aggregate band (brochure figures) + mandatory disclaimer */}
      <section className="s-paper chapter reviews-stats">
        <div className="wrap center">
          <ul className="pg-stats" data-reveal-kids="">
            {reviewAggregates.map((s) => (
              <li key={s.label}>
                <b>
                  <MonoCounter
                    value={s.value}
                    suffix={"suffix" in s ? s.suffix : ""}
                    decimals={"decimals" in s ? s.decimals : 0}
                    plain={s.value > 1900 && s.value < 2100}
                  />
                </b>
                <span>{s.label}</span>
              </li>
            ))}
          </ul>
          <p className="fine">{DISCLAIMER}</p>
        </div>
      </section>

      <section className="s-white chapter reviews-wall">
        <div className="wrap">
          <ReviewWall />
        </div>
      </section>

      <section className="s-black chapter pg-closing">
        <div className="wrap">
          <h2 className="h-section">Decide for yourself — the first three steps are free</h2>
          <div className="cta-row">
            <ApLeadButton />
          </div>
        </div>
      </section>
    </MarketingShell>
  );
}
