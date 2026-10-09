"use client";

import Link from "next/link";
import { useRef, useState, type KeyboardEvent, type ReactNode } from "react";
import { MockHistory, type HistoryRow } from "@/components/mocks/MockHistory";
import { InterviewIcon, type InterviewIconName } from "@/components/mocks/InterviewIcons";
import { MicIllustration, SpeechBubblesIllustration } from "@/components/mocks/InterviewIllustrations";
import { useVoiceInterview, voiceStatus, type MockSummary } from "@/components/mocks/MockCards";
import type { MockKind } from "@/lib/mockKinds";

const CARD = "rounded-[18px] border border-line bg-white shadow-soft";
const FOCUS = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-trust focus-visible:ring-offset-2";

const PREP: { icon: InterviewIconName; title: string; body: string }[] = [
  { icon: "headphones", title: "Find a quiet space", body: "Choose a calm, noise-free environment for the best experience." },
  { icon: "video", title: "Allow microphone & camera", body: "We need access to your microphone and camera to conduct the interview." },
  { icon: "globe", title: "Use Chrome or Edge", body: "Works best in Chrome or Edge, with your microphone and camera allowed." },
];

const BENEFITS: { icon: InterviewIconName; title: string; body: string }[] = [
  { icon: "target", title: "Practise for your role", body: "Role-specific questions to build confidence." },
  { icon: "fileText", title: "Review model answers", body: "See high-quality answers after your interview." },
  { icon: "chartBars", title: "Focus on your next 3 fixes", body: "Get a scorecard with key areas to improve." },
];

type TabKind = Extract<MockKind, "voice" | "job" | "cv">;

const TABS: { kind: TabKind; label: string }[] = [
  { kind: "voice", label: "Voice" },
  { kind: "job", label: "Job interviews" },
  { kind: "cv", label: "AI Readiness" },
];

function IconTile({ name, size = "md" }: { name: InterviewIconName; size?: "md" | "lg" }) {
  return (
    <span
      className={`grid shrink-0 place-items-center rounded-2xl bg-sky text-trust ${
        size === "lg" ? "h-14 w-14" : "h-12 w-12"
      }`}
    >
      <InterviewIcon name={name} className={size === "lg" ? "h-7 w-7" : "h-6 w-6"} />
    </span>
  );
}

/**
 * The AI Interviews page (/student-ai-mock): voice interview hero, a
 * preparation checklist, what you get, and the student's own interviews by
 * type. All numbers come from /me/mocks — nothing about limits is hardcoded.
 */
