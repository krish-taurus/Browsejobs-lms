"use client";

import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from "react";
import { useReducedMotion } from "framer-motion";
import type { FloorLook, TaurusFloor } from "@/lib/taurus-floor/floor";
import { STATUS_LABEL, istTime, speakable, type ConsoleChat, type ConsoleSource, type ConsoleState, type FloorEffect, type Proposal } from "@/lib/taurus-floor/types";
import "./taurus-console.css";

export const LOOKS: { key: FloorLook; letter: string; name: string; line: string }[] = [
  { key: "obsidian", letter: "A", name: "Obsidian", line: "Glass on black" },
  { key: "titanium", letter: "B", name: "Titanium", line: "Daylight studio" },
  { key: "noir", letter: "C", name: "Noir", line: "After dark" },
];

type SpeechRecognitionLike = {
  lang: string;
  interimResults: boolean;
  onresult: ((e: { results: ArrayLike<ArrayLike<{ transcript: string }>> }) => void) | null;
  onend: (() => void) | null;
  onerror: (() => void) | null;
  start(): void;
};

type Props = {
  source: ConsoleSource;
  mode: "ops" | "recruitment";
  title: string;
  subtitle?: string;
  variant?: "full" | "hero";
  /** Show the Ask Taurus bar (needs source.ask). */
  ask?: boolean;
  askPlaceholder?: string;
  className?: string;
};

const LOOK_KEY = "taurus-look";
const SPEECH_LANG_KEY = "taurus-speech-lang-v2";

/**
 * The language the mic listens for. Taurus answers in whatever language the question was asked in, so this
 * only tells the browser's speech recognition what to expect.
 */
const SPEECH_LANGS = [
  { key: "auto", label: "Auto", hint: "the language you speak or type (English, Hindi, Kannada)", tag: "en-IN" },
  { key: "hi", label: "हिं", hint: "Hindi", tag: "hi-IN" },
  { key: "kn", label: "ಕನ್", hint: "Kannada", tag: "kn-IN" },
] as const;
type SpeechLangKey = (typeof SPEECH_LANGS)[number]["key"];

/** Web addresses in an answer become links you can open. */
function Linked({ text }: { text: string }) {
  return (
    <>
      {text.split(/(https?:\/\/[^\s)]+)/g).map((part, i) =>
        /^https?:\/\//.test(part) ? (
          <a key={i} href={part} target="_blank" rel="noreferrer">
            {part.length > 52 ? `${part.slice(0, 49)}…` : part}
          </a>
        ) : (
          part
        ),
      )}
    </>
  );
}

/** Kannada script has no ElevenLabs voice here; Devanagari and the rest read fine. */
const KANNADA = /[\u0C80-\u0CFF]/;
const DEVANAGARI = /[\u0900-\u097F]/;

/**
 * The Taurus console: the 3D floor (lib/taurus-floor/floor.mjs) plus the HUD
 * around it. The same component runs the public sample demos, the founder's
 * live agent console and an employer's hiring floor — only the data source
 * changes. If WebGL isn't available the HUD still works over a plain
 * background.
 */
