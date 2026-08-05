"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { use, useCallback, useEffect, useRef, useState } from "react";
import { apiJson } from "@/lib/api";

type Turn = { id: number; role: "interviewer" | "candidate"; body: string };

type MockSession = {
  id: number;
  status: "in_progress" | "completed" | "abandoned";
  mode: "text" | "voice";
  role_title: string | null;
  questions_asked: number;
  max_questions: number;
  min_answers: number;
  ready_to_finish: boolean;
  turns: Turn[];
};

/** Minimal Web Speech API typings (not in TS's DOM lib). */
type SpeechAlternativeLike = { transcript: string };
type SpeechResultLike = { isFinal: boolean; 0: SpeechAlternativeLike };
type SpeechResultEventLike = { resultIndex: number; results: { length: number; [index: number]: SpeechResultLike } };
type SpeechRecognitionLike = {
  lang: string;
  continuous: boolean;
  interimResults: boolean;
  onresult: ((event: SpeechResultEventLike) => void) | null;
  onend: (() => void) | null;
  onerror: ((event: { error?: string }) => void) | null;
  start: () => void;
  stop: () => void;
};

function speechRecognitionCtor(): (new () => SpeechRecognitionLike) | null {
  if (typeof window === "undefined") return null;
  const w = window as unknown as {
    SpeechRecognition?: new () => SpeechRecognitionLike;
    webkitSpeechRecognition?: new () => SpeechRecognitionLike;
  };
  return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
}

function pad(n: number): string {
  return n.toString().padStart(2, "0");
}

/**
 * The Zoom-style interview room: a full-screen call layout over the same
 * text-mock session — the interviewer tile "speaks" each question (TTS),
 * the mic transcribes the spoken answer, and your webcam self-view keeps it
 * feeling like a real screening call. The AI brain and scorecard are the
 * existing server-side mock endpoints.
 */