export function AiInterviewsHub({
  summary,
  reload,
  previewRows,
  children,
}: {
  summary: MockSummary;
  reload: () => void;
  /** Fixture rows per tab — design previews only; real pages load history from the API. */
  previewRows?: Partial<Record<TabKind, HistoryRow[]>>;
  /** Extra sections (module mocks, gap report) shown under the interview history. */
  children?: ReactNode;
}) {
  const voice = voiceStatus(summary);
  const { busy, error, notice, start, buyTopup } = useVoiceInterview(summary, reload);
  const starting = busy === "start";

  const attemptsLabel =
    voice.mode === "call"
      ? `${voice.credits} session${voice.credits === 1 ? "" : "s"} available`
      : `${voice.remaining} attempt${voice.remaining === 1 ? "" : "s"} available`;

  const usageLine =
    voice.mode === "call"
      ? `${voice.credits} session${voice.credits === 1 ? "" : "s"} left · 1 credit per session`
      : `${voice.used} of ${voice.limit} attempts used · No credits required`;

  return (
    <div className="mx-auto max-w-6xl">
      {/* Header */}
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.14em] text-trust">AI interview coach</p>
          <h1 className="display mt-2 text-[32px] leading-[1.1] text-ink sm:text-[40px]">Ace your next interview.</h1>
          <p className="mt-2 text-base text-muted">Practice out loud. Get clear feedback. Walk in confident.</p>
        </div>
        <span className="inline-flex items-center gap-2 rounded-xl bg-sky px-4 py-2.5 text-sm font-semibold text-trust">
          <InterviewIcon name="gift" className="h-5 w-5" />
          <span className={voice.exhausted ? "text-muted" : undefined}>{attemptsLabel}</span>
        </span>
      </div>

      {summary.human_mock_unlocked && (
        <div className="mt-6 flex items-start gap-3 rounded-[18px] border border-verify/30 bg-verify-bg p-5">
          <InterviewIcon name="award" className="mt-0.5 h-5 w-5 shrink-0 text-verify" />
          <div>
            <p className="text-sm font-semibold text-verify">Human mock unlocked</p>
            <p className="mt-1 text-sm text-ink">
              Your best score is <span className="mono">{summary.best_score}</span> — you&apos;re ready for a live mock with a
              mentor. Your counselor will reach out to schedule it.
            </p>
          </div>
        </div>
      )}

      {/* Hero + preparation */}
      <div className="mt-6 grid gap-6 lg:grid-cols-3">
        <section
          aria-labelledby="voice-hero-title"
          className="relative flex flex-col justify-center overflow-hidden rounded-[18px] border border-trust/20 bg-gradient-to-br from-sky via-white to-sky/70 p-6 shadow-soft sm:p-7 lg:col-span-2"
        >
          <div className="grid w-full items-center gap-4 md:grid-cols-[minmax(0,1fr)_184px] 2xl:grid-cols-[minmax(0,1fr)_240px]">
            <div className="order-2 md:order-1">
              <span className="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-trust ring-1 ring-trust/15">
                <InterviewIcon name="mic" className="h-4 w-4" />
                Voice interview
              </span>
              <h2 id="voice-hero-title" className="display mt-4 text-[28px] leading-[1.15] text-ink sm:text-[32px]">
                Your next offer starts with practice.
              </h2>
              <p className="mt-3 max-w-md text-base text-muted">
                Talk to your AI interviewer and get personalised feedback for your target role.
              </p>

              <ul className="mt-5 flex flex-wrap gap-2">
                {[
                  { icon: "clock" as const, label: `Up to ${voice.maxMinutes} min` },
                  { icon: "fileText" as const, label: "Real-time transcription" },
                  { icon: "chartColumn" as const, label: "Detailed scorecard" },
                ].map((chip) => (
                  <li
                    key={chip.label}
                    className="inline-flex items-center gap-1 rounded-xl border border-line bg-white px-2 py-1.5 text-[13px] text-ink"
                  >
                    <InterviewIcon name={chip.icon} className="h-4 w-4 text-trust" />
                    {chip.label}
                  </li>
                ))}
              </ul>

              <div className="mt-6">
                {voice.joinUrl ? (
                  <a
                    href={voice.joinUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={`inline-flex min-h-[52px] w-full items-center justify-center gap-3 rounded-full bg-trust px-7 text-base font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep sm:w-auto ${FOCUS}`}
                  >
                    <InterviewIcon name="mic" className="h-5 w-5" />
                    Rejoin the call
                    <InterviewIcon name="arrowRight" className="h-5 w-5" />
                  </a>
                ) : voice.mode === "call" && voice.exhausted ? (
                  <div>
                    <p className="text-sm text-ink">You&apos;re out of voice sessions. Finish a module to unlock one, or top up:</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                      {voice.topups.map((t) => (
                        <button
                          key={t.product_id}
                          onClick={() => buyTopup(t)}
                          disabled={busy === t.product_id}
                          className={`min-h-[44px] rounded-full border border-line bg-white px-4 text-sm font-semibold text-ink hover:border-trust disabled:opacity-50 ${FOCUS}`}
                        >
                          {busy === t.product_id
                            ? "Creating order…"
                            : `${t.sessions} session${t.sessions === 1 ? "" : "s"} · ₹${Math.round(t.price_paise / 100)}`}
                        </button>
                      ))}
                    </div>
                  </div>
                ) : (
                  <button
                    type="button"
                    onClick={start}
                    disabled={starting || voice.exhausted}
                    aria-busy={starting}
                    className={`inline-flex min-h-[52px] w-full items-center justify-center gap-3 rounded-full bg-trust px-7 text-base font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep disabled:cursor-not-allowed disabled:opacity-60 disabled:shadow-none sm:w-auto ${FOCUS}`}
                  >
                    <InterviewIcon name="mic" className="h-5 w-5" />
                    {starting ? "Opening the room…" : voice.exhausted ? "All attempts used" : "Start a voice interview"}
                    {!starting && !voice.exhausted && <InterviewIcon name="arrowRight" className="h-5 w-5" />}
                  </button>
                )}
                <p className="mono mt-3 text-xs text-muted">{usageLine}</p>
                {error && <p role="alert" className="mt-2 text-sm text-warn">{error}</p>}
                {notice && <p role="status" className="mt-2 text-sm text-verify">{notice}</p>}
              </div>
            </div>

            <div className="order-1 flex justify-center md:order-2">
              <MicIllustration className="h-auto w-36 sm:w-48 md:w-full" />
            </div>
          </div>
        </section>

        <section aria-labelledby="prep-title" className={`${CARD} p-6 sm:p-7`}>
          <h2 id="prep-title" className="display text-xl text-ink">Before you begin</h2>
          <ul className="mt-4 divide-y divide-line">
            {PREP.map((item) => (
              <li key={item.title} className="flex gap-4 py-4 first:pt-1">
                <IconTile name={item.icon} />
                <div>
                  <p className="text-[15px] font-semibold text-ink">{item.title}</p>
                  <p className="mt-1 text-sm text-muted">{item.body}</p>
                </div>
              </li>
            ))}
          </ul>
          <p className="mt-2 flex items-start gap-3 rounded-xl bg-sky px-4 py-3 text-sm text-trust">
            <InterviewIcon name="info" className="mt-0.5 h-5 w-5 shrink-0" />
            Speak naturally. Your answers are transcribed as you go.
          </p>
        </section>
      </div>

      {/* Benefits */}
      <ul className="mt-6 grid divide-y divide-line sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        {BENEFITS.map((b) => (
          <li key={b.title} className="flex items-center gap-4 py-4 sm:px-6 sm:py-2 sm:first:pl-0 sm:last:pr-0">
            <IconTile name={b.icon} size="lg" />
            <div>
              <p className="text-[15px] font-semibold text-ink">{b.title}</p>
              <p className="mt-1 text-sm text-muted">{b.body}</p>
            </div>
          </li>
        ))}
      </ul>

      <InterviewHistory
        counts={summary.kind_counts ?? {}}
        previewRows={previewRows}
        onStartVoice={voice.exhausted || voice.joinUrl ? undefined : start}
      />

      {children}
    </div>
  );
}

