"use client";

import { useState } from "react";
import {
  GOOGLE_LISTING_URL,
  GOOGLE_RATING_LINE,
  googleReviews,
  googleReviewsJsonLd,
  reviewExcerpt,
} from "@/content/reviews";

export function GoogleReviews() {
  const reviews = googleReviews.filter((review) => review.published && review.name.trim() !== "" && review.text.trim() !== "");
  const [open, setOpen] = useState<string | null>(null);
  const jsonLd = googleReviewsJsonLd();
  if (reviews.length === 0 || !jsonLd) return null;

  return (
    <div id="google-reviews" className="mt-14">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <p className="text-center">
        <a href={GOOGLE_LISTING_URL} className="apple-rating-link" target="_blank" rel="noreferrer">
          {GOOGLE_RATING_LINE}
        </a>
      </p>
      <div className="apple-marquee-clip mt-6 text-left">
        <ul className="apple-marquee">
          {[0, 1].flatMap((copy) =>
            reviews.map((review) => {
              const key = `${review.id}-${copy}`;
              const expanded = open === key;
              const { excerpt, truncated } = reviewExcerpt(review.text);
              return (
                <li key={key} className={copy === 1 ? "apple-marquee-copy" : undefined} aria-hidden={copy === 1 ? true : undefined}>
                  <article className="apple-review">
                    <a href={GOOGLE_LISTING_URL} className="apple-review-hit" target="_blank" rel="noreferrer">
                      <span className="sr-only">{`${review.name}, ${review.stars} out of 5, Google review`}</span>
                    </a>
                    <span className="apple-stars" aria-hidden>
                      {"★".repeat(review.stars)}
                      <span className="text-[#d2d2d7]">{"★".repeat(Math.max(0, 5 - review.stars))}</span>
                    </span>
                    <p className={expanded ? "apple-review-text is-open" : "apple-review-text"}>{expanded ? review.text : excerpt}</p>
                    {truncated ? (
                      <button
                        type="button"
                        className="apple-review-more"
                        aria-expanded={expanded}
                        onClick={() => setOpen(expanded ? null : key)}
                      >
                        {expanded ? "Show less" : "Read more"}
                      </button>
                    ) : null}
                    <span className="mt-auto flex items-center justify-between gap-3 pt-3 text-[13px] text-[#6e6e73]">
                      <span>{review.name}</span>
                      <span className="rounded-full border border-black/10 px-2 py-0.5">Google review</span>
                    </span>
                  </article>
                </li>
              );
            }),
          )}
        </ul>
      </div>
    </div>
  );
}