export function TaurusConsole({ source, mode, title, subtitle, variant = "full", ask = true, askPlaceholder, className }: Props) {
  const reduce = useReducedMotion() ?? false;
  const stageRef = useRef<HTMLDivElement | null>(null);
  const floorHostRef = useRef<HTMLDivElement | null>(null);
  const floorRef = useRef<TaurusFloor | null>(null);
  const topRef = useRef<HTMLElement | null>(null);
  const [look, setLook] = useState<FloorLook>("obsidian");
  const [titleKey, setTitleKey] = useState(0);
  const [intro, setIntro] = useState(true);
  const [state, setState] = useState<ConsoleState | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [noGl, setNoGl] = useState(false);
  const [toast, setToast] = useState<string | null>(null);
  const [answer, setAnswer] = useState<string | null>(null);
  // The answer is a question back ("…Shall I go ahead?"): it stays until answered.
  const [answerProposal, setAnswerProposal] = useState<Proposal | null>(null);
  const [deciding, setDeciding] = useState<number | null>(null);
  const [heldIds, setHeldIds] = useState<Set<number>>(new Set());
  const [voice, setVoice] = useState<"idle" | "listening" | "thinking" | "speaking">("idle");
  const [question, setQuestion] = useState("");
  const [speechLang, setSpeechLang] = useState<SpeechLangKey>("auto");
  const recorderRef = useRef<MediaRecorder | null>(null);
  // Live conversation: it listens, answers out loud, then listens again, with nothing to tap in between.
  const [live, setLive] = useState(false);
  const liveRef = useRef(false);
  const quietRounds = useRef(0);
  const audioRef = useRef<HTMLAudioElement | null>(null);
  const listenRef = useRef<(() => void) | null>(null);
  const [clock, setClock] = useState("");
  const seenFeed = useRef<Set<string>>(new Set());
  const prevStages = useRef<Record<string, number>>({});
  const [bumped, setBumped] = useState<Set<string>>(new Set());
  const stateRef = useRef<ConsoleState | null>(null);
  // The floor mounts asynchronously, so it reads the look from a ref: a look
  // restored from storage before the import resolves would otherwise be lost
  // (a light HUD over a dark floor).
  const lookRef = useRef(look);
  lookRef.current = look;
  // Height of the top bar, measured on resize rather than read every frame.
  const topH = useRef(90);

  useEffect(() => {
    const el = topRef.current;
    if (!el) return;
    const measure = () => (topH.current = el.offsetHeight);
    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(el);
    return () => ro.disconnect();
  }, []);

  // a stage bump plays once, then clears so the next change can play it again
  useEffect(() => {
    if (!bumped.size) return;
    const t = window.setTimeout(() => setBumped(new Set()), 900);
    return () => window.clearTimeout(t);
  }, [bumped]);

  // remembered look
  useEffect(() => {
    try {
      const saved = localStorage.getItem(LOOK_KEY) as FloorLook | null;
      if (saved && LOOKS.some((l) => l.key === saved)) setLook(saved);
    } catch {
      /* storage unavailable: keep the default */
    }
    try {
      const lang = localStorage.getItem(SPEECH_LANG_KEY);
      if (SPEECH_LANGS.some((l) => l.key === lang)) setSpeechLang(lang as SpeechLangKey);
    } catch {
      /* storage unavailable: stay on English */
    }
  }, []);

  const cycleSpeechLang = useCallback(() => {
    setSpeechLang((current) => {
      const next = SPEECH_LANGS[(SPEECH_LANGS.findIndex((l) => l.key === current) + 1) % SPEECH_LANGS.length].key;
      try {
        localStorage.setItem(SPEECH_LANG_KEY, next);
      } catch {
        /* not remembered */
      }
      return next;
    });
  }, []);

  const flash = useCallback((msg: string) => {
    setToast(msg);
    window.setTimeout(() => setToast((t) => (t === msg ? null : t)), 3200);
  }, []);

  const approve = useCallback(
    async (id: string) => {
      if (!source.approve) return;
      try {
        flash(await source.approve(id));
      } catch {
        flash("That approval didn't go through. Try again.");
      }
    },
    [source, flash],
  );
  const approveRef = useRef(approve);
  approveRef.current = approve;

  // mount the 3D floor once
  useEffect(() => {
    let cancelled = false;
    const host = floorHostRef.current;
    if (!host) return;
    import("@/lib/taurus-floor/floor")
      .then(({ createTaurusFloor }) => {
        if (cancelled || !floorHostRef.current) return;
        try {
          floorRef.current = createTaurusFloor(floorHostRef.current, {
            look: lookRef.current,
            reducedMotion: reduce,
            wheelZoom: variant === "full",
            numberZones: mode === "recruitment",
            topSafe: () => topH.current + 130,
            onApprove: source.approve ? (id) => void approveRef.current(id) : undefined,
            onError: () => setNoGl(true),
          });
          const s = stateRef.current;
          if (s) {
            floorRef.current.setZones(s.zones);
            floorRef.current.sync(s.agents);
          }
        } catch {
          setNoGl(true);
        }
      })
      .catch(() => setNoGl(true));
    const t = window.setTimeout(() => setIntro(false), 3300);
    return () => {
      cancelled = true;
      window.clearTimeout(t);
      floorRef.current?.dispose();
      floorRef.current = null;
    };
    // The renderer is created once per mount; look/data are pushed in below.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // data
  useEffect(() => {
    let zonesKey = "";
    const stop = source.start({
      state: (s) => {
        stateRef.current = s;
        setState(s);
        const f = floorRef.current;
        if (f) {
          const k = s.zones.map((z) => z.key).join("|");
          if (k !== zonesKey) {
            zonesKey = k;
            f.setZones(s.zones);
          }
          f.sync(s.agents);
        }
        if (s.stages) {
          const up = new Set<string>();
          s.stages.forEach((st) => {
            const before = prevStages.current[st.key];
            if (before !== undefined && st.count > before) up.add(st.key);
            prevStages.current[st.key] = st.count;
          });
          if (up.size) setBumped(up);
        }
      },
      effects: (fx: FloorEffect[]) => {
        const f = floorRef.current;
        if (!f) return;
        fx.forEach((e) => {
          if (e.type === "flow") f.flow(e.from, e.to, e.count);
          else if (e.type === "deliver") f.deliver(e.id);
          else if (e.type === "stream") f.stream(e.id, 6);
          else if (e.type === "focus") f.focus(e.id, 7);
        });
      },
      error: setError,
    });
    return stop;
  }, [source]);

  // clock
  useEffect(() => {
    const tick = () => setClock(istTime());
    tick();
    const t = window.setInterval(tick, 15000);
    return () => window.clearInterval(t);
  }, []);

  const chooseLook = (next: FloorLook) => {
    if (next === look) return;
    setLook(next);
    setTitleKey((k) => k + 1);
    setIntro(true);
    window.setTimeout(() => setIntro(false), 3300);
    floorRef.current?.setLook(next);
    try {
      localStorage.setItem(LOOK_KEY, next);
    } catch {
      /* ignore */
    }
  };
  useEffect(() => {
    floorRef.current?.setLook(look, false);
  }, [look]);

  // ask Taurus: typed or spoken
  const speak = useCallback(
    (text: string, audio?: Blob | null) =>
      new Promise<void>((resolve) => {
        const kannada = KANNADA.test(text);
        const browserVoice = () => {
          try {
            const u = new SpeechSynthesisUtterance(text);
            if (kannada) u.lang = "kn-IN";
            else if (DEVANAGARI.test(text)) u.lang = "hi-IN";
            u.rate = 1;
            u.pitch = 0.9;
            u.onstart = () => setVoice("speaking");
            u.onend = u.onerror = () => {
              setVoice("idle");
              resolve();
            };
            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(u);
          } catch {
            setVoice("idle");
            resolve();
          }
        };
        if (audio && audio.size > 0 && !kannada) {
          const url = URL.createObjectURL(audio);
          const a = new Audio(url);
          audioRef.current = a;
          let finished = false;
          const done = () => {
            if (finished) return;
            finished = true;
            setVoice("idle");
            URL.revokeObjectURL(url);
            if (audioRef.current === a) audioRef.current = null;
            resolve();
          };
          a.onended = a.onerror = done;
          setVoice("speaking");
          a.play().catch(() => {
            if (finished) return;
            finished = true;
            URL.revokeObjectURL(url);
            if (audioRef.current === a) audioRef.current = null;
            browserVoice();
          });
          return;
        }
        browserVoice();
      }),
    [],
  );

  // Leave live conversation: stop listening and stop talking.
  const stopLive = useCallback(
    (why?: string) => {
      liveRef.current = false;
      setLive(false);
      quietRounds.current = 0;
      if (recorderRef.current?.state === "recording") recorderRef.current.stop();
      audioRef.current?.pause();
      audioRef.current = null;
      try {
        window.speechSynthesis?.cancel();
      } catch {
        /* nothing speaking */
      }
      setVoice("idle");
      if (why) flash(why);
    },
    [flash],
  );
  useEffect(() => () => stopLive(), [stopLive]);

  const runAsk = useCallback(
    async (q: string) => {
      if (!source.ask || !q.trim()) return;
      setVoice("thinking");
      setAnswer(null);
      setAnswerProposal(null);
      try {
        const res = await source.ask(q.trim(), speechLang === "auto" ? undefined : speechLang);
        setAnswer(res.answer);
        setAnswerProposal(res.proposal ?? null);
        setVoice("idle");
        await speak(speakable(res.answer), res.audio);
        // In a live conversation the mic opens again once Taurus has finished speaking.
        if (liveRef.current) window.setTimeout(() => liveRef.current && listenRef.current?.(), 350);
      } catch {
        setVoice("idle");
        setAnswer("I couldn't answer that just now. Try again in a moment.");
      }
    },
    [source, speak, speechLang],
  );

  const onSubmit = (e: FormEvent) => {
    e.preventDefault();
    if (recorderRef.current) {
      recorderRef.current.stop();
      return;
    }
    const q = question;
    setQuestion("");
    void runAsk(q || (mode === "recruitment" ? "Where are we in hiring?" : "What needs me right now?"));
  };

  // Firefox, Safari and some phones have no speech recognition: record, and the server turns it into text
  // (it hears Hindi and Kannada too). Tap the orb again to send; it also stops by itself after 15 seconds.
  const listenByRecording = useCallback(async () => {
    if (recorderRef.current) {
      recorderRef.current.stop();
      return;
    }
    const transcribe = source.transcribe;
    if (!transcribe || typeof MediaRecorder === "undefined" || !navigator.mediaDevices?.getUserMedia) {
      flash("This browser can't listen. Type your question instead.");
      return;
    }
    let stream: MediaStream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch {
      flash("Microphone blocked. Allow it for this site, or type your question.");
      return;
    }
    const mimeType = MediaRecorder.isTypeSupported("audio/webm") ? "audio/webm" : MediaRecorder.isTypeSupported("audio/mp4") ? "audio/mp4" : "";
    const recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
    const chunks: Blob[] = [];
    recorderRef.current = recorder;
    const stopTimer = window.setTimeout(() => {
      if (recorder.state === "recording") recorder.stop();
    }, 15000);
    recorder.ondataavailable = (e) => {
      if (e.data.size > 0) chunks.push(e.data);
    };
    // Send it once they have spoken and then gone quiet for a moment, so there is nothing to tap.
    let watch = 0;
    let audioCtx: AudioContext | null = null;
    try {
      const Ctx = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
      if (Ctx) {
        audioCtx = new Ctx();
        const analyser = audioCtx.createAnalyser();
        analyser.fftSize = 1024;
        audioCtx.createMediaStreamSource(stream).connect(analyser);
        const samples = new Uint8Array(analyser.fftSize);
        const began = performance.now();
        let spoke = false;
        let quietSince = began;
        watch = window.setInterval(() => {
          analyser.getByteTimeDomainData(samples);
          let peak = 0;
          for (const v of samples) peak = Math.max(peak, Math.abs(v - 128));
          const now = performance.now();
          if (peak > 14) {
            spoke = true;
            quietSince = now;
          } else if (recorder.state === "recording" && ((spoke && now - quietSince > 1600) || (!spoke && now - began > 8000))) {
            recorder.stop();
          }
        }, 100);
      }
    } catch {
      /* no silence detection here: tap the orb or Ask to send */
    }
    recorder.onstop = async () => {
      window.clearTimeout(stopTimer);
      window.clearInterval(watch);
      void audioCtx?.close();
      stream.getTracks().forEach((t) => t.stop());
      recorderRef.current = null;
      const clip = new Blob(chunks, { type: recorder.mimeType || "audio/webm" });
      const missed = (message: string) => {
        setVoice("idle");
        if (!liveRef.current) {
          flash(message);
          return;
        }
        quietRounds.current += 1;
        if (quietRounds.current >= 2) stopLive("I stopped listening. Tap the orb to talk again.");
        else window.setTimeout(() => liveRef.current && listenRef.current?.(), 300);
      };
      if (clip.size < 800) {
        missed("I didn't catch that. Tap the orb and try again.");
        return;
      }
      setVoice("thinking");
      try {
        const heard = await transcribe(clip);
        if (!heard || /^\[[^\]]*\]$/.test(heard)) {
          missed("I didn't catch that. Tap the orb and try again.");
          return;
        }
        quietRounds.current = 0;
        void runAsk(heard);
      } catch {
        if (liveRef.current) stopLive("Couldn't turn that into text. Type your question instead.");
        else {
          setVoice("idle");
          flash("Couldn't turn that into text. Type your question instead.");
        }
      }
    };
    recorder.start();
    setVoice("listening");
  }, [source, flash, runAsk, stopLive]);
  useEffect(() => {
    listenRef.current = () => void listenByRecording();
  }, [listenByRecording]);

  // One tap starts a live conversation; tap again to end it.
  const toggleLive = useCallback(() => {
    if (liveRef.current) {
      stopLive();
      return;
    }
    liveRef.current = true;
    quietRounds.current = 0;
    setLive(true);
    void listenByRecording();
  }, [listenByRecording, stopLive]);

  const listen = () => {
    const w = window as unknown as { SpeechRecognition?: new () => SpeechRecognitionLike; webkitSpeechRecognition?: new () => SpeechRecognitionLike };
    const Ctor = w.SpeechRecognition ?? w.webkitSpeechRecognition;
    // The server transcribes whatever language is spoken, so prefer it over the browser's own recogniser,
    // which only listens for the one language it is set to.
    if (source.transcribe && typeof MediaRecorder !== "undefined" && "mediaDevices" in navigator) {
      toggleLive();
      return;
    }
    if (!Ctor) {
      void runAsk(question || (mode === "recruitment" ? "Where are we in hiring?" : "What needs me right now?"));
      return;
    }
    const rec = new Ctor();
    rec.lang = SPEECH_LANGS.find((l) => l.key === speechLang)?.tag ?? "en-IN";
    rec.interimResults = false;
    let heard = "";
    setVoice("listening");
    rec.onresult = (e) => {
      heard = e.results[0]?.[0]?.transcript ?? "";
      setQuestion(heard);
    };
    rec.onerror = () => setVoice("idle");
    rec.onend = () => {
      if (heard) {
        setQuestion("");
        void runAsk(heard);
      } else setVoice("idle");
    };
    try {
      rec.start();
    } catch {
      setVoice("idle");
    }
  };

  useEffect(() => {
    if (!answer || answerProposal) return;
    // long answers (a list of people, a role draft) stay up long enough to read
    const t = window.setTimeout(() => setAnswer(null), Math.min(60000, Math.max(14000, answer.length * 90)));
    return () => window.clearTimeout(t);
  }, [answer, answerProposal]);

  // Yes or not now, by button. A spoken or typed "yes" goes through Ask and lands the same way.
  const decide = useCallback(
    async (p: Proposal, yes: boolean) => {
      if (!source.decide || deciding !== null) return;
      setDeciding(p.id);
      try {
        const result = await source.decide(p.id, yes);
        setHeldIds((ids) => new Set(ids).add(p.id));
        if (answerProposal?.id === p.id) {
          setAnswerProposal(null);
          setAnswer(result);
        } else flash(result);
        if (yes) void speak(result);
      } catch {
        flash("That didn't go through. Try again.");
      } finally {
        setDeciding(null);
      }
    },
    [source, deciding, answerProposal, flash, speak],
  );

  const feed = useMemo(() => state?.feed ?? [], [state]);
  const freshIds = useMemo(() => {
    const fresh = new Set<string>();
    feed.forEach((f) => {
      if (!seenFeed.current.has(f.id)) {
        if (seenFeed.current.size) fresh.add(f.id);
        seenFeed.current.add(f.id);
      }
    });
    return fresh;
  }, [feed]);

  const lookMeta = LOOKS.find((l) => l.key === look)!;
  // Milestone proposals, minus the one already on screen as the answer and any just decided.
  const asks = (state?.proposals ?? []).filter((p) => p.id !== answerProposal?.id && !heldIds.has(p.id));
  const showAsk = ask && !!source.ask && !state?.chat;

  return (
    <div
      ref={stageRef}
      className={`tc ${intro && !reduce ? "tc-intro" : ""} ${className ?? ""}`}
      data-look={look}
      data-variant={variant}
      data-mode={mode}
      data-voice={voice}
    >
      <div ref={floorHostRef} className="absolute inset-0" />
      {noGl && <div className="tc-nogl">The 3D floor needs WebGL. Everything else below still updates live.</div>}
      <div className="tc-fx tc-grain" aria-hidden="true" />
      <div className="tc-fx tc-vignette" aria-hidden="true" />
      <div className="tc-lb top" aria-hidden="true" />
      <div className="tc-lb bot" aria-hidden="true" />

      <header className="tc-top" ref={topRef}>
        <div className="tc-mark">
          <svg viewBox="0 0 40 40" aria-hidden="true" fill="none" stroke="currentColor">
            <circle cx="20" cy="20" r="18" strokeWidth="1.3" opacity=".9" />
            <circle cx="20" cy="20" r="13" strokeWidth="1" strokeDasharray="2.5 2.5" opacity=".55" />
            <path d="M12.5 14h15L20 27.5z" strokeWidth="1.8" strokeLinejoin="round" />
            <circle cx="20" cy="18.2" r="1.7" fill="currentColor" stroke="none" />
          </svg>
          <div className="min-w-0">
            <b>{title}</b>
            {subtitle && <small>{subtitle}</small>}
          </div>
        </div>
        <div className="tc-kpis" role="list" aria-label="Today">
          {(state?.kpis ?? []).map((k) => (
            <div key={k.key} role="listitem" className={`tc-kpi tc-panel ${k.hot ? "hot" : ""}`}>
              <span className="l">{k.label}</span>
              <span className="v">
                {k.value}
                {k.note && <em>{k.note}</em>}
              </span>
            </div>
          ))}
        </div>
        <div className="tc-meta">
          <time>{clock} IST</time>
          {state?.sample ? <span className="tc-sample">SAMPLE DATA</span> : <span>Live</span>}
        </div>
      </header>

      {state && state.side.length > 0 && (
        <aside className="tc-side tc-now tc-panel" aria-label={state.sideTitle}>
          <h3>
            {state.sideTitle} <span>{state.side.length}</span>
          </h3>
          <ul>
            {state.side.map((s) => (
              <li key={s.id}>
                <div className="row">
                  <b>
                    <span className="tc-chip" data-s={s.status} aria-label={STATUS_LABEL[s.status]} />
                    {s.title}
                  </b>
                  {typeof s.progress === "number" && <span className="pct">{Math.round(s.progress * 100)}%</span>}
                </div>
                <p>{s.subtitle}</p>
                {typeof s.progress === "number" && (
                  <div className="tc-bar" data-s={s.status}>
                    <i style={{ width: `${Math.round(s.progress * 100)}%` }} />
                  </div>
                )}
                {s.metrics && (
                  <div className="tc-metrics">
                    {s.metrics.map((m) => (
                      <span key={m.label}>
                        {m.label} <b>{m.value}</b>
                      </span>
                    ))}
                  </div>
                )}
              </li>
            ))}
          </ul>
        </aside>
      )}

      <aside className="tc-side tc-feed tc-panel" aria-label="Live updates">
        <h3>
          Live <span>IST</span>
        </h3>
        <ol aria-live="polite">
          {feed.map((f) => (
            <li key={f.id} className={freshIds.has(f.id) && !reduce ? "tc-new" : undefined}>
              <time>{istTime(f.at)}</time>
              <div>
                <div className="t">
                  <b>{f.agent}</b> {f.text}
                </div>
                <span className="tc-chip" data-s={f.status}>
                  {STATUS_LABEL[f.status]}
                </span>
              </div>
            </li>
          ))}
          {!feed.length && (
            <li>
              <time>—</time>
              <div className="t">Waiting for the first update from your agents.</div>
            </li>
          )}
        </ol>
      </aside>

      {state?.caption && (
        <div className="tc-caption" key={`${state.caption.step}-${state.caption.title}`} role="status">
          <small>
            {state.ended ? "The whole story" : `Step ${state.caption.step} of ${state.caption.total}`} · {state.caption.title}
          </small>
          <p>{state.caption.text}</p>
        </div>
      )}

      {state?.chat && <PhonePanel chat={state.chat} onReply={(t) => source.reply?.(t)} />}

      {state?.stages && (
        <div className="tc-stages tc-panel" role="list" aria-label="Hiring stages">
          {state.stages.map((s) => (
            <div key={s.key} role="listitem" className={`tc-stage ${bumped.has(s.key) ? "bump" : ""}`}>
              <span className="l">{s.label}</span>
              <span className="v">{s.count}</span>
              {s.sub && <span className="s">{s.sub}</span>}
            </div>
          ))}
        </div>
      )}

      {showAsk && (
        <form className="tc-askbar tc-panel" onSubmit={onSubmit}>
          <button type="button" className="tc-orb" data-live={live ? "on" : "off"} onClick={listen} aria-label={live ? "End the live conversation" : "Start a live conversation with Taurus"} title={live ? "Live. Tap to stop" : "Tap to talk. It listens and answers on its own"} />
          <button
            type="button"
            className="tc-lang"
            onClick={cycleSpeechLang}
            title={`Answers in ${SPEECH_LANGS.find((l) => l.key === speechLang)?.hint}. Tap to change.`}
            aria-label={`Answer language: ${SPEECH_LANGS.find((l) => l.key === speechLang)?.hint}. Tap to change.`}
          >
            {SPEECH_LANGS.find((l) => l.key === speechLang)?.label}
          </button>
          <label className="sr-only" htmlFor={`tc-ask-${mode}-${variant}`}>
            Ask Taurus
          </label>
          <input
            id={`tc-ask-${mode}-${variant}`}
            value={question}
            onChange={(e) => setQuestion(e.target.value)}
            placeholder={live ? (voice === "speaking" ? "Taurus is speaking…" : voice === "thinking" ? "Thinking…" : "Live: just talk. Tap the orb to stop") : voice === "listening" ? (source.transcribe ? "Listening… speak, then pause" : "Listening…") : voice === "thinking" ? "Thinking…" : askPlaceholder ?? "Ask Taurus: what needs me right now?"}
            autoComplete="off"
          />
          <span className="tc-wave" aria-hidden="true">
            <i />
            <i />
            <i />
            <i />
          </span>
          <button type="submit" className="tc-btn primary">
            Ask
          </button>
        </form>
      )}
      {answer && (
        <div className="tc-answer tc-panel" role="status">
          <small>TAURUS · {istTime()} IST</small>
          <Linked text={answer} />
          {answerProposal && source.decide && (
            <ProposalButtons proposal={answerProposal} busy={deciding === answerProposal.id} onDecide={decide} />
          )}
        </div>
      )}

      {asks.length > 0 && source.decide && !answerProposal && (
        <aside className="tc-propose tc-panel" aria-label="Taurus is asking">
          <small>
            TAURUS ASKS{asks.length > 1 ? ` · 1 of ${asks.length}` : ""}
          </small>
          <p>{asks[0].summary}</p>
          <ProposalButtons proposal={asks[0]} busy={deciding === asks[0].id} onDecide={decide} />
        </aside>
      )}

      <nav className="tc-looks tc-panel" aria-label="Visual style">
        {LOOKS.map((l) => (
          <button key={l.key} type="button" aria-pressed={look === l.key} onClick={() => chooseLook(l.key)}>
            <span>{l.letter}</span>
            {l.name}
          </button>
        ))}
      </nav>

      {titleKey > 0 && !reduce && (
        <div className="tc-title" key={titleKey} aria-hidden="true">
          <div>
            <small>Look {lookMeta.letter}</small>
            <h2>{lookMeta.name}</h2>
            <p>{lookMeta.line}</p>
          </div>
        </div>
      )}
      {toast && (
        <div className="tc-toast tc-panel" role="status">
          {toast}
        </div>
      )}
      {error && <div className="tc-error" role="alert">{error}</div>}
    </div>
  );
}

/** Go ahead / Not now under something Taurus proposes. Saying "yes" to Ask Taurus does the same. */
function ProposalButtons({ proposal, busy, onDecide }: { proposal: Proposal; busy: boolean; onDecide: (p: Proposal, yes: boolean) => void }) {
  return (
    <div className="tc-decide">
      <button type="button" className="tc-btn primary" disabled={busy} onClick={() => onDecide(proposal, true)}>
        {busy ? "Working…" : "Go ahead"}
      </button>
      <button type="button" className="tc-btn" disabled={busy} onClick={() => onDecide(proposal, false)}>
        Not now
      </button>
    </div>
  );
}

/** WhatsApp-style chat between the employer and Taurus (story demos). */
function PhonePanel({ chat, onReply }: { chat: ConsoleChat; onReply: (text: string) => void }) {
  const bodyRef = useRef<HTMLDivElement | null>(null);
  useEffect(() => {
    const el = bodyRef.current;
    if (el) el.scrollTo({ top: el.scrollHeight, behavior: "smooth" });
  }, [chat.messages.length, chat.typing, chat.replies.length]);

  return (
    <aside className="tc-phone" aria-label="WhatsApp conversation with Taurus">
      <header className="tc-phone-head">
        <span className="tc-phone-avatar" aria-hidden="true">
          T
        </span>
        <div>
          <b>{chat.title}</b>
          <small>{chat.typing ? "typing…" : "online"}</small>
        </div>
      </header>
      <div className="tc-phone-body" ref={bodyRef} aria-live="polite">
        {chat.messages.map((m) => (
          <div key={m.id} className={`tc-bubble ${m.from === "employer" ? "me" : "them"}`}>
            {m.attachment && (
              <span className="tc-file">
                <i aria-hidden="true">PDF</i>
                <span>
                  <b>{m.attachment.name}</b>
                  <small>{m.attachment.meta}</small>
                </span>
              </span>
            )}
            <span>{m.text}</span>
            <time>{istTime(m.at)}</time>
          </div>
        ))}
        {chat.typing && (
          <div className="tc-bubble them tc-typing" aria-label="Taurus is typing">
            <i />
            <i />
            <i />
          </div>
        )}
      </div>
      {chat.replies.length > 0 && (
        <div className="tc-replies">
          {chat.replies.map((r, i) => (
            <button key={r} type="button" onClick={() => onReply(r)} className={i === 0 ? "first" : undefined}>
              {r}
              {i === 0 && chat.autoIn !== undefined && <em>{chat.autoIn}s</em>}
            </button>
          ))}
        </div>
      )}
    </aside>
  );
}
