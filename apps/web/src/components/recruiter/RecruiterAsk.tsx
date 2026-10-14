"use client";

import { useCallback, useEffect, useRef, useState, type FormEvent } from "react";
import { ApiError, apiJson, apiPostBlob } from "@/lib/api";

/**
 * The AI Recruiter's Ask bar: tell the hiring agent what to do, by typing or
 * out loud ("send the L1 invites", "where are we?", "can we do the interview
 * now"). A question is answered; an instruction comes back as a proposal to
 * confirm with Go ahead / Not now, or by saying "yes". Taurus's own proposals
 * at milestones show up the same way. Replies are spoken (the Voice switch).
 *
 * Voice in: Chrome and Edge listen by themselves. Anywhere else (Firefox,
 * Safari, most phones) the mic is recorded and turned into text on the server.
 * A browser only listens after a tap and a one-time permission, never on its
 * own, so the mic button starts each command.
 */

type Phase = "idle" | "listening" | "thinking" | "speaking";
type Proposal = { id: number; summary: string };
type Reply = { intent: string; reply: string; action: Proposal | null };

type RecognitionLike = {
  lang: string;
  interimResults: boolean;
  continuous: boolean;
  onresult: ((e: { results: ArrayLike<ArrayLike<{ transcript: string }>> }) => void) | null;
  onerror: ((e: { error?: string }) => void) | null;
  onend: (() => void) | null;
  start: () => void;
  stop: () => void;
};

const VOICE_KEY = "bj-recruiter-voice";
const MAX_RECORD_MS = 15_000;

