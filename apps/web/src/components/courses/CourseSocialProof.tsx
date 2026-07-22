"use client";

import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { StoryCard, type Story } from "@/components/courses/StoryCard";

/**
 * Course-page social proof: placement-story cards (transformation → package →
 * company → rounds → the WhatsApp offer message), a per-course interview-question
 * popup, and the course's real reviews. Fetched from the public social-proof
 * endpoint; renders nothing until there's something to show, so course pages
 * without content stay clean.
 */

type Round = { round_no: number; round_name: string; questions: string[] };

type Review = {
  name: string;
  meta: string | null;
  rating: number;
  body: string;
  source: "google" | "justdial" | "whatsapp" | "platform";
};

type Payload = {
  course: { slug: string; name: string };
  stories: Story[];
  question_rounds: Round[];
  reviews: Review[];
};

const SOURCE_LABEL: Record<Review["source"], string> = {
  google: "Google",
  justdial: "JustDial",
  whatsapp: "WhatsApp",
  platform: "Testimonial",
};

function Initial({ name, color }: { name: string; color?: string | null }) {
  return (
    <span
      className="grid h-11 w-11 shrink-0 place-items-center rounded-full text-base font-bold text-white"
      style={{ background: color ?? "var(--bj-trust)" }}
    >
      {name.trim().charAt(0).toUpperCase()}
    </span>
  );
}

function ReviewCard({ review }: { review: Review }) {
  return (
    <article className="break-inside-avoid rounded-[16px] border border-line bg-white p-5 shadow-soft">
      <div className="flex items-center gap-3">
        <Initial name={review.name} />
        <div>
          <p className="font-semibold text-ink">{review.name}</p>
          {review.meta && <p className="text-xs text-muted">{review.meta}</p>}
        </div>
      </div>
      <div className="mt-3 flex items-center gap-2">
        <span className="text-amber" aria-label={`${review.rating} out of 5`}>
          {"★".repeat(review.rating)}
          <span className="text-line">{"★".repeat(5 - review.rating)}</span>
        </span>
        <span className="mono rounded-full border border-line px-2 py-0.5 text-[9px] uppercase tracking-widest text-muted">
          {SOURCE_LABEL[review.source]}
        </span>
      </div>
      <p className="mt-3 text-sm text-ink2/85">{review.body}</p>
    </article>
  );
}

