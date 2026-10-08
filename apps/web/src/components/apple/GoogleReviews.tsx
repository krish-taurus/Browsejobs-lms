"use client";

import { useState } from "react";
import { CountUp } from "@/components/argus/CountUp";
import {
  GOOGLE_LISTING_URL,
  GOOGLE_RATING_LINE,
  googleReviews,
  googleReviewsJsonLd,
  reviewExcerpt,
  type GoogleReview,
} from "@/content/reviews";

function Stars({ count }: { count: number }) {
  return (
    <span className="apple-stars" aria-hidden>
      {Array.from({ length: 5 }, (_, index) => (
        <span key={index} className={index < count ? "is-on" : "is-off"}>
          {index < count ? "★" : "☆"}
        </span>
      ))}
    </span>
  );
}

function ReviewCard({ review, expanded, onToggle }: { review: GoogleReview; expanded: boolean; onToggle: () => void }) {
  const { excerpt, truncated } = reviewExcerpt(review.text);
  return (
    <article className="apple-review">
      <a href={GOOGLE_LISTING_URL} className="apple-review-hit" target="_blank" rel="noreferrer">
        <span className="sr-only">{`${review.name}, ${review.stars} out of 5, Google review`}</span>
      </a>
      <Stars count={review.stars} />
      <p className={expanded ? "apple-review-text is-open" : "apple-review-text"}>{expanded ? review.text : excerpt}</p>
      {truncated ? (
        <button type="button" className="apple-review-more" aria-expanded={expanded} onClick={onToggle}>
          {expanded ? "Show less" : "Read more"}
        </button>
      ) : null}
      <span className="mt-auto flex items-center justify-between gap-3 pt-3 text-[13px] text-[#6e6e73]">
        <span>{review.name}</span>
        <span className="rounded-full border border-black/10 px-2 py-0.5">Google review</span>
      </span>
    </article>
  );
}

export function GoogleReviews({ tone = "apple" }: { tone?: "apple" | "argus" }) {
  const reviews = googleReviews.filter((review) => review.published && review.name.trim() !== "" && review.text.trim() !== "");
  const [open, setOpen] = useState<string | null>(null);
  const jsonLd = googleReviewsJsonLd();
  if (reviews.length === 0 || !jsonLd) return null;

  return (
    <div id="google-reviews" className="mt-14">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <p className="text-center">
        <a href={GOOGLE_LISTING_URL} className={tone === "argus" ? "argus-rating-link" : "apple-rating-link"} target="_blank" rel="noreferrer">
          {tone === "argus" ? (
            <>
              <span className="argus-rating-num" aria-hidden>
                <CountUp to={4.9} decimals={1} />
              </span>
              <span className="sr-only">{GOOGLE_RATING_LINE}</span>
              <span aria-hidden> on Google · 473 reviews</span>
            </>
          ) : (
            GOOGLE_RATING_LINE
          )}
        </a>
      </p>
      {tone === "argus" ? (
        <div className="argus-review-rows">
          {[0, 1].map((row) => {
            const lane = reviews.filter((_, index) => index % 2 === row);
            return (
              <div key={row} className="apple-marquee-clip argus-review-clip">
                {[0, 1].map((copy) => (
                  <ul key={copy} className={copy === 0 ? "argus-marquee" : "argus-marquee is-clone"} aria-hidden={copy === 1 ? true : undefined}>
                    {lane.map((review) => {
                      const key = `${review.id}-${row}-${copy}`;
                      const expanded = open === key;
                      return (
                        <li key={key}>
                          <ReviewCard review={review} expanded={expanded} onToggle={() => setOpen(expanded ? null : key)} />
                        </li>
                      );
                    })}
                  </ul>
                ))}
              </div>
            );
          })}
        </div>
      ) : (
      <div className="apple-marquee-clip mt-6 text-left">
        <ul className="apple-marquee">
          {[0, 1].flatMap((copy) =>
            reviews.map((review) => {
              const key = `${review.id}-${copy}`;
              const expanded = open === key;
              return (
                <li key={key} className={copy === 1 ? "apple-marquee-copy" : undefined} aria-hidden={copy === 1 ? true : undefined}>
                  <ReviewCard review={review} expanded={expanded} onToggle={() => setOpen(expanded ? null : key)} />
                </li>
              );
            }),
          )}
        </ul>
      </div>
      )}
    </div>
  );
}
