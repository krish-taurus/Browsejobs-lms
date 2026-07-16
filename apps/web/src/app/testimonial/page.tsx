"use client";

import { Suspense, useState } from "react";
import { useSearchParams } from "next/navigation";
import { ApiError, apiJson } from "@/lib/api";

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

function TestimonialForm() {
  const params = useSearchParams();
  const batch = params.get("batch");

  const [rating, setRating] = useState(0);
  const [body, setBody] = useState("");
  const [video, setVideo] = useState<File | null>(null);
  const [consent, setConsent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const form = new FormData();
      form.set("rating", String(rating));
      form.set("body", body);
      form.set("consent_publish", consent ? "1" : "0");
      if (batch) form.set("batch_id", batch);
      if (video) form.set("video", video);
      await apiJson("/api/v1/me/testimonials", { method: "POST", body: form });
      setDone(true);
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Something went wrong.");
    } finally {
      setSubmitting(false);
    }
  }

  if (done) {
    return (
      <div className="rounded-[22px] border border-line bg-white p-8 text-center">
        <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-verify-bg text-verify">✓</div>
        <h1 className="display mt-4 text-2xl text-ink">Thank you</h1>
        <p className="mt-2 text-sm text-muted">
          Your testimonial is with our team for review. Once approved, your voucher is pre-applied to your
          registration link.
        </p>
      </div>
    );
  }

  const canSubmit = rating > 0 && body.trim().length >= 10 && consent && !submitting;

  return (
    <form onSubmit={submit} className="rounded-[22px] border border-line bg-white p-8">
      <p className="kicker text-trust">Your story</p>
      <h1 className="display mt-2 text-2xl text-ink">Share a testimonial</h1>
      <p className="mt-2 text-sm text-muted">
        Built from real interviews — tell us how the bootcamp went. Honest words help the next batch decide.
      </p>

      <div className="mt-6">
        <label className="mb-2 block text-sm font-medium text-ink">Your rating</label>
        <Stars value={rating} onChange={setRating} />
      </div>

      <div className="mt-5">
        <label className="mb-2 block text-sm font-medium text-ink">Your testimonial</label>
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          rows={5}
          maxLength={2000}
          placeholder="What changed for you? Be specific and honest."
          className="w-full rounded-[10px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
        />
      </div>

      <div className="mt-5">
        <label className="mb-2 block text-sm font-medium text-ink">Video (optional)</label>
        <input
          type="file"
          accept="video/mp4,video/quicktime,video/webm"
          onChange={(e) => setVideo(e.target.files?.[0] ?? null)}
          className="block w-full text-sm text-muted file:mr-3 file:rounded-full file:border file:border-line file:bg-paper file:px-4 file:py-1.5 file:text-sm file:text-ink"
        />
      </div>

      <label className="mt-5 flex items-start gap-2.5 text-sm text-ink">
        <input
          type="checkbox"
          checked={consent}
          onChange={(e) => setConsent(e.target.checked)}
          className="mt-0.5 h-4 w-4 rounded border-line text-trust"
        />
        <span>I consent to BrowseJobs publishing this testimonial on their website.</span>
      </label>

      <p className="mt-4 rounded-[10px] border-l-2 border-trust bg-sky/40 px-3 py-2 text-xs text-muted">
        This voucher is our thank-you for your BrowseJobs testimonial. A Google review is completely separate —
        never required, and never tied to any reward.
      </p>

      {error && (
        <p className="mt-4 rounded-[10px] bg-warn/10 px-3 py-2 text-sm text-warn">{error}</p>
      )}

      <button
        type="submit"
        disabled={!canSubmit}
        className="mt-6 w-full rounded-full bg-trust px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-deep disabled:opacity-50"
      >
        {submitting ? "Submitting…" : "Submit testimonial"}
      </button>
    </form>
  );
}

export default function TestimonialPage() {
  return (
    <main className="mx-auto grid min-h-screen max-w-lg place-items-center px-5 py-12">
      <div className="w-full">
        <Suspense fallback={<div className="shimmer h-96 rounded-[22px]" />}>
          <TestimonialForm />
        </Suspense>
      </div>
    </main>
  );
}