/** Taurus writes WhatsApp-style *bold* and _italic_; the bar shows and speaks plain words. */
function plain(text: string): string {
  return text.replace(/[*_`]/g, "").replace(/\s+/g, " ").trim();
}

function recognitionCtor(): (new () => RecognitionLike) | null {
  if (typeof window === "undefined") return null;
  const w = window as unknown as { SpeechRecognition?: new () => RecognitionLike; webkitSpeechRecognition?: new () => RecognitionLike };
  return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
}

export function RecruiterAsk({ workspaceId, jobId, onChanged }: { workspaceId: number; jobId: number | null; onChanged: () => void }) {
  const base = `/api/v1/employer/workspaces/${workspaceId}`;
  const [phase, setPhase] = useState<Phase>("idle");
  const [question, setQuestion] = useState("");
  const [answer, setAnswer] = useState<string | null>(null);
  const [answerProposal, setAnswerProposal] = useState<Proposal | null>(null);
  const [proposals, setProposals] = useState<Proposal[]>([]);
  const [canAct, setCanAct] = useState(false);
  const [deciding, setDeciding] = useState<number | null>(null);
  const [voiceReplies, setVoiceReplies] = useState(true);
  const [note, setNote] = useState<string | null>(null);
  const audioRef = useRef<HTMLAudioElement | null>(null);
  const recognitionRef = useRef<RecognitionLike | null>(null);
  const recorderRef = useRef<MediaRecorder | null>(null);
  const recordTimer = useRef<number | undefined>(undefined);

  // Spoken replies are on unless the person switched them off here before.
  useEffect(() => {
    try {
      if (localStorage.getItem(VOICE_KEY) === "off") setVoiceReplies(false);
    } catch {
      /* storage unavailable: keep voice on */
    }
  }, []);

  const stopSpeaking = useCallback(() => {
    audioRef.current?.pause();
    audioRef.current = null;
    try {
      window.speechSynthesis?.cancel();
    } catch {
      /* no browser voice */
    }
    setPhase((p) => (p === "speaking" ? "idle" : p));
  }, []);

  const toggleVoice = () => {
    const next = !voiceReplies;
    setVoiceReplies(next);
    try {
      localStorage.setItem(VOICE_KEY, next ? "on" : "off");
    } catch {
      /* storage unavailable */
    }
    if (!next) stopSpeaking();
  };

  // Taurus's own proposals ("send L1 to these 8?") wait here for Go ahead / Not now.
  const loadProposals = useCallback(async () => {
    try {
      const q = jobId ? `?job_id=${jobId}` : "";
      const res = await apiJson<{ data: { can_act: boolean; open: Proposal[] } }>(`${base}/agent/actions${q}`);
      setCanAct(res.data.can_act);
      setProposals(res.data.can_act ? res.data.open.map((a) => ({ id: a.id, summary: plain(a.summary) })) : []);
    } catch {
      /* proposals are extra: the bar still works without them */
    }
  }, [base, jobId]);

  useEffect(() => {
    void loadProposals();
    const id = window.setInterval(() => void loadProposals(), 15_000);
    return () => window.clearInterval(id);
  }, [loadProposals]);

  // Nothing keeps listening or talking after the page is left.
  useEffect(
    () => () => {
      window.clearTimeout(recordTimer.current);
      recognitionRef.current?.stop();
      const recorder = recorderRef.current;
      if (recorder && recorder.state !== "inactive") recorder.stop();
      audioRef.current?.pause();
      try {
        window.speechSynthesis?.cancel();
      } catch {
        /* no browser voice */
      }
    },
    [],
  );

  const speak = useCallback(
    async (text: string) => {
      if (!voiceReplies || !text) return;
      stopSpeaking();
      try {
        const blob = await apiPostBlob(`${base}/speak`, { text });
        if (blob && blob.size > 0) {
          const url = URL.createObjectURL(blob);
          const audio = new Audio(url);
          audioRef.current = audio;
          setPhase("speaking");
          audio.onended = () => {
            setPhase("idle");
            URL.revokeObjectURL(url);
          };
          audio.onerror = () => {
            setPhase("idle");
            URL.revokeObjectURL(url);
          };
          await audio.play();
          return;
        }
      } catch {
        /* fall back to the browser's own voice */
      }
      try {
        const u = new SpeechSynthesisUtterance(text);
        u.lang = "en-IN";
        u.onstart = () => setPhase("speaking");
        u.onend = () => setPhase("idle");
        u.onerror = () => setPhase("idle");
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(u);
      } catch {
        setPhase("idle");
      }
    },
    [base, voiceReplies, stopSpeaking],
  );

  const ask = useCallback(
    async (text: string) => {
      const q = text.trim();
      if (!q) return;
      setPhase("thinking");
      setAnswer(null);
      setAnswerProposal(null);
      setNote(null);
      try {
        const res = await apiJson<{ data: Reply }>(`${base}/agent/command`, {
          method: "POST",
          body: JSON.stringify(jobId ? { text: q, job_id: jobId } : { text: q }),
        });
        const reply = plain(res.data.reply);
        setAnswer(reply);
        setAnswerProposal(res.data.intent === "proposed" && res.data.action ? { id: res.data.action.id, summary: plain(res.data.action.summary) } : null);
        setPhase("idle");
        if (res.data.intent !== "proposed") onChanged();
        void loadProposals();
        void speak(reply);
      } catch (err) {
        setPhase("idle");
        setAnswer(err instanceof ApiError && err.status === 403 ? "You do not have access to ask here." : "I couldn't answer that just now. Try again in a moment.");
      }
    },
    [base, jobId, onChanged, loadProposals, speak],
  );

  // Yes or not now, by button. A spoken or typed "yes" goes through ask() and lands the same way.
  const decide = useCallback(
    async (p: Proposal, yes: boolean) => {
      if (deciding !== null) return;
      setDeciding(p.id);
      try {
        const res = await apiJson<{ data: Reply }>(`${base}/agent/actions/${p.id}/${yes ? "confirm" : "cancel"}`, {
          method: "POST",
          body: JSON.stringify({}),
        });
        const result = plain(res.data.reply);
        setProposals((list) => list.filter((x) => x.id !== p.id));
        setAnswerProposal((current) => (current?.id === p.id ? null : current));
        setAnswer(result);
        if (yes) void speak(result);
        onChanged();
        void loadProposals();
      } catch {
        setAnswer("That didn't go through. Try again.");
      } finally {
        setDeciding(null);
      }
    },
    [base, deciding, loadProposals, onChanged, speak],
  );

  // Browsers without speech recognition: record, send, get the words back.
  const recordForServer = useCallback(async () => {
    if (typeof MediaRecorder === "undefined" || !navigator.mediaDevices?.getUserMedia) {
      setNote("Voice commands need a newer browser. Type your request instead.");
      return;
    }
    let stream: MediaStream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch {
      setNote("The microphone is blocked. Allow it in the address bar, then tap the mic again.");
      return;
    }
    const mime = ["audio/webm;codecs=opus", "audio/webm", "audio/ogg;codecs=opus", "audio/mp4"].find((t) => MediaRecorder.isTypeSupported(t));
    const recorder = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
    const chunks: Blob[] = [];
    recorder.ondataavailable = (e) => {
      if (e.data.size > 0) chunks.push(e.data);
    };
    recorder.onstop = async () => {
      stream.getTracks().forEach((t) => t.stop());
      window.clearTimeout(recordTimer.current);
      recorderRef.current = null;
      const clip = new Blob(chunks, { type: recorder.mimeType || "audio/webm" });
      if (clip.size < 1500) {
        setPhase("idle");
        setNote("I did not hear anything. Tap the mic and speak.");
        return;
      }
      setPhase("thinking");
      try {
        const form = new FormData();
        form.append("audio", clip, `command.${clip.type.includes("mp4") ? "m4a" : clip.type.includes("ogg") ? "ogg" : "webm"}`);
        const res = await apiJson<{ data: { text: string } }>(`${base}/agent/transcribe`, { method: "POST", body: form });
        const heard = res.data.text.trim();
        if (!heard) {
          setPhase("idle");
          setNote("I did not catch any words. Tap the mic and try again.");
          return;
        }
        setQuestion("");
        void ask(heard);
      } catch {
        setPhase("idle");
        setNote("I could not turn that into text. Type your request instead.");
      }
    };
    recorderRef.current = recorder;
    recorder.start();
    setPhase("listening");
    recordTimer.current = window.setTimeout(() => {
      if (recorder.state !== "inactive") recorder.stop();
    }, MAX_RECORD_MS);
  }, [base, ask]);

  const stopListening = () => {
    recognitionRef.current?.stop();
    const recorder = recorderRef.current;
    if (recorder && recorder.state !== "inactive") recorder.stop();
  };

  const listen = () => {
    if (phase === "listening") {
      stopListening();
      return;
    }
    stopSpeaking();
    setNote(null);
    const Ctor = recognitionCtor();
    if (!Ctor) {
      void recordForServer();
      return;
    }
    const rec = new Ctor();
    rec.lang = "en-IN";
    rec.interimResults = true;
    rec.continuous = false;
    let heard = "";
    let failed = false;
    rec.onresult = (e) => {
      let text = "";
      for (let i = 0; i < e.results.length; i++) text += e.results[i][0].transcript;
      heard = text;
      setQuestion(text);
    };
    rec.onerror = (e) => {
      failed = true;
      recognitionRef.current = null;
      setPhase("idle");
      if (e.error === "not-allowed") setNote("The microphone is blocked. Allow it in the address bar, then tap the mic again.");
      else if (e.error === "network" || e.error === "service-not-allowed") void recordForServer();
      else setNote("I did not hear anything. Tap the mic and speak.");
    };
    rec.onend = () => {
      recognitionRef.current = null;
      if (failed) return;
      if (heard.trim()) {
        setQuestion("");
        void ask(heard);
      } else {
        setPhase("idle");
      }
    };
    try {
      rec.start();
      recognitionRef.current = rec;
      setPhase("listening");
    } catch {
      setPhase("idle");
      void recordForServer();
    }
  };

  const onSubmit = (e: FormEvent) => {
    e.preventDefault();
    const q = question;
    setQuestion("");
    void ask(q || "Where are we in hiring?");
  };

  // A confirmation card Taurus raised by itself, apart from the one already shown as the answer.
  const waiting = canAct ? proposals.filter((p) => p.id !== answerProposal?.id).slice(0, 2) : [];
  const busy = phase === "thinking";

  return (
    <div id="ask-bar" data-phase={phase}>
      {waiting.map((p) => (
        <div key={p.id} className="ask-card" role="group" aria-label="Taurus is waiting for your answer">
          <b>Taurus asks:</b> {p.summary}
          <Decide proposal={p} busy={deciding === p.id} onDecide={decide} />
        </div>
      ))}
      {answer ? (
        <div className="ask-answer" role="status">
          {answer}
          {answerProposal && canAct ? <Decide proposal={answerProposal} busy={deciding === answerProposal.id} onDecide={decide} /> : null}
        </div>
      ) : null}
      {note ? (
        <div className="ask-note" role="alert">
          {note}
        </div>
      ) : null}
      <form className="ask-row" onSubmit={onSubmit}>
        <button
          type="button"
          className="ask-mic"
          onClick={listen}
          aria-pressed={phase === "listening"}
          aria-label={phase === "listening" ? "Stop listening and send" : "Speak a command"}
          title={phase === "listening" ? "Tap to send" : "Tap and speak"}
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <rect x="9" y="3" width="6" height="12" rx="3" />
            <path d="M5 11a7 7 0 0 0 14 0M12 18v3" />
          </svg>
        </button>
        <input
          value={question}
          onChange={(e) => setQuestion(e.target.value)}
          placeholder={phase === "listening" ? "Listening… speak now" : busy ? "Thinking…" : "Say or type: “where are we?” · “send the L1 invites”"}
          aria-label="Ask the AI Recruiter"
          maxLength={1000}
          disabled={busy}
        />
        <button type="submit" className="ask-go" disabled={busy}>
          Ask
        </button>
        <button type="button" className="ask-voice" onClick={toggleVoice} aria-pressed={voiceReplies} title="Spoken replies">
          {voiceReplies ? "Voice on" : "Voice off"}
        </button>
      </form>
    </div>
  );
}

/** Go ahead / Not now under something Taurus proposes. */
function Decide({ proposal, busy, onDecide }: { proposal: Proposal; busy: boolean; onDecide: (p: Proposal, yes: boolean) => void }) {
  return (
    <div className="ask-decide">
      <button type="button" className="go" disabled={busy} onClick={() => onDecide(proposal, true)}>
        {busy ? "Working…" : "Go ahead"}
      </button>
      <button type="button" disabled={busy} onClick={() => onDecide(proposal, false)}>
        Not now
      </button>
    </div>
  );
}