export default function InterviewRoomPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const [session, setSession] = useState<MockSession | null>(null);
  const [loading, setLoading] = useState(true);
  const [phase, setPhase] = useState<"idle" | "listening" | "thinking" | "grading">("idle");
  const [speaking, setSpeaking] = useState(false);
  const [transcript, setTranscript] = useState("");
  const [camOn, setCamOn] = useState(false);
  const [micError, setMicError] = useState<string | null>(null);
  const [elapsed, setElapsed] = useState(0);
  const [captions, setCaptions] = useState(true);
  // Proctoring: the student accepts the rules before the interview starts;
  // leaving the tab once is a warning, twice closes the interview.
  const [accepted, setAccepted] = useState(false);
  const [tabWarning, setTabWarning] = useState(false);
  const violationsRef = useRef(0);
  const closingRef = useRef(false);

  const videoRef = useRef<HTMLVideoElement | null>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const recognitionRef = useRef<SpeechRecognitionLike | null>(null);
  const spokenTurnRef = useRef<number | null>(null);
  const committedRef = useRef("");

  const question = session ? [...session.turns].reverse().find((t) => t.role === "interviewer") : undefined;
  const answered = session?.turns.filter((t) => t.role === "candidate").length ?? 0;
  const canFinish = session?.status === "in_progress" && answered >= (session?.min_answers ?? 2);

  const load = useCallback(() => {
    apiJson<{ data: MockSession }>(`/api/v1/me/mocks/${id}`)
      .then((r) => setSession(r.data))
      .catch(() => setSession(null))
      .finally(() => setLoading(false));
  }, [id]);

  useEffect(() => { load(); }, [load]);

  // Call timer.
  useEffect(() => {
    const t = setInterval(() => setElapsed((s) => s + 1), 1000);
    return () => clearInterval(t);
  }, []);

  // Webcam self-view — local preview only, nothing is recorded or uploaded.
  useEffect(() => {
    let cancelled = false;
    navigator.mediaDevices?.getUserMedia({ video: true })
      .then((stream) => {
        if (cancelled) { stream.getTracks().forEach((t) => t.stop()); return; }
        streamRef.current = stream;
        setCamOn(true);
      })
      .catch(() => setCamOn(false));
    return () => {
      cancelled = true;
      streamRef.current?.getTracks().forEach((t) => t.stop());
    };
  }, []);

  // The <video> element mounts only after camOn flips (and after the rules
  // screen), so attach the stream whenever it (re)appears — assigning inside
  // the getUserMedia callback races the mount and leaves a black tile.
  useEffect(() => {
    if (camOn && accepted && videoRef.current !== null && streamRef.current !== null) {
      videoRef.current.srcObject = streamRef.current;
    }
  }, [camOn, accepted]);

  const speak = useCallback((text: string) => {
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = "en-IN";
    utterance.rate = 0.95;
    utterance.onend = () => setSpeaking(false);
    setSpeaking(true);
    window.speechSynthesis.speak(utterance);
  }, []);

  // Read each new interviewer question aloud — only once the rules are accepted.
  useEffect(() => {
    if (accepted && question !== undefined && spokenTurnRef.current !== question.id) {
      spokenTurnRef.current = question.id;
      speak(question.body);
    }
  }, [accepted, question, speak]);

  // Proctoring: hiding the tab (switching tabs/apps, minimising) is a
  // violation. First offence warns; the second closes the interview — the
  // session is marked abandoned server-side and the credit stays spent.
  useEffect(() => {
    if (!accepted || session === null || session.status !== "in_progress") return;

    const onVisibility = () => {
      if (!document.hidden || closingRef.current) return;
      violationsRef.current += 1;
      if (violationsRef.current === 1) {
        setTabWarning(true);
      } else {
        closingRef.current = true;
        recognitionRef.current?.stop();
        window.speechSynthesis.cancel();
        void apiJson(`/api/v1/me/mocks/${id}/abandon`, { method: "POST" })
          .catch(() => {})
          .finally(() => router.push(`/mock/${id}`));
      }
    };

    document.addEventListener("visibilitychange", onVisibility);
    return () => document.removeEventListener("visibilitychange", onVisibility);
  }, [accepted, session, id, router]);

  // Leaving the room must release the mic, camera, and speaker.
  useEffect(() => () => {
    recognitionRef.current?.stop();
    if (typeof window !== "undefined") window.speechSynthesis.cancel();
  }, []);

  const sendAnswer = useCallback(async (text: string) => {
    if (text.trim() === "") { setPhase("idle"); return; }
    setPhase("thinking");
    setTranscript("");
    try {
      const r = await apiJson<{ data: MockSession }>(`/api/v1/me/mocks/${id}/answer`, {
        method: "POST",
        body: JSON.stringify({ answer: text.trim() }),
      });
      setSession(r.data);
      setPhase("idle");
    } catch {
      setMicError("The interviewer could not hear that — try answering again.");
      setPhase("idle");
    }
  }, [id]);

  const stopAndSend = useCallback(() => {
    recognitionRef.current?.stop();
    recognitionRef.current = null;
    void sendAnswer(committedRef.current + " " + transcript.slice(committedRef.current.length));
  }, [sendAnswer, transcript]);

  const startListening = useCallback(() => {
    const Ctor = speechRecognitionCtor();
    if (Ctor === null) { setMicError("Voice input needs Chrome or Edge."); return; }
    window.speechSynthesis.cancel();
    setSpeaking(false);
    setMicError(null);
    committedRef.current = "";
    setTranscript("");

    const recognition = new Ctor();
    recognition.lang = "en-IN";
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.onresult = (event) => {
      let interim = "";
      for (let i = event.resultIndex; i < event.results.length; i++) {
        const result = event.results[i];
        if (result.isFinal) committedRef.current += result[0].transcript.trim() + " ";
        else interim += result[0].transcript;
      }
      setTranscript((committedRef.current + interim).trimStart());
    };
    recognition.onerror = (event) => {
      setMicError(
        event.error === "not-allowed"
          ? "Microphone blocked — allow it in the address bar, then tap Answer again."
          : "The mic cut out — tap Answer to continue.",
      );
      setPhase("idle");
    };
    recognition.onend = () => {
      // Chrome ends recognition on silence; treat it like "done answering".
      if (recognitionRef.current !== null) {
        recognitionRef.current = null;
        setPhase((p) => (p === "listening" ? "idle" : p));
      }
    };
    recognition.start();
    recognitionRef.current = recognition;
    setPhase("listening");
  }, []);

  async function finish() {
    recognitionRef.current?.stop();
    window.speechSynthesis.cancel();
    setPhase("grading");
    try {
      await apiJson(`/api/v1/me/mocks/${id}/finish`, { method: "POST" });
    } catch {
      // The session page shows the precise error state.
    }
    router.push(`/mock/${id}`);
  }

  if (loading) {
    return (
      <div className="fixed inset-0 z-[60] grid place-items-center bg-ink">
        <div className="shimmer h-12 w-12 rounded-full" />
      </div>
    );
  }

  if (!session || session.status !== "in_progress" || session.mode !== "text") {
    return (
      <div className="fixed inset-0 z-[60] grid place-items-center bg-ink p-6 text-center">
        <div>
          <p className="text-sm text-white">This interview room is closed.</p>
          <Link href={`/mock/${id}`} className="mt-3 inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white">
            View the session →
          </Link>
        </div>
      </div>
    );
  }

  const listening = phase === "listening";

  if (!accepted) {
    return (
      <div className="fixed inset-0 z-[60] grid place-items-center bg-ink p-6">
        <div className="w-full max-w-md rounded-[22px] bg-white p-7">
          <p className="mono text-[11px] uppercase tracking-widest text-trust">Before you begin</p>
          <h1 className="display mt-2 text-xl text-ink">Interview rules</h1>
          <ul className="mt-4 space-y-3 text-sm text-ink">
            <li className="flex gap-2"><span>🎥</span> Treat it like the real room — camera on, speak your answers out loud.</li>
            <li className="flex gap-2"><span>🚫</span> <span><strong>Stay in this tab.</strong> Don&apos;t switch tabs, apps, or windows, and don&apos;t minimise.</span></li>
            <li className="flex gap-2"><span>⚠️</span> <span>Leaving the tab once gives a warning. <strong>Leaving twice ends the interview</strong> — the session is spent and it won&apos;t be graded.</span></li>
          </ul>
          <button
            onClick={() => setAccepted(true)}
            className="mt-6 w-full rounded-full bg-trust px-5 py-3 text-sm font-semibold text-white"
          >
            I understand — start the interview
          </button>
          <Link href={`/mock/${id}`} className="mt-3 block text-center text-sm text-muted hover:underline">
            Not now
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="fixed inset-0 z-[60] flex flex-col bg-ink">
      {/* Top bar */}
      <header className="flex items-center justify-between px-5 py-3">
        <div className="flex items-center gap-3">
          <span className="h-2 w-2 animate-pulse rounded-full bg-warn motion-reduce:animate-none" />
          <span className="text-sm font-semibold text-white">{session.role_title ?? "Mock"} interview</span>
        </div>
        <div className="mono flex items-center gap-4 text-xs text-white/70">
          <span>Q {Math.min(session.questions_asked, session.max_questions)}/{session.max_questions}</span>
          <span>{pad(Math.floor(elapsed / 60))}:{pad(elapsed % 60)}</span>
        </div>
      </header>

      {/* Proctoring warning after the first tab switch */}
      {tabWarning && (
        <div className="mx-5 rounded-[10px] bg-warn/90 px-4 py-2 text-center text-sm font-semibold text-white">
          ⚠️ You left the interview tab. One more time and this interview closes — unscored, session spent.
        </div>
      )}

      {/* Stage */}
      <main className="relative mx-auto flex w-full max-w-3xl flex-1 flex-col items-center justify-center px-5">
        {/* Interviewer tile */}
        <div className="flex flex-col items-center">
          <div className="relative grid h-36 w-36 place-items-center sm:h-44 sm:w-44">
            {(speaking || phase === "thinking") && (
              <>
                <span className="absolute inset-0 animate-ping rounded-full bg-trust/20 motion-reduce:animate-none" />
                <span className="absolute inset-3 animate-pulse rounded-full bg-trust/25 motion-reduce:animate-none" />
              </>
            )}
            <div className="relative grid h-28 w-28 place-items-center rounded-full bg-gradient-to-br from-deep to-trust shadow-[0_10px_40px_rgba(27,109,240,.35)] sm:h-32 sm:w-32">
              <span className="display text-3xl text-white">AI</span>
            </div>
          </div>
          <p className="mt-4 text-sm font-semibold text-white">Interviewer</p>
          <p className="mono mt-1 text-[11px] uppercase tracking-widest text-white/50">
            {phase === "thinking" ? "Thinking…" : phase === "grading" ? "Preparing your scorecard…" : speaking ? "Speaking" : listening ? "Listening to you" : "Waiting for your answer"}
          </p>
        </div>

        {/* Captions */}
        {captions && question !== undefined && phase !== "grading" && (
          <div className="mt-8 w-full rounded-[14px] bg-white/10 px-4 py-3 backdrop-blur">
            <p className="text-center text-sm leading-relaxed text-white">{question.body}</p>
          </div>
        )}
        {listening && transcript !== "" && (
          <p className="mt-3 w-full text-center text-sm italic text-white/70">“{transcript}”</p>
        )}
        {micError && <p className="mt-3 text-sm text-warn">{micError}</p>}

        {/* Self view */}
        <div className="absolute bottom-4 right-5 h-28 w-40 overflow-hidden rounded-[14px] border border-white/15 bg-ink-2 shadow-lg sm:h-32 sm:w-48">
          {camOn ? (
            <video ref={videoRef} autoPlay playsInline muted className="h-full w-full scale-x-[-1] object-cover" />
          ) : (
            <div className="grid h-full w-full place-items-center">
              <span className="text-xs text-white/50">Camera off</span>
            </div>
          )}
          <span className="absolute bottom-1 left-2 text-[10px] font-medium text-white/80">You</span>
        </div>
      </main>

      {/* Control bar */}
      <footer className="flex flex-wrap items-center justify-center gap-3 px-5 pb-6 pt-3">
        {session.ready_to_finish ? (
          <p className="w-full text-center text-xs text-white/60">That&apos;s the full round — end the interview to get your scorecard.</p>
        ) : listening ? (
          <button
            onClick={stopAndSend}
            className="rounded-full bg-verify px-6 py-3 text-sm font-semibold text-white"
          >
            ✓ Done — send answer
          </button>
        ) : (
          <button
            onClick={startListening}
            disabled={phase === "thinking" || phase === "grading"}
            className="rounded-full bg-trust px-6 py-3 text-sm font-semibold text-white disabled:opacity-40"
          >
            🎙 Answer
          </button>
        )}

        <button
          onClick={() => setCaptions((c) => !c)}
          className="rounded-full border border-white/20 px-4 py-3 text-sm text-white/80 hover:border-white/40"
        >
          {captions ? "Hide captions" : "Show captions"}
        </button>

        {canFinish ? (
          <button
            onClick={finish}
            disabled={phase === "grading"}
            className="rounded-full bg-warn px-5 py-3 text-sm font-semibold text-white disabled:opacity-40"
          >
            {phase === "grading" ? "Grading…" : "End interview"}
          </button>
        ) : (
          <Link href={`/mock/${id}`} className="rounded-full border border-white/20 px-4 py-3 text-sm text-white/80 hover:border-white/40">
            Leave
          </Link>
        )}
      </footer>
    </div>
  );
}