/** "Your interviews" — one accessible tab per interview type, real counts. */
function InterviewHistory({
  counts,
  previewRows,
  onStartVoice,
}: {
  counts: Partial<Record<MockKind, number>>;
  previewRows?: Partial<Record<TabKind, HistoryRow[]>>;
  onStartVoice?: () => void;
}) {
  const [active, setActive] = useState<TabKind>("voice");
  const tabRefs = useRef<(HTMLButtonElement | null)[]>([]);

  function onKeyDown(e: KeyboardEvent<HTMLButtonElement>, index: number) {
    const last = TABS.length - 1;
    const next =
      e.key === "ArrowRight" ? (index === last ? 0 : index + 1)
      : e.key === "ArrowLeft" ? (index === 0 ? last : index - 1)
      : e.key === "Home" ? 0
      : e.key === "End" ? last
      : null;
    if (next === null) return;
    e.preventDefault();
    setActive(TABS[next].kind);
    tabRefs.current[next]?.focus();
  }

  const empty: Record<TabKind, ReactNode> = {
    voice: onStartVoice ? (
      <button type="button" onClick={onStartVoice} className={`inline-flex min-h-[44px] items-center gap-2 rounded-full px-3 text-sm font-semibold text-trust hover:underline ${FOCUS}`}>
        Start practising <InterviewIcon name="arrowRight" className="h-4 w-4" />
      </button>
    ) : null,
    job: (
      <Link href="/jobs-for-you" className={`inline-flex min-h-[44px] items-center gap-2 rounded-full px-3 text-sm font-semibold text-trust hover:underline ${FOCUS}`}>
        See open roles <InterviewIcon name="arrowRight" className="h-4 w-4" />
      </Link>
    ),
    cv: (
      <Link href="/jobs-for-you" className={`inline-flex min-h-[44px] items-center gap-2 rounded-full px-3 text-sm font-semibold text-trust hover:underline ${FOCUS}`}>
        Take the AI Readiness Interview <InterviewIcon name="arrowRight" className="h-4 w-4" />
      </Link>
    ),
  };

  return (
    <section aria-labelledby="history-title" className={`${CARD} mt-6 p-6 sm:p-7`}>
      <h2 id="history-title" className="display text-2xl text-ink">Your interviews</h2>
      <p className="mt-1 text-sm text-muted">Your practice, feedback and progress in one place.</p>

      <div className="mt-5 border-b border-line">
      <div role="tablist" aria-label="Interview types" className="flex gap-2 overflow-x-auto">
        {TABS.map((tab, i) => {
          const on = tab.kind === active;
          return (
            <button
              key={tab.kind}
              ref={(el) => { tabRefs.current[i] = el; }}
              role="tab"
              id={`interview-tab-${tab.kind}`}
              aria-selected={on}
              aria-controls={`interview-panel-${tab.kind}`}
              tabIndex={on ? 0 : -1}
              onClick={() => setActive(tab.kind)}
              onKeyDown={(e) => onKeyDown(e, i)}
              className={`flex min-h-[44px] shrink-0 items-center gap-2 border-b-2 px-4 text-sm font-semibold transition-colors ${
                on ? "border-trust text-trust" : "border-transparent text-ink hover:text-trust"
              } ${FOCUS}`}
            >
              {tab.label}
              <span className={`mono rounded-full px-2 py-0.5 text-[11px] ${on ? "bg-sky text-trust" : "bg-paper text-muted"}`}>
                {counts[tab.kind] ?? 0}
              </span>
            </button>
          );
        })}
      </div>
      </div>

      <div
        role="tabpanel"
        id={`interview-panel-${active}`}
        aria-labelledby={`interview-tab-${active}`}
        tabIndex={0}
        className={`pt-2 ${FOCUS} rounded-[10px]`}
      >
        <MockHistory
          key={active}
          kind={active}
          rows={previewRows?.[active]}
          emptyText="Complete a session to see your scorecard and personalised feedback."
          empty={
            <div className="flex flex-col items-center py-10 text-center">
              <SpeechBubblesIllustration className="h-14 w-[72px]" />
              <p className="mt-4 text-base font-semibold text-ink">Your first interview starts here</p>
              <p className="mt-1 max-w-sm text-sm text-muted">Complete a session to see your scorecard and personalised feedback.</p>
              <div className="mt-3">{empty[active]}</div>
            </div>
          }
        />
      </div>
    </section>
  );
}
