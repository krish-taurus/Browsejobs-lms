"use client";

import { useCallback, useEffect, useState } from "react";
import { motion, useReducedMotion } from "framer-motion";
import { apiJson } from "@/lib/api";
import { durations, ease } from "@/lib/motion";

type Review = {
  id: number;
  author_name: string;
  rating: number;
  body: string;
  source: "google" | "whatsapp" | "platform";
  course_slug: string | null;
  reviewed_on: string | null;
};

type ReviewsResponse = {
  data: Review[];
  meta: { current_page: number; last_page: number; total: number; average_rating: number };
};

const FILTERS = [
  { key: "", label: "All reviews" },
  { key: "google", label: "Google" },
  { key: "whatsapp", label: "WhatsApp" },
  { key: "platform", label: "Testimonials" },
] as const;

/** Amber is reserved for review stars (spec §2.1) — this is that use. */
function Stars({ rating }: { rating: number }) {
  return (
    <span className="text-amber" aria-label={`${rating} out of 5 stars`}>
      {"★".repeat(rating)}
      <span className="text-line">{"★".repeat(5 - rating)}</span>
    </span>
  );
}

function SourceBadge({ source }: { source: Review["source"] }) {
  const styles: Record<Review["source"], { label: string; cls: string }> = {
    google: { label: "Google", cls: "bg-sky text-deep" },
    whatsapp: { label: "WhatsApp", cls: "bg-verify-bg text-verify" },
    platform: { label: "Verified testimonial", cls: "bg-paper text-muted border border-line" },
  };
  const s = styles[source];
  return (
    <span className={`mono rounded-full px-2.5 py-1 text-[10px] uppercase tracking-widest ${s.cls}`}>
      {s.label}
    </span>
  );
}

function ReviewCard({ review, index }: { review: Review; index: number }) {
  const reduce = useReducedMotion();
  const isWhatsApp = review.source === "whatsapp";

  return (
    <motion.article
      initial={reduce ? false : { opacity: 0, y: 18 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: durations.slow, ease, delay: (index % 12) * 0.04 }}
      className={`mb-5 break-inside-avoid rounded-[14px] border p-6 ${
        isWhatsApp
          ? "rounded-tl-sm border-verify/25 bg-verify-bg/60"
          : "border-line bg-white"
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <span className="display grid h-10 w-10 shrink-0 place-items-center rounded-full bg-ink text-sm text-white">
            {review.author_name.charAt(0).toUpperCase()}
          </span>
          <div>
            <p className="font-semibold text-ink">{review.author_name}</p>
            <Stars rating={review.rating} />
          </div>
        </div>
        <SourceBadge source={review.source} />
      </div>
      <p className="mt-4 text-sm leading-relaxed text-ink2/90">
        “{review.body}”
      </p>
      {review.reviewed_on && (
        <p className="mono mt-3 text-[11px] text-muted">{review.reviewed_on}</p>
      )}
    </motion.article>
  );
}

export function ReviewWall() {
  const [source, setSource] = useState<string>("");
  const [reviews, setReviews] = useState<Review[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async (nextPage: number, nextSource: string, replace: boolean) => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page: String(nextPage), per_page: "12" });
      if (nextSource) params.set("source", nextSource);
      const res = await apiJson<ReviewsResponse>(`/api/v1/reviews?${params}`);
      setReviews((prev) => (replace ? res.data : [...prev, ...res.data]));
      setPage(res.meta.current_page);
      setLastPage(res.meta.last_page);
      setTotal(res.meta.total);
    } catch {
      // Leave the wall as-is; the page header still communicates.
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load(1, source, true);
  }, [source, load]);

  return (
    <div>
      <div className="flex flex-wrap items-center gap-2">
        {FILTERS.map((f) => (
          <button
            key={f.key}
            onClick={() => setSource(f.key)}
            className={`rounded-full px-4 py-2 text-sm font-semibold transition-colors ${
              source === f.key
                ? "bg-ink text-white"
                : "border border-line bg-white text-muted hover:text-ink"
            }`}
          >
            {f.label}
          </button>
        ))}
        {total !== null && (
          <span className="mono ml-auto text-xs text-muted">
            {total.toLocaleString("en-IN")} shown
          </span>
        )}
      </div>

      {reviews.length === 0 && !loading ? (
        <div className="mt-10 rounded-[14px] border border-dashed border-line bg-white p-10 text-center text-muted">
          Reviews for this filter are being loaded onto the platform.
        </div>
      ) : (
        <div className="mt-8 columns-1 gap-5 sm:columns-2 lg:columns-3">
          {reviews.map((r, i) => (
            <ReviewCard key={r.id} review={r} index={i} />
          ))}
        </div>
      )}

      {loading && (
        <div className="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <div key={i} className="shimmer h-36 rounded-[14px]" />
          ))}
        </div>
      )}

      {!loading && page < lastPage && (
        <div className="mt-8 text-center">
          <button
            onClick={() => void load(page + 1, source, false)}
            className="rounded-full border border-line bg-white px-7 py-3 font-semibold text-ink transition-colors hover:border-trust"
          >
            Load more reviews
          </button>
        </div>
      )}
    </div>
  );
}