function QuestionsModal({ rounds, courseName, onClose }: { rounds: Round[]; courseName: string; onClose: () => void }) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-ink/70 p-5"
      role="dialog"
      aria-modal="true"
      onClick={onClose}
    >
      <div
        className="max-h-[86vh] w-full max-w-xl overflow-auto rounded-[20px] border border-line bg-white shadow-soft"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="sticky top-0 flex items-start justify-between gap-3 border-b border-line bg-white p-5">
          <div>
            <p className="mono text-[11px] uppercase tracking-widest text-trust">Real interview questions</p>
            <h3 className="display mt-1.5 text-xl text-ink">{courseName} — the rounds you&apos;ll face</h3>
            <p className="mt-1 text-sm text-muted">Straight from our alumni&apos;s interviews. Study before yours.</p>
          </div>
          <button onClick={onClose} aria-label="Close" className="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-line text-muted hover:bg-paper">
            ✕
          </button>
        </div>
        <div className="p-5">
          {rounds.map((r) => (
            <div key={r.round_no} className="border-b border-line py-4 last:border-0">
              <div className="flex items-center gap-2.5">
                <span className="mono grid h-6 w-6 place-items-center rounded-[8px] bg-trust/15 text-xs font-semibold text-trust">
                  {String(r.round_no).padStart(2, "0")}
                </span>
                <span className="font-semibold text-ink">{r.round_name}</span>
              </div>
              <ul className="mt-2 space-y-1">
                {r.questions.map((q, i) => (
                  <li key={i} className="flex gap-2 text-sm text-ink2/85">
                    <span className="text-trust">›</span>
                    <span>{q}</span>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

export function CourseSocialProof({ slug }: { slug: string }) {
  const [data, setData] = useState<Payload | null>(null);
  const [showQuestions, setShowQuestions] = useState(false);

  useEffect(() => {
    let live = true;
    apiJson<{ data: Payload }>(`/api/v1/courses/${slug}/social-proof`)
      .then((r) => live && setData(r.data))
      .catch(() => {});
    return () => {
      live = false;
    };
  }, [slug]);

  if (!data) return null;
  const { stories, question_rounds, reviews } = data;
  if (stories.length === 0 && reviews.length === 0 && question_rounds.length === 0) return null;

  const questionCount = question_rounds.reduce((n, r) => n + r.questions.length, 0);

  return (
    <section className="bg-paper">
      <div className="mx-auto max-w-6xl px-5 py-16">
        {stories.length > 0 && (
          <>
            <p className="kicker text-verify">Placement stories</p>
            <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-4xl">From stuck → hired</h2>
            <p className="mt-3 max-w-2xl text-muted">
              Where our learners started, the package and company they landed, and the rounds they cleared —
              with the interview questions they faced, so you can study them.
            </p>
            <div className="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
              {stories.map((s, i) => (
                <StoryCard
                  key={i}
                  story={s}
                  cta={
                    <button
                      onClick={() => setShowQuestions(true)}
                      className="rounded-full border border-trust/40 bg-trust/10 px-4 py-2 text-sm font-semibold text-trust transition-colors hover:bg-trust/15"
                    >
                      See the questions they were asked →
                    </button>
                  }
                />
              ))}
            </div>
            <div className="mt-5">
              <Disclaimer />
            </div>
            {stories.some((s) => s.is_sample) && (
              <p className="mono mt-2 text-xs text-muted">
                Cards marked “Sample” are illustrative — real, consented student stories replace them as they’re published.
              </p>
            )}
          </>
        )}

        {/* Standalone question-bank entry — shows even when no live story card does. */}
        {question_rounds.length > 0 && (
          <div className={stories.length > 0 ? "mt-14" : ""}>
            <div className="flex flex-col items-start gap-4 rounded-[18px] border border-line bg-white p-6 shadow-soft md:flex-row md:items-center md:justify-between">
              <div>
                <p className="kicker text-trust">Real interview questions</p>
                <h3 className="display mt-2 text-2xl text-ink">The exact rounds our alumni faced</h3>
                <p className="mt-1.5 max-w-xl text-sm text-muted">
                  <span className="mono text-ink">{question_rounds.length}</span> rounds ·{" "}
                  <span className="mono text-ink">{questionCount}</span> questions reverse-engineered from real{" "}
                  {data.course.name} interviews. Study them before yours.
                </p>
              </div>
              <button
                onClick={() => setShowQuestions(true)}
                className="shrink-0 rounded-full bg-trust px-6 py-3 text-sm font-semibold text-white shadow-[0_6px_20px_rgba(27,109,240,0.35)] transition-transform hover:-translate-y-0.5"
              >
                See the questions →
              </button>
            </div>
          </div>
        )}

        {reviews.length > 0 && (
          <div className={stories.length > 0 || question_rounds.length > 0 ? "mt-16" : ""}>
            <p className="kicker text-verify">Real · unedited reviews</p>
            <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-4xl">What learners actually say</h2>
            <div className="mt-8 columns-1 gap-5 md:columns-2 lg:columns-3 [&>*]:mb-5">
              {reviews.map((r, i) => (
                <ReviewCard key={i} review={r} />
              ))}
            </div>
          </div>
        )}
      </div>

      {showQuestions && question_rounds.length > 0 && (
        <QuestionsModal rounds={question_rounds} courseName={data.course.name} onClose={() => setShowQuestions(false)} />
      )}
    </section>
  );
}
