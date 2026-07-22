"use client";

import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { Disclaimer } from "@/components/brand/Disclaimer";

/**
 * Course-page social proof: placement-story cards (transformation → package →
 * company → rounds → the WhatsApp offer message), a per-course interview-question
 * popup, and the course's real reviews. Fetched from the public social-proof
 * endpoint; renders nothing until there's something to show, so course pages
 * without content stay clean.
 */

type Story = {
  name: string;
  before: string;
  after: string;
  package: string | null;
  company: string | null;
  company_color: string | null;
  rounds: number | null;
  quote: string | null;
  screenshot_url: string | null;
};

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

function StoryCard({ story, onSeeQuestions }: { story: Story; onSeeQuestions: () => void }) {
  return (
    <article className="flex flex-col overflow-hidden rounded-[18px] border border-line bg-white shadow-soft">
      <div className="p-5">
        <div className="flex items-center gap-3">
          <Initial name={story.name} color={story.company_color} />
          <div>
            <p className="font-semibold text-ink">{story.name}</p>
            <p className="text-sm text-muted">
              {story.before} → <span className="font-medium text-verify">{story.after}</span>
            </p>
          </div>
        </div>

        <div className="mt-4 grid grid-cols-3 gap-2">
          {story.package && (
            <div className="rounded-[12px] border border-line bg-paper px-3 py-2">
              <span className="mono block text-[9px] uppercase tracking-widest text-muted">Package</span>
              <span className="mono mt-0.5 block text-base font-semibold text-verify">{story.package}</span>
            </div>
          )}
          {story.company && (
            <div className="rounded-[12px] border border-line bg-paper px-3 py-2">
              <span className="mono block text-[9px] uppercase tracking-widest text-muted">Joined</span>
              <span className="mt-0.5 block text-sm font-semibold text-ink">{story.company}</span>
            </div>
          )}
          {story.rounds != null && (
            <div className="rounded-[12px] border border-line bg-paper px-3 py-2">
              <span className="mono block text-[9px] uppercase tracking-widest text-muted">Cleared</span>
              <span className="mono mt-0.5 block text-base font-semibold text-ink">
                {story.rounds} <span className="text-[11px] font-normal text-muted">rounds</span>
              </span>
            </div>
          )}
        </div>
      </div>

      {/* The offer message — a real screenshot if uploaded, else the quote as a chat bubble. */}
      {story.screenshot_url ? (
        // Signed, short-lived S3 URL — next/image would need per-host remote config; a plain img is right here.
        // eslint-disable-next-line @next/next/no-img-element
        <img src={story.screenshot_url} alt={`${story.name}'s offer message`} className="mx-5 rounded-[10px] border border-line" />
      ) : story.quote ? (
        <div className="mx-5 rounded-[10px] bg-[#e5ddd4] p-3">
          <div className="ml-auto max-w-[92%] rounded-[8px] rounded-tr-[2px] bg-[#dcf8c6] px-3 py-2 text-[13px] text-[#111b21] shadow-[0_1px_0.5px_rgba(0,0,0,0.13)]">
            {story.quote}
          </div>
        </div>
      ) : null}

      <div className="flex items-center justify-between gap-3 p-5 pt-4">
        <button
          onClick={onSeeQuestions}
          className="rounded-full border border-trust/40 bg-trust/10 px-4 py-2 text-sm font-semibold text-trust transition-colors hover:bg-trust/15"
        >
          See the questions they were asked →
        </button>
      </div>
    </article>
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
  if (stories.length === 0 && reviews.length === 0) return null;

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
                <StoryCard key={i} story={s} onSeeQuestions={() => setShowQuestions(true)} />
              ))}
            </div>
            <div className="mt-5">
              <Disclaimer />
            </div>
          </>
        )}

        {reviews.length > 0 && (
          <div className={stories.length > 0 ? "mt-16" : ""}>
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
