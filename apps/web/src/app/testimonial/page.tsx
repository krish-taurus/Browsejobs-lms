"use client";

import { Suspense, useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { ApiError, apiJson } from "@/lib/api";

/**
 * Candidate review page (Platform Spec §3 review gate). The candidate rates and
 * writes their review; a rating below the public threshold is kept as private
 * feedback ("thank you, we'll look into it") and never published. A 4★+ rating
 * opens the hand-off: follow us on Instagram + YouTube and post the review on
 * Google, self-attest each, and once our team confirms, the voucher is issued
 * and the review joins the public wall. Reached via a one-tap magic link.
 */

type ReviewLinks = {
  min_public_rating: number;
  google_review_url: string;
  instagram_url: string;
  youtube_url: string;
};

function Stars({ value, onChange }: { value: number; onChange: (n: number) => void }) {
  return (
    <div className="flex gap-1" role="radiogroup" aria-label="Rating">
      {[1, 2, 3, 4, 5].map((n) => (
        <button
          key={n}
          type="button"
          role="radio"
          aria-checked={value === n}
          aria-label={`${n} star${n > 1 ? "s" : ""}`}
          onClick={() => onChange(n)}
          className={`text-3xl leading-none transition-transform hover:scale-110 ${
            n <= value ? "text-amber" : "text-line"
          }`}
        >
          ★
        </button>
      ))}
    </div>
  );
}

/** A social action: open the link, then tick that it's done (self-attested). */
function SocialAction({
  label,
  hint,
  href,
  checked,
  onChange,
}: {
  label: string;
  hint: string;
  href: string | null;
  checked: boolean;
  onChange: (v: boolean) => void;
}) {
  return (
    <div className="rounded-[12px] border border-line bg-paper p-3">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-semibold text-ink">{label}</p>
          <p className="text-xs text-muted">{hint}</p>
        </div>
        {href ? (
          <a
            href={href}
            target="_blank"
            rel="noreferrer"
            className="shrink-0 rounded-full border border-trust/40 bg-trust/10 px-3.5 py-1.5 text-xs font-semibold text-trust hover:bg-trust/15"
          >
            Open →
          </a>
        ) : (
          <span className="shrink-0 text-xs text-muted">Link not set</span>
        )}
      </div>
      <label className="mt-2.5 flex items-center gap-2 text-sm text-ink">
        <input
          type="checkbox"
          checked={checked}
          onChange={(e) => onChange(e.target.checked)}
          className="h-4 w-4 rounded border-line text-trust"
        />
        <span>Done</span>
      </label>
    </div>
  );
}

function ReviewForm() {
  const params = useSearchParams();
  const batch = params.get("batch");
  const stage = params.get("stage");

  const [links, setLinks] = useState<ReviewLinks | null>(null);
  const [rating, setRating] = useState(0);
  const [body, setBody] = useState("");
  const [step, setStep] = useState<"write" | "share">("write");

  const [consent, setConsent] = useState(false);
  const [ig, setIg] = useState(false);
  const [yt, setYt] = useState(false);
  const [google, setGoogle] = useState(false);

  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState<null | "public" | "private">(null);

  useEffect(() => {
    apiJson<{ data: ReviewLinks }>("/api/v1/review-links")
      .then((r) => setLinks(r.data))
      .catch(() => setLinks({ min_public_rating: 4, google_review_url: "", instagram_url: "", youtube_url: "" }));
  }, []);

  const threshold = links?.min_public_rating ?? 4;
  const isPublic = rating >= threshold;

  async function post() {
    setError(null);
    setSubmitting(true);
    try {
      const form = new FormData();
      form.set("rating", String(rating));
      form.set("body", body);
      form.set("consent_publish", isPublic && consent ? "1" : "0");
      form.set("followed_instagram", isPublic && ig ? "1" : "0");
      form.set("subscribed_youtube", isPublic && yt ? "1" : "0");
      form.set("posted_google_review", isPublic && google ? "1" : "0");
      if (stage) form.set("stage", stage);
      if (batch) form.set("batch_id", batch);
      await apiJson("/api/v1/me/testimonials", { method: "POST", body: form });
      setDone(isPublic ? "public" : "private");
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setSubmitting(false);
    }
  }

  function onContinue() {
    if (rating === 0 || body.trim().length < 10) return;
    if (isPublic) {
      setStep("share"); // reveal the Google + social hand-off
    } else {
      void post(); // low rating → straight to private feedback
    }
  }

  if (done === "private") {
    return (
      <div className="rounded-[22px] border border-line bg-white p-8 text-center">
        <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-sky text-trust">✓</div>
        <h1 className="display mt-4 text-2xl text-ink">Thank you for your feedback</h1>
        <p className="mt-2 text-sm text-muted">
          We&apos;ll look into this as soon as possible. A member of our team may reach out to make it right.
        </p>
      </div>
    );
  }

  if (done === "public") {
    return (
      <div className="rounded-[22px] border border-line bg-white p-8 text-center">
        <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-verify-bg text-verify">★</div>
        <h1 className="display mt-4 text-2xl text-ink">Thank you — that means a lot</h1>
        <p className="mt-2 text-sm text-muted">
          Once our team confirms your review, it goes live on our wall and your thank-you voucher is on its way.
        </p>
      </div>
    );
  }

  // Step 2 — the 4★+ hand-off.
  if (step === "share") {
    const canSubmit = consent && !submitting;
    return (
      <div className="rounded-[22px] border border-line bg-white p-8">
        <p className="kicker text-verify">One last step</p>
        <h1 className="display mt-2 text-2xl text-ink">Make it count</h1>
        <p className="mt-2 text-sm text-muted">
          Post your review on Google and follow us — tick each once you&apos;re done. We confirm, then send your
          voucher and add your review to our wall.
        </p>

        <div className="mt-6 space-y-3">
          <SocialAction
            label="Post your review on Google"
            hint="Paste the same words — it helps the next batch find us."
            href={links?.google_review_url || null}
            checked={google}
            onChange={setGoogle}
          />
          <SocialAction
            label="Follow us on Instagram"
            hint="Stay in the loop on batches and wins."
            href={links?.instagram_url || null}
            checked={ig}
            onChange={setIg}
          />
          <SocialAction
            label="Subscribe on YouTube"
            hint="Free lessons and interview prep."
            href={links?.youtube_url || null}
            checked={yt}
            onChange={setYt}
          />
        </div>

        <label className="mt-5 flex items-start gap-2.5 text-sm text-ink">
          <input
            type="checkbox"
            checked={consent}
            onChange={(e) => setConsent(e.target.checked)}
            className="mt-0.5 h-4 w-4 rounded border-line text-trust"
          />
          <span>I consent to BrowseJobs publishing my review on their website.</span>
        </label>

        {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

        <div className="mt-6 flex gap-3">
          <button
            type="button"
            onClick={() => setStep("write")}
            className="rounded-full border border-line px-5 py-2.5 text-sm font-semibold text-ink hover:bg-paper"
          >
            Back
          </button>
          <button
            type="button"
            onClick={() => void post()}
            disabled={!canSubmit}
            className="flex-1 rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
          >
            {submitting ? "Submitting…" : "Submit review"}
          </button>
        </div>
      </div>
    );
  }

  // Step 1 — rate + write.
  const canContinue = rating > 0 && body.trim().length >= 10;
  return (
    <div className="rounded-[22px] border border-line bg-white p-8">
      <p className="kicker text-trust">Your story</p>
      <h1 className="display mt-2 text-2xl text-ink">How was your experience?</h1>
      <p className="mt-2 text-sm text-muted">
        Built from real interviews — tell us honestly how it went. Your words help the next batch decide.
      </p>

      <div className="mt-6">
        <label className="mb-2 block text-sm font-medium text-ink">Your rating</label>
        <Stars value={rating} onChange={setRating} />
      </div>

      <div className="mt-5">
        <label className="mb-2 block text-sm font-medium text-ink">Your review</label>
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          rows={5}
          maxLength={2000}
          placeholder="What changed for you? Be specific and honest."
          className="w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
        />
      </div>

      {error && <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>}

      <button
        type="button"
        onClick={onContinue}
        disabled={!canContinue || submitting}
        className="mt-6 w-full rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
      >
        {submitting ? "Submitting…" : "Continue"}
      </button>
    </div>
  );
}

export default function TestimonialPage() {
  return (
    <main className="mx-auto grid min-h-screen max-w-lg place-items-center px-5 py-12">
      <div className="w-full">
        <Suspense fallback={<div className="shimmer h-96 rounded-[22px]" />}>
          <ReviewForm />
        </Suspense>
      </div>
    </main>
  );
}
