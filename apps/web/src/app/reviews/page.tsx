import type { Metadata } from "next";
import { cmsMetadata } from "@/lib/site-content";
import { MarketingShell } from "@/components/landing/MarketingShell";
import { BookCta } from "@/components/landing/BookCta";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { MonoCounter } from "@/components/motion/MonoCounter";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { ReviewWall } from "@/components/reviews/ReviewWall";
import { reviewAggregates } from "@/content/landing";

export async function generateMetadata(): Promise<Metadata> {
  return cmsMetadata("/reviews", {
    title: "Reviews",
    description:
      "Real stories. Real people. Real success. Reviews from BrowseJobs students on Google and WhatsApp.",
  });
}

export default function ReviewsPage() {
  return (
    <MarketingShell>
      <div className="mx-auto max-w-6xl px-5 py-16 md:py-24">
        <ScrollReveal>
          <p className="kicker text-trust">Reviews</p>
          <h1 className="display mt-3 max-w-3xl text-4xl text-ink md:text-5xl">
            Real stories. Real people. Real success.
          </h1>
          <p className="mt-4 max-w-2xl text-muted">
            From Google and WhatsApp — as students wrote them. And when
            you&apos;re done reading ours, search “BrowseJobs” yourself and read
            the rest.
          </p>
        </ScrollReveal>

        {/* Aggregate band (brochure figures) + mandatory disclaimer */}
        <ScrollReveal delay={0.08}>
          <div className="mt-10 rounded-[22px] bg-ink px-6 py-10 text-white shadow-soft md:px-10">
            <div className="grid grid-cols-2 gap-8 md:grid-cols-4">
              {reviewAggregates.map((s) => (
                <div key={s.label} className="text-center">
                  <div className="display text-3xl md:text-4xl">
                    <MonoCounter
                      value={s.value}
                      suffix={"suffix" in s ? s.suffix : ""}
                      decimals={"decimals" in s ? s.decimals : 0}
                      plain={s.value > 1900 && s.value < 2100}
                    />
                  </div>
                  <p className="mt-2 text-sm text-sky/70">{s.label}</p>
                </div>
              ))}
            </div>
            <div className="mx-auto mt-7 max-w-2xl text-center">
              <Disclaimer className="text-sky/50" />
            </div>
          </div>
        </ScrollReveal>

        <div className="mt-12">
          <ReviewWall />
        </div>

        <ScrollReveal>
          <div className="mt-16 rounded-[22px] bg-trust px-8 py-12 text-center text-white shadow-soft">
            <h2 className="display mx-auto max-w-xl text-2xl md:text-3xl">
              Decide for yourself — the first three steps are free
            </h2>
            <div className="mt-6">
              <BookCta ghost className="border-white bg-white px-8 py-3.5 text-trust" />
            </div>
          </div>
        </ScrollReveal>
      </div>
    </MarketingShell>
  );
}
