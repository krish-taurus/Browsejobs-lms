"use client";

import { useEffect, useRef, useState } from "react";
import { ApiError, apiJson, apiPostBlob } from "@/lib/api";

/**
 * The AI interviewer, as the meeting bot runs it inside a Zoom call.
 *
 * The bot's browser gives this page the call's audio as its microphone and
 * puts this page's sound and picture into the call. So the loop is simply:
 * say the question (the server's voice, /speak), listen until the candidate
 * stops talking, transcribe that answer (/transcribe), send it (/answer),
 * and say the next question — until the round is complete (/finish). Every
 * step is the same server-side interviewer the BrowseJobs room uses, so
 * questions, scoring and the scorecard are identical.
 *
 * Nobody touches this page: it starts by itself, waits for the candidate to
 * say hello, and never needs a click.
 */

type Turn = { id: number; role: "interviewer" | "candidate"; body: string };
type Session = {
  id: number;
  status: "in_progress" | "completed" | "abandoned";
  questions_asked: number;
  max_questions: number;
  ready_to_finish: boolean;
  turns: Turn[];
};
type Started = { mock_id: number; first_name: string; role: string | null; company: string | null; round: string };
type Phase = "connecting" | "waiting" | "speaking" | "listening" | "thinking" | "finished" | "error";

/** Silence that ends an answer, once they have spoken for a moment. */
const END_SILENCE_MS = 2600;
const MIN_ANSWER_MS = 1500;
const MAX_ANSWER_MS = 180_000;
/** How long to wait for an answer to begin before nudging. */
const NO_ANSWER_MS = 40_000;
/** How long to keep inviting a candidate who has not joined (the bot leaves after 15 minutes alone). */
const GREETING_ROUNDS = 14;

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

export function InterviewBot({ code }: { code: string }) {
  const [phase, setPhase] = useState<Phase>("connecting");
  const [info, setInfo] = useState<Started | null>(null);
  const [caption, setCaption] = useState<string>("");
  const [progress, setProgress] = useState<{ asked: number; max: number } | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const booted = useRef(false);

  useEffect(() => {
    if (booted.current) return;
    booted.current = true;
    void run();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function run() {
    const base = `/api/v1/interview-bot/${code}`;
    let mic: MediaStream;
    try {
      mic = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: false, noiseSuppression: true, autoGainControl: true } });
    } catch {
      return fail("No call audio: the microphone is not available to the interviewer.");
    }

    const ctx = new AudioContext();
    const analyser = ctx.createAnalyser();
    analyser.fftSize = 1024;
    ctx.createMediaStreamSource(mic).connect(analyser);
    const samples = new Float32Array(analyser.fftSize);
    const level = () => {
      analyser.getFloatTimeDomainData(samples);
      let sum = 0;
      for (const v of samples) sum += v * v;
      return Math.sqrt(sum / samples.length);
    };

    // The call's background level, so "speaking" means louder than the room.
    const noiseFloor = async () => {
      let total = 0;
      for (let i = 0; i < 10; i++) {
        total += level();
        await sleep(80);
      }
      return Math.max(0.012, (total / 10) * 2.5);
    };

    let started: Started;
    let session: Session;
    try {
      started = (await apiJson<{ data: Started }>(`${base}/start`, { method: "POST" })).data;
      setInfo(started);
      session = (await apiJson<{ data: Session }>(`${base}/mocks/${started.mock_id}`)).data;
    } catch (err) {
      return fail(err instanceof ApiError ? (err.firstError ?? err.message) : "This interview could not be started.");
    }
    const mockPath = `${base}/mocks/${started.mock_id}`;

    const say = async (text: string) => {
      setPhase("speaking");
      setCaption(text);
      try {
        const blob = await apiPostBlob(`${mockPath}/speak`, { text });
        if (blob && blob.size > 0) {
          const url = URL.createObjectURL(blob);
          const audio = new Audio(url);
          await new Promise<void>((resolve) => {
            audio.onended = () => resolve();
            audio.onerror = () => resolve();
            void audio.play().catch(() => resolve());
          });
          URL.revokeObjectURL(url);
          return;
        }
      } catch {
        // fall through to the browser voice
      }
      await new Promise<void>((resolve) => {
        const u = new SpeechSynthesisUtterance(text);
        u.lang = "en-IN";
        u.onend = () => resolve();
        u.onerror = () => resolve();
        window.speechSynthesis.speak(u);
      });
    };

    /** Waits for someone to start talking; true if they did within `ms`. */
    const heardWithin = async (ms: number, threshold: number) => {
      const until = Date.now() + ms;
      let loud = 0;
      while (Date.now() < until) {
        loud = level() > threshold ? loud + 1 : 0;
        if (loud >= 3) return true;
        await sleep(100);
      }
      return false;
    };

    /** Records one spoken answer: from the moment they start until they stop. Null when they never start. */
    const recordAnswer = async (): Promise<Blob | null> => {
      setPhase("listening");
      const threshold = await noiseFloor();
      const mime = ["audio/webm;codecs=opus", "audio/webm", "audio/ogg"].find((t) => MediaRecorder.isTypeSupported(t));
      const recorder = new MediaRecorder(mic, mime ? { mimeType: mime } : undefined);
      const chunks: Blob[] = [];
      recorder.ondataavailable = (e) => { if (e.data.size > 0) chunks.push(e.data); };
      const stopped = new Promise<void>((resolve) => { recorder.onstop = () => resolve(); });
      recorder.start(250);

      if (!(await heardWithin(NO_ANSWER_MS, threshold))) {
        recorder.stop();
        await stopped;
        return null;
      }

      const began = Date.now();
      let lastVoice = Date.now();
      while (Date.now() - began < MAX_ANSWER_MS) {
        if (level() > threshold) lastVoice = Date.now();
        if (Date.now() - lastVoice > END_SILENCE_MS && Date.now() - began > MIN_ANSWER_MS) break;
        await sleep(100);
      }
      recorder.stop();
      await stopped;

      return new Blob(chunks, { type: recorder.mimeType || "audio/webm" });
    };

    const transcribe = async (clip: Blob) => {
      const form = new FormData();
      form.append("audio", clip, clip.type.includes("ogg") ? "answer.ogg" : "answer.webm");
      const r = await apiJson<{ data: { text: string } }>(`${mockPath}/transcribe`, { method: "POST", body: form });
      return r.data.text.trim();
    };

    const currentQuestion = (s: Session) => [...s.turns].reverse().find((t) => t.role === "interviewer")?.body ?? "";
    const name = started.first_name;

    // 1. Wait for the candidate to join and say hello (skipped when resuming mid-interview).
    const resumed = session.turns.some((t) => t.role === "candidate");
    if (!resumed) {
      setPhase("waiting");
      let joined = false;
      for (let round = 0; round < GREETING_ROUNDS && !joined; round++) {
        await say(
          `Hi ${name}, I am the BrowseJobs AI interviewer, here for your ${started.round} interview for the ${started.role ?? "open"} role` +
            (started.company ? ` at ${started.company}` : "") +
            ". When you can hear me, please say hello.",
        );
        setPhase("waiting");
        joined = await heardWithin(60_000, await noiseFloor());
      }
      if (!joined) {
        setPhase("finished");
        setCaption("The candidate did not join. This interview has not started.");
        return;
      }
      await sleep(1500);
      await say(
        `Great to meet you, ${name}. I will ask you about ${session.max_questions} questions. Take your time with each answer, and simply pause when you have finished. Here is the first question.`,
      );
    }

    // 2. The interview: ask, listen, transcribe, answer — until the round is complete.
    let nudges = 0;
    let misses = 0;
    while (session.status === "in_progress" && !session.ready_to_finish) {
      setProgress({ asked: session.questions_asked, max: session.max_questions });
      await say(currentQuestion(session));

      let text = "";
      for (;;) {
        const clip = await recordAnswer();
        if (clip === null) {
          if (nudges++ < 2) {
            await say("Take your time. Whenever you are ready, please answer the question.");
            continue;
          }
          text = "(The candidate did not answer this question.)";
          break;
        }
        setPhase("thinking");
        try {
          text = await transcribe(clip);
        } catch {
          text = "";
        }
        if (text !== "") break;
        if (misses++ < 2) {
          await say("Sorry, I did not catch that. Could you say it again?");
          continue;
        }
        text = "(The answer could not be heard clearly.)";
        break;
      }
      nudges = 0;
      misses = 0;

      setPhase("thinking");
      setCaption("");
      try {
        session = (await apiJson<{ data: Session }>(`${mockPath}/answer`, { method: "POST", body: JSON.stringify({ answer: text }) })).data;
      } catch {
        await sleep(3000);
        try {
          session = (await apiJson<{ data: Session }>(`${mockPath}/answer`, { method: "POST", body: JSON.stringify({ answer: text }) })).data;
        } catch {
          return fail("The interviewer lost its connection. Your answers so far are saved.");
        }
      }
    }

    // 3. Score it, and say goodbye. The bot leaves the call once it is scored.
    setPhase("thinking");
    void apiJson(`${mockPath}/finish`, { method: "POST" }).catch(() => {});
    await say(`Thank you, ${name}. That is the end of this interview. Your answers have been recorded, and the company will be in touch with you. You can leave the call now.`);
    setPhase("finished");
    setCaption("Interview complete.");
    mic.getTracks().forEach((t) => t.stop());
  }

  function fail(message: string) {
    setProblem(message);
    setPhase("error");
  }

  const status: Record<Phase, string> = {
    connecting: "Connecting…",
    waiting: "Waiting for the candidate",
    speaking: "Speaking",
    listening: "Listening",
    thinking: "Thinking…",
    finished: "Finished",
    error: "Something went wrong",
  };
  const active = phase === "speaking" || phase === "listening";

  return (
    <main className="flex min-h-screen w-full items-center justify-center bg-[#0b1220] px-16 text-white">
      <div className="flex w-full max-w-5xl items-center gap-14">
        <div className="relative grid h-56 w-56 shrink-0 place-items-center">
          <span
            className={`absolute inset-0 rounded-full ${phase === "listening" ? "bg-[#22c55e]/25" : "bg-[#1b6df0]/30"} ${active ? "animate-ping" : ""}`}
            aria-hidden
          />
          <span className="relative grid h-44 w-44 place-items-center rounded-full bg-gradient-to-br from-[#1b6df0] to-[#0e3fa9] text-5xl font-semibold tracking-tight shadow-[0_0_80px_rgba(27,109,240,.45)]">
            AI
          </span>
        </div>

        <div className="min-w-0 flex-1">
          <p className="text-sm font-semibold uppercase tracking-[0.18em] text-white/50">BrowseJobs AI Interviewer</p>
          <p className="mt-2 text-2xl font-semibold">
            {info ? `${info.round} interview · ${info.role ?? ""}${info.company ? ` · ${info.company}` : ""}` : "Interview"}
          </p>
          <p className="mt-8 min-h-[7rem] text-[28px] leading-snug text-white/90">{problem ?? caption}</p>
          <div className="mt-8 flex items-center gap-4 text-base text-white/60">
            <span className={`rounded-full px-4 py-1.5 font-semibold ${phase === "listening" ? "bg-[#22c55e]/20 text-[#86efac]" : phase === "error" ? "bg-[#d64545]/20 text-[#fca5a5]" : "bg-white/10 text-white/80"}`}>
              {status[phase]}
            </span>
            {progress && phase !== "finished" && (
              <span>Question {Math.min(progress.asked, progress.max)} of {progress.max}</span>
            )}
          </div>
        </div>
      </div>
    </main>
  );
}
