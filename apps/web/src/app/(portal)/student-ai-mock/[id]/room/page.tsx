"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { use, useCallback, useEffect, useRef, useState, type ReactNode } from "react";
import { apiJson, apiPostBlob } from "@/lib/api";
import { AiAvatar, type AiAvatarPhase } from "@/components/portal/AiAvatar";

// Paused proctoring checks (Sept 2026): multi-face was firing on candidates
// sitting alone — a background poster, a reflection, a mirror behind them —
// closing genuine attempts. Gaze-down/gaze-side share the same eye-tracking
// model and get paused alongside it while that's investigated. Left in place
// on purpose, not deleted: flip any of these back to `false` to re-enable
// once the false-positive rate is understood. Face-presence ("can't see your
// face") and every non-camera check (tab switch, window blur, paste) are
// untouched and still fully active.
const PAUSED_PROCTORING_CHECKS = {
  multiFace: true,
  gazeDown: true,
  gazeSide: true,
};

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
 * The spoken question currently playing. Module-scoped rather than a ref so
 * that every place which silences the browser voice — the proctor closing the
 * interview, the mic opening, unmount — can silence this one with a plain call
 * and no hook dependencies. One room is open at a time.
 */
let currentAudio: HTMLAudioElement | null = null;
let sharedAudioContext: AudioContext | null = null;
let recordingMixDestination: MediaStreamAudioDestinationNode | null = null;

function stopAudio(): void {
  currentAudio?.pause();
  currentAudio = null;
}

/** One AudioContext for the whole room — reused by the autoplay unlock, the system-check tests and the recording mix below. */
function getAudioContext(): AudioContext | null {
  try {
    const Ctor = window.AudioContext
      ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!Ctor) return null;
    sharedAudioContext ??= new Ctor();
    return sharedAudioContext;
  } catch {
    return null;
  }
}

/**
 * The interviewer's spoken question and the candidate's own mic, mixed into
 * one audio track — this, not the raw mic alone, is what actually gets
 * recorded. Without it, whoever reviews the recording later hears the
 * candidate answer a question they can never hear being asked. Built once
 * per room and reused by every spoken question afterwards; the mic track
 * connects in only once (a second call would just add nothing new).
 */
function getRecordingMix(micStream: MediaStream): MediaStreamAudioDestinationNode | null {
  const ctx = getAudioContext();
  if (ctx === null) return null;

  if (recordingMixDestination === null) {
    recordingMixDestination = ctx.createMediaStreamDestination();
    const micTrack = micStream.getAudioTracks()[0];
    if (micTrack) {
      // Deliberately not also connected to ctx.destination — that would
      // echo the candidate's own mic back through their own speakers.
      ctx.createMediaStreamSource(new MediaStream([micTrack])).connect(recordingMixDestination);
    }
  }

  return recordingMixDestination;
}

/**
 * Chromium's autoplay policy blocks `audio.play()` when it happens inside an
 * async chain (a network fetch resolving) — even though the interview really
 * did start from a genuine click a moment earlier, the awaited fetch breaks
 * the browser's "this came directly from a user gesture" trust. Playing a
 * silent WebAudio buffer synchronously, right inside that click, registers
 * this tab as one the visitor has already interacted with for audio, so the
 * real spoken question moments later actually plays instead of failing
 * silently. Waking speechSynthesis the same way covers the browser-voice
 * fallback too. Best-effort only — nothing here should ever block starting
 * the interview if a browser rejects any of it.
 */
function unlockAudio(): void {
  try {
    const ctx = getAudioContext();
    if (ctx) {
      const buffer = ctx.createBuffer(1, 1, 22050);
      const source = ctx.createBufferSource();
      source.buffer = buffer;
      source.connect(ctx.destination);
      source.start(0);
      void ctx.resume();
    }
  } catch {
    // Best-effort — the real speak() call still has its own fallback chain.
  }

  try {
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(new SpeechSynthesisUtterance(""));
  } catch {
    // Best-effort.
  }
}

/** Which pre-flight screen (if any) stands between opening the room and the live call. */
type Step = "checking" | "rules" | "countdown" | "live";

type CheckState = "pending" | "ok" | "failed";

/**
 * The Zoom-style interview room: a full-screen call layout over the same
 * text-mock session — the interviewer tile "speaks" each question (TTS),
 * the mic transcribes the spoken answer, and your webcam self-view keeps it
 * feeling like a real screening call. The AI brain and scorecard are the
 * existing server-side mock endpoints.
 *
 * Four screens run in order before the call itself: a system check (camera,
 * mic, speaker), the recording/conduct rules, a short countdown, then the
 * live split-panel room with a running transcript underneath.
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
  // Brave (and some other Chromium forks) block Google's speech backend by
  // default, so SpeechRecognition silently doesn't exist there — checked
  // once on mount, never mid-interview, so the control bar doesn't swap
  // layouts under someone's hands while they're answering.
  const [voiceSupported] = useState(() => speechRecognitionCtor() !== null);
  const [typedAnswer, setTypedAnswer] = useState("");
  // "Are you still there?" idle check (Sept 2026) — separate from proctoring:
  // this is a check-in, not a violation, and never counts against the
  // warning/close counter. Shown after IDLE_NUDGE_MS with no activity on the
  // current question; if that silence continues to IDLE_AUTO_ADVANCE_MS the
  // question is auto-skipped so a genuinely stuck or disconnected candidate
  // isn't stranded on one question for the rest of the interview.
  const [stillThereNudge, setStillThereNudge] = useState(false);
  // Set the instant a violation closes the interview — drives the "why this
  // closed" modal below instead of a silent auto-redirect, so the reason
  // doesn't flash by in a banner the candidate has no time to read.
  const [closedReason, setClosedReason] = useState<string | null>(null);

  // Pre-flight: system check → rules → countdown → live. Camera/mic are
  // requested the moment the room opens (below) so the checklist reflects
  // real permission state instead of a guess, but nothing proctoring-related
  // (violations, the recording, the first spoken question) starts until the
  // candidate has actually reached "live".
  const [step, setStep] = useState<Step>("checking");
  const [micCheck, setMicCheck] = useState<CheckState>("pending");
  const [micListening, setMicListening] = useState(false);
  const [speakerCheck, setSpeakerCheck] = useState<CheckState>("pending");
  const [speakerPlaying, setSpeakerPlaying] = useState(false);
  const [speakerTested, setSpeakerTested] = useState(false);
  const [countdown, setCountdown] = useState(3);

  const [warningText, setWarningText] = useState<string | null>(null);
  const violationsRef = useRef(0);
  const closingRef = useRef(false);
  // Proctoring grace period: camera warm-up, a permission prompt stealing
  // focus, and the face model still loading are all real events that fire
  // in the first few seconds after the call actually starts — before this
  // existed, one of those plus one slow first face-lock could burn both
  // strikes (they share one counter across every violation type) and close
  // the interview before the candidate had answered a single question.
  // Violations raised in this window are dropped silently, not even counted
  // as the first warning.
  const graceUntilRef = useRef(0);

  const videoRef = useRef<HTMLVideoElement | null>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const recognitionRef = useRef<SpeechRecognitionLike | null>(null);
  const spokenTurnRef = useRef<number | null>(null);
  const committedRef = useRef("");
  const recorderRef = useRef<MediaRecorder | null>(null);
  const recordedChunksRef = useRef<Blob[]>([]);

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

  // Webcam + mic — the self-view is local, but this same stream is also
  // what gets recorded once the call starts (see below), and what the
  // system-check screen tests before that. Requested together so the
  // browser asks for camera and microphone in one prompt.
  // Settles (true) once getUserMedia has resolved one way or the other —
  // distinct from camOn, which only ever turns true. The system-check
  // screen needs both: "still asking" vs "asked and it was denied" render
  // differently even though camOn is false in both cases.
  const [mediaSettled, setMediaSettled] = useState(false);
  useEffect(() => {
    let cancelled = false;
    navigator.mediaDevices?.getUserMedia({ video: true, audio: true })
      .then((stream) => {
        if (cancelled) { stream.getTracks().forEach((t) => t.stop()); return; }
        streamRef.current = stream;
        setCamOn(true);
        setMediaSettled(true);
      })
      .catch(() => { setCamOn(false); setMediaSettled(true); });
    return () => {
      cancelled = true;
      streamRef.current?.getTracks().forEach((t) => t.stop());
    };
  }, []);

  // The <video> element mounts on the system-check screen already (so the
  // candidate can see themselves while the checks run) and stays mounted
  // through every later step. Each step (checking → rules → countdown →
  // live) is a structurally different tree, so the <video> element itself
  // unmounts and a fresh one mounts at every step change — camOn alone
  // isn't enough to re-attach the stream, since it only flips true once and
  // stays true; `step` has to be a dependency too or every step after the
  // first render with a never-attached, blank video element.
  useEffect(() => {
    if (camOn && videoRef.current !== null && streamRef.current !== null) {
      videoRef.current.srcObject = streamRef.current;
    }
  }, [camOn, step]);

  // Records the whole call (webcam + mic + the interviewer's own spoken
  // questions, mixed in via getRecordingMix) from the moment the candidate
  // accepts the rules — the employer's evidence, uploaded when the interview
  // finishes. A recording that fails to start (unsupported browser, no audio
  // track) never blocks the interview itself; it just means no recording,
  // same tolerance the spoken-question fallback already has.
  const pastRules = step === "countdown" || step === "live";
  useEffect(() => {
    if (!pastRules || !camOn || streamRef.current === null || recorderRef.current !== null) return;
    if (typeof MediaRecorder === "undefined") return;

    const mimeType = ["video/webm;codecs=vp8,opus", "video/webm", "video/mp4"]
      .find((t) => MediaRecorder.isTypeSupported(t));
    if (mimeType === undefined) return;

    try {
      const mix = getRecordingMix(streamRef.current);
      const recordedStream = mix
        ? new MediaStream([...streamRef.current.getVideoTracks(), ...mix.stream.getAudioTracks()])
        : streamRef.current; // No mix available — falls back to the plain mic-only stream.

      const recorder = new MediaRecorder(recordedStream, { mimeType });
      recordedChunksRef.current = [];
      recorder.ondataavailable = (e) => { if (e.data.size > 0) recordedChunksRef.current.push(e.data); };
      recorder.start(1000);
      recorderRef.current = recorder;
    } catch {
      recorderRef.current = null;
    }
  }, [pastRules, camOn]);

  /** Stops recording and hands back the finished blob, or null if there is none. */
  const stopRecording = useCallback((): Promise<Blob | null> => {
    const recorder = recorderRef.current;
    if (recorder === null || recorder.state === "inactive") {
      return Promise.resolve(recordedChunksRef.current.length > 0
        ? new Blob(recordedChunksRef.current, { type: recorder?.mimeType || "video/webm" })
        : null);
    }
    return new Promise((resolve) => {
      recorder.onstop = () => {
        resolve(recordedChunksRef.current.length > 0 ? new Blob(recordedChunksRef.current, { type: recorder.mimeType }) : null);
      };
      recorder.stop();
    });
  }, []);

  /**
   * The browser's own robot — the fallback, and what this used to be.
   * Chromium's voice list loads asynchronously and is empty on the very
   * first call in a fresh tab; speaking before it's populated is a common,
   * silent way for this to produce no sound at all. If no "en-IN" voice
   * exists on this device, any available voice still beats staying silent.
   */
  const speakLocally = useCallback((text: string) => {
    window.speechSynthesis.cancel();
    stopAudio();

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = 0.95;
    utterance.onend = () => setSpeaking(false);
    utterance.onerror = () => setSpeaking(false);

    let spoken = false;
    const say = () => {
      if (spoken) return;
      spoken = true;
      const voices = window.speechSynthesis.getVoices();
      const preferred = voices.find((v) => v.lang === "en-IN") ?? voices.find((v) => v.lang.startsWith("en"));
      if (preferred) utterance.voice = preferred;
      setSpeaking(true);
      window.speechSynthesis.speak(utterance);
    };

    if (window.speechSynthesis.getVoices().length > 0) {
      say();
    } else {
      window.speechSynthesis.onvoiceschanged = () => {
        window.speechSynthesis.onvoiceschanged = null;
        say();
      };
      // Some browsers never fire voiceschanged if there is truly only one
      // (default) voice — a timeout guarantees this always ends in either
      // a spoken question or, at worst, the visible caption alone.
      setTimeout(say, 300);
    }
  }, []);

  /**
   * Ask the API for the question in a real voice and play that. Anything at all
   * going wrong — no voice configured, ElevenLabs down, audio blocked — drops
   * back to the browser, because an interview with a robot voice still works
   * and one with no voice does not.
   */
  const speak = useCallback(
    (text: string) => {
      window.speechSynthesis.cancel();
      stopAudio();
      setSpeaking(true);

      apiPostBlob(`/api/v1/me/mocks/${id}/speak`, { text })
        .then((blob) => {
          if (blob === null || blob.size === 0) {
            speakLocally(text);
            return;
          }

          const url = URL.createObjectURL(blob);
          const audio = new Audio(url);
          currentAudio = audio;
          audio.onended = () => {
            setSpeaking(false);
            URL.revokeObjectURL(url);
          };

          // Route this same question into the recording too, alongside the
          // candidate's mic — best-effort, and only possible once the camera
          // stream exists to mix into. A session that never gets this (an
          // older browser, camera denied) still plays the question normally;
          // it just means the eventual recording won't include it.
          try {
            const ctx = getAudioContext();
            if (ctx && streamRef.current) {
              const mix = getRecordingMix(streamRef.current);
              const source = ctx.createMediaElementSource(audio);
              source.connect(ctx.destination); // still audible to the candidate
              if (mix) source.connect(mix); // and captured in the recording
            }
          } catch {
            // Best-effort — audio.play() below still works unmixed.
          }

          audio.play().catch(() => {
            URL.revokeObjectURL(url);
            speakLocally(text);
          });
        })
        .catch(() => speakLocally(text));
    },
    [id, speakLocally],
  );

  // Read each new interviewer question aloud — only once the call is live.
  useEffect(() => {
    if (step === "live" && question !== undefined && spokenTurnRef.current !== question.id) {
      spokenTurnRef.current = question.id;
      speak(question.body);
    }
  }, [step, question, speak]);

  // Proctoring: hiding the tab, looking away from the camera, or a second
  // person stepping into frame are all the same kind of violation — one
  // warning, then the interview closes. Shared so the tab check below and
  // the camera check after it both end the session the same way. `immediate`
  // skips the one-warning grace period entirely — for a signal serious
  // enough (a suspected always-on-top cheating overlay) that a first-offence
  // warning isn't appropriate.
  const raiseViolation = useCallback((message: string, opts?: { immediate?: boolean }) => {
    if (closingRef.current) return;
    if (Date.now() < graceUntilRef.current) return;
    violationsRef.current += 1;
    if (!opts?.immediate && violationsRef.current === 1) {
      setWarningText(message);
      return;
    }
    closingRef.current = true;
    // A modal that stays up, not a banner that flashes by before an
    // auto-redirect — the candidate should actually get to read why this
    // closed, not just see it scroll past on the way out. Every violation
    // message is written as a first-offence warning ("...one more time and
    // this closes") — true when it was a warning, stale by the time it's
    // the reason something already closed, so that clause is trimmed off
    // here rather than rewriting every call site with two message variants.
    setWarningText(null);
    setClosedReason(message.split(/\s*[—-]?\s*one more time/i)[0].trim());
    recognitionRef.current?.stop();
    window.speechSynthesis.cancel();
    stopAudio();
    // Best-effort: the modal and the eventual "back to dashboard" nav don't
    // wait on this. A dropped connection or a slow server response is not a
    // reason to hold the candidate on this screen.
    void apiJson(`/api/v1/me/mocks/${id}/abandon`, { method: "POST" }).catch(() => {});
  }, [id]);

  // Proctoring: hiding the tab (switching tabs/apps, minimising) is a
  // violation. First offence warns; the second closes the interview — the
  // session is marked abandoned server-side and the credit stays spent.
  useEffect(() => {
    if (step !== "live" || session === null || session.status !== "in_progress") return;

    const onVisibility = () => {
      if (!document.hidden || closingRef.current) return;
      raiseViolation("⚠️ You left the interview tab. One more time and this interview closes — unscored, session spent.");
    };

    document.addEventListener("visibilitychange", onVisibility);
    return () => document.removeEventListener("visibilitychange", onVisibility);
  }, [step, session, raiseViolation]);

  // Proctoring: the window losing focus while the tab stays visible — the
  // signature of clicking into a floating always-on-top overlay (an AI
  // answer-assist tool, a second device's remote-control window) rather than
  // genuinely switching away, which the tab-visibility check above already
  // catches. Gated on `!document.hidden` specifically so a real tab switch —
  // blur and hidden firing together — is never counted twice against the
  // same two-strike limit.
  //
  // Softened back to warn-then-close (Aug 2026): the immediate-close variant
  // shipped first, at explicit request, to catch this specifically — but in
  // real use it was closing interviews within 10-25 seconds of starting,
  // before the candidate could answer even the first question, on totally
  // ordinary focus loss (an OS notification, a permission prompt, alt-tab to
  // check something) — not actual overlay-tool use. That false-positive rate
  // was severe enough it was silently burning through students' attempt caps
  // platform-wide, not just an edge case, so this reverts to the same
  // one-warning grace every other violation type already gets.
  useEffect(() => {
    if (step !== "live" || session === null || session.status !== "in_progress") return;

    const onBlur = () => {
      if (document.hidden || closingRef.current) return;
      raiseViolation("⚠️ The interview window lost focus. Keep this window active — one more time and this interview closes.");
    };

    window.addEventListener("blur", onBlur);
    return () => window.removeEventListener("blur", onBlur);
  }, [step, session, raiseViolation]);

  // Proctoring: camera presence and gaze. A tiny on-device face model
  // (Google's MediaPipe, loaded from its CDN — no video frame ever leaves
  // the browser) samples the same webcam stream already showing in the
  // self-view tile. Nobody in frame, more than one face, or eyes pointed
  // down for several checks in a row (looking at a phone in your lap keeps
  // the face on camera, so presence alone misses it — the standardised
  // "eyeLookDown" scores this model already reports catch that even when
  // the head barely moves) are all the same kind of violation as leaving
  // the tab and share its one-warning-then-close counter above. If the
  // model can't load at all (offline, the CDN blocked) this quietly does
  // nothing — tab-visibility proctoring still applies either way, so a
  // flaky network never strands anyone mid-interview.
  useEffect(() => {
    if (step !== "live" || !camOn || session?.status !== "in_progress") return;

    let cancelled = false;
    let timer: ReturnType<typeof setInterval> | null = null;
    let detector: {
      detectForVideo: (video: HTMLVideoElement, timestamp: number) => {
        faceLandmarks: unknown[][];
        faceBlendshapes?: { categories: { categoryName: string; score: number }[] }[];
      };
      close?: () => void;
    } | null = null;
    let missStreak = 0;
    let gazeDownStreak = 0;
    let gazeSideStreak = 0;

    // A plain dynamic import() of a CDN URL would make TypeScript try (and
    // fail) to resolve that module at compile time; routing it through
    // Function keeps the import fully runtime-only.
    const importFromCdn = new Function("specifier", "return import(specifier)") as (specifier: string) => Promise<{
      FilesetResolver: { forVisionTasks: (wasmPath: string) => Promise<unknown> };
      FaceLandmarker: { createFromOptions: (resolver: unknown, options: unknown) => Promise<typeof detector> };
    }>;

    (async () => {
      try {
        const vision = await importFromCdn("https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/vision_bundle.mjs");
        const resolver = await vision.FilesetResolver.forVisionTasks(
          "https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@0.10.14/wasm",
        );
        if (cancelled) return;
        detector = await vision.FaceLandmarker.createFromOptions(resolver, {
          baseOptions: {
            modelAssetPath: "https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task",
            delegate: "GPU",
          },
          runningMode: "VIDEO",
          numFaces: 2,
          outputFaceBlendshapes: true,
        });
        if (cancelled || detector === null) return;

        timer = setInterval(() => {
          const video = videoRef.current;
          if (video === null || video.readyState < 2 || detector === null) return;
          try {
            const result = detector.detectForVideo(video, performance.now());
            const faces = result.faceLandmarks.length;
            if (faces === 0) {
              gazeDownStreak = 0;
              gazeSideStreak = 0;
              missStreak += 1;
              // ~2.5s per check — three misses in a row is 7-8 real seconds
              // out of frame, past a normal shift in your chair. A full
              // head turn away from the camera (checking notes, a second
              // monitor, another person off-screen) also lands here in
              // practice — this model is trained on frontal faces and
              // reliably loses lock well before someone reaches a true
              // profile turn, so "can't see your face" already covers most
              // of what a sustained side-turn looks like to the camera.
              if (missStreak >= 3) {
                missStreak = 0;
                raiseViolation("⚠️ We couldn't see your face on camera. Stay facing the screen — one more time and this interview closes.");
              }
              return;
            }

            missStreak = 0;
            if (faces > 1 && !PAUSED_PROCTORING_CHECKS.multiFace) {
              gazeDownStreak = 0;
              gazeSideStreak = 0;
              raiseViolation("⚠️ More than one face is visible. Take this interview alone — one more time and this interview closes.");
              return;
            }

            // Standardised (ARKit-style) blendshape scores, 0-1, for how far
            // each eye is rotated within the face — this catches a glance
            // down at a phone, or sideways at notes or a second monitor,
            // even when the head itself barely moves, which a presence-only
            // check can't.
            const categories = result.faceBlendshapes?.[0]?.categories ?? [];
            const score = (name: string) => categories.find((c) => c.categoryName === name)?.score ?? 0;
            const lookingDown = (score("eyeLookDownLeft") + score("eyeLookDownRight")) / 2 > 0.55;
            if (lookingDown && !PAUSED_PROCTORING_CHECKS.gazeDown) {
              gazeDownStreak += 1;
              if (gazeDownStreak >= 3) {
                gazeDownStreak = 0;
                raiseViolation("⚠️ Keep your eyes on the screen — no phones during the interview. One more time and this interview closes.");
              }
            } else {
              gazeDownStreak = 0;
            }

            // Gaze to either side: an eye rotating toward its own outer
            // corner and the other eye rotating toward its inner corner,
            // together, is the two-eye signature of looking right or left
            // rather than down — reading notes propped beside the screen or
            // glancing at a second monitor.
            const gazeRight = (score("eyeLookOutLeft") + score("eyeLookInRight")) / 2;
            const gazeLeft = (score("eyeLookOutRight") + score("eyeLookInLeft")) / 2;
            const lookingSide = Math.max(gazeRight, gazeLeft) > 0.5;
            if (lookingSide && !PAUSED_PROCTORING_CHECKS.gazeSide) {
              gazeSideStreak += 1;
              if (gazeSideStreak >= 3) {
                gazeSideStreak = 0;
                raiseViolation("⚠️ Keep your eyes on the screen — no notes or second screens during the interview. One more time and this interview closes.");
              }
            } else {
              gazeSideStreak = 0;
            }
          } catch {
            // A single failed frame read isn't a violation.
          }
        }, 2500);
      } catch {
        // No network access to the CDN, or this browser can't run the WASM
        // model — an enhancement, not a requirement.
      }
    })();

    return () => {
      cancelled = true;
      if (timer !== null) clearInterval(timer);
      detector?.close?.();
    };
  }, [step, camOn, session?.status, raiseViolation]);

  // Leaving the room must release the mic, camera, and speaker — and the
  // shared recording mix, which is module-scoped like currentAudio (same
  // "one room is open at a time" reasoning). Left stale, a second interview
  // opened later in the same tab would find recordingMixDestination already
  // set and skip connecting its own mic into it, silently recording with no
  // candidate audio at all.
  useEffect(() => () => {
    recognitionRef.current?.stop();
    if (typeof window !== "undefined") window.speechSynthesis.cancel();
    stopAudio();
    if (recorderRef.current?.state !== "inactive") recorderRef.current?.stop();
    recordingMixDestination = null;
    sharedAudioContext?.close().catch(() => {});
    sharedAudioContext = null;
  }, []);

  // Countdown: three ticks, then the call goes live. The grace period for
  // proctoring starts here, not at the rules-accept click, so it actually
  // protects the first live seconds instead of mostly burning during the
  // countdown itself.
  useEffect(() => {
    if (step !== "countdown") return;
    setCountdown(3);
    const tick = setInterval(() => {
      setCountdown((c) => {
        if (c <= 1) {
          clearInterval(tick);
          graceUntilRef.current = Date.now() + 8000;
          setStep("live");
          return 0;
        }
        return c - 1;
      });
    }, 1000);
    return () => clearInterval(tick);
  }, [step]);

  /** System check: a few seconds of silence proves nothing is obviously broken, without inventing a fake Mbps number. */
  const [connectionCheck, setConnectionCheck] = useState<CheckState>("pending");
  useEffect(() => {
    if (step !== "checking") return;
    const t = setTimeout(() => setConnectionCheck(navigator.onLine ? "ok" : "failed"), 900);
    return () => clearTimeout(t);
  }, [step]);

  /** "Speak now" — a short on-device volume check, not a transcript; passes the moment the mic actually picks up sound. */
  const testMic = useCallback(() => {
    const track = streamRef.current?.getAudioTracks()[0];
    const ctx = getAudioContext();
    if (!track || !ctx) { setMicCheck("failed"); return; }

    setMicListening(true);
    setMicCheck("pending");
    const source = ctx.createMediaStreamSource(new MediaStream([track]));
    const analyser = ctx.createAnalyser();
    analyser.fftSize = 512;
    source.connect(analyser);
    const data = new Uint8Array(analyser.frequencyBinCount);
    let heard = false;
    const startedAt = Date.now();

    const poll = () => {
      analyser.getByteTimeDomainData(data);
      let peak = 0;
      for (const v of data) peak = Math.max(peak, Math.abs(v - 128));
      if (peak > 12) heard = true;

      if (Date.now() - startedAt > 3500) {
        source.disconnect();
        setMicListening(false);
        setMicCheck(heard ? "ok" : "failed");
        return;
      }
      requestAnimationFrame(poll);
    };
    poll();
  }, []);

  /** A short tone through the speakers — the candidate confirms with Yes/Retry, same as the reference flow. */
  const testSpeaker = useCallback(() => {
    const ctx = getAudioContext();
    setSpeakerPlaying(true);
    setSpeakerTested(true);
    if (!ctx) { setSpeakerPlaying(false); setSpeakerCheck("pending"); return; }
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.frequency.value = 880;
    gain.gain.value = 0.18;
    osc.connect(gain).connect(ctx.destination);
    osc.start();
    setTimeout(() => {
      osc.stop();
      setSpeakerPlaying(false);
    }, 700);
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

  // "Are you still there?" idle check. Restarts on any sign of life on the
  // current question — typing, an interim voice transcript, or a fresh
  // question arriving — so it only ever fires on genuine, sustained
  // silence, never mid-answer. Not a violation: it never touches
  // raiseViolation's counter, and it stays out of the way while the AI is
  // still thinking or grading.
  useEffect(() => {
    setStillThereNudge(false);

    // `speaking` matters as much as `phase` here: the interviewer reading a
    // long question out loud can easily run past 10s on its own, and
    // speak() cancels whatever audio is already playing to start new audio
    // — so without this guard, the nudge's own "are you still there?" line
    // was cutting the question off mid-sentence before it ever finished.
    if (
      step !== "live" || session === null || session.status !== "in_progress" || session.ready_to_finish ||
      phase === "thinking" || phase === "grading" || speaking
    ) {
      return;
    }

    const IDLE_NUDGE_MS = 10_000;
    const IDLE_AUTO_ADVANCE_MS = 60_000;

    const nudgeTimer = setTimeout(() => {
      setStillThereNudge(true);
      speak("Are you still there? Take your time, or use skip to move on.");
    }, IDLE_NUDGE_MS);

    const advanceTimer = setTimeout(() => {
      void sendAnswer("(No response — moved on automatically after a long pause.)");
    }, IDLE_AUTO_ADVANCE_MS);

    return () => {
      clearTimeout(nudgeTimer);
      clearTimeout(advanceTimer);
    };
  }, [step, session?.status, session?.ready_to_finish, question?.id, typedAnswer, transcript, phase, speaking, speak, sendAnswer]);

  const skipQuestion = useCallback(() => {
    recognitionRef.current?.stop();
    recognitionRef.current = null;
    void sendAnswer("(Skipped this question.)");
  }, [sendAnswer]);

  const startListening = useCallback(() => {
    const Ctor = speechRecognitionCtor();
    if (Ctor === null) { setMicError("Voice input needs Chrome or Edge."); return; }
    window.speechSynthesis.cancel();
    stopAudio();
    setSpeaking(false);
    setMicError(null);
    committedRef.current = "";
    setTranscript("");

    // A genuine error (mic revoked, no speech at all, a real hardware/
    // permission problem) owns the ending itself and shows its own message —
    // onend's auto-restart below must never second-guess that by silently
    // resuming a session onerror just told the candidate had stopped.
    let erroredOut = false;

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
      erroredOut = true;
      recognitionRef.current = null;
      setMicError(
        event.error === "not-allowed"
          ? "Microphone blocked — allow it in the address bar, then tap Answer again."
          : "The mic cut out — tap Answer to continue.",
      );
      setPhase("idle");
    };
    recognition.onend = () => {
      // recognitionRef.current is already null here if this end came from
      // the candidate themselves (stopAndSend/skipQuestion null it out
      // before calling stop()), or from onerror just above — nothing to do
      // in either case, that handler already covered it.
      if (recognitionRef.current === null || erroredOut) return;

      // Any other onend is Chrome's own engine giving up — most often on a
      // long natural pause mid-answer — despite continuous:true. Restarting
      // immediately is what makes it actually continuous: committedRef
      // already holds everything said so far, so a silent restart loses
      // nothing, where the old behaviour (drop to idle without sending)
      // silently stopped listening mid-answer with no visible sign of it —
      // everything said after that point was never captured at all.
      if (closingRef.current) {
        recognitionRef.current = null;
        return;
      }

      try {
        recognition.start();
      } catch {
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
    stopAudio();
    setPhase("grading");

    // Stop first so the very last seconds are captured, before either
    // network call — an evidence upload that missed the closing answer
    // because it fired too early would defeat the point of recording at all.
    const recording = await stopRecording();

    try {
      await apiJson(`/api/v1/me/mocks/${id}/finish`, { method: "POST" });
    } catch {
      // The session page shows the precise error state.
    }

    if (recording !== null) {
      const form = new FormData();
      form.append("recording", recording, `interview-${id}.webm`);
      // Best-effort — a failed upload should never strand the candidate on
      // this screen. The score already stands on its own without it.
      await apiJson(`/api/v1/me/mocks/${id}/recording`, { method: "POST", body: form }).catch(() => {});
    }

    router.push(`/student-ai-mock/${id}`);
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
          <Link href={`/student-ai-mock/${id}`} className="mt-3 inline-block rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white">
            View the session →
          </Link>
        </div>
      </div>
    );
  }

  const listening = phase === "listening";
  const avatarPhase: AiAvatarPhase = phase === "thinking" ? "thinking" : speaking ? "speaking" : listening ? "listening" : "idle";

  // The self-view tile, reused across the system-check, rules-preview and
  // live screens so the candidate is never looking at a placeholder once
  // the camera has actually granted.
  const selfView = (
    <div className="relative h-full w-full overflow-hidden bg-ink-2">
      {camOn ? (
        <video ref={videoRef} autoPlay playsInline muted className="h-full w-full scale-x-[-1] object-cover" />
      ) : (
        <div className="grid h-full w-full place-items-center">
          <span className="text-xs text-white/50">Camera off</span>
        </div>
      )}
    </div>
  );

  // ── Step 1: system check ────────────────────────────────────────────────
  if (step === "checking") {
    const CheckRow = ({
      label, state, extra,
    }: { label: string; state: CheckState; extra?: ReactNode }) => (
      <div className="flex items-start gap-3 py-2.5">
        <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center">
          {state === "ok" && <span className="grid h-5 w-5 place-items-center rounded-full bg-verify text-[11px] text-white">✓</span>}
          {state === "pending" && <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-white/40 motion-reduce:animate-none" />}
          {state === "failed" && <span className="grid h-5 w-5 place-items-center rounded-full bg-warn/80 text-[11px] text-white">!</span>}
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-white">{label}</p>
          {extra}
        </div>
      </div>
    );

    return (
      <div className="fixed inset-0 z-[60] overflow-y-auto bg-ink px-5 py-10">
        <div className="mx-auto max-w-3xl">
          <p className="mono text-[11px] uppercase tracking-widest text-trust">Before you begin</p>
          <h1 className="display mt-2 text-2xl text-white">System check</h1>
          <p className="mt-1 text-sm text-white/60">A quick check so nothing interrupts the interview once it starts.</p>

          <div className="mt-6 grid gap-5 sm:grid-cols-[1.1fr_1fr]">
            <div className="rounded-[18px] border border-white/10 bg-white/5 p-5">
              <CheckRow label="Internet connection" state={connectionCheck} />
              <div className="h-px bg-white/10" />
              <CheckRow
                label="Camera and microphone access"
                state={camOn ? "ok" : mediaSettled ? "failed" : "pending"}
                extra={!camOn && mediaSettled && (
                  <p className="mt-1 text-xs text-warn">
                    Blocked — allow camera/mic in the address bar and reload to test them.
                  </p>
                )}
              />
              <div className="h-px bg-white/10" />
              <CheckRow
                label="Testing microphone"
                state={micCheck}
                extra={
                  <div className="mt-1.5">
                    <p className="text-xs text-white/60">
                      Please say — <span className="font-medium text-white">&quot;I am ready for the interview.&quot;</span>
                    </p>
                    <button
                      onClick={testMic}
                      disabled={!camOn || micListening}
                      className="mt-2 rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white disabled:opacity-40"
                    >
                      {micListening ? "Listening…" : micCheck === "ok" ? "Sounds good — test again" : micCheck === "failed" ? "Didn't catch that — try again" : "Speak now"}
                    </button>
                  </div>
                }
              />
              <div className="h-px bg-white/10" />
              <CheckRow
                label="Testing speaker audio"
                state={speakerCheck}
                extra={
                  speakerCheck === "ok" || speakerTested ? null : (
                    <div className="mt-1.5">
                      {speakerPlaying ? (
                        <p className="text-xs text-white/60">Playing a test tone…</p>
                      ) : (
                        <button
                          onClick={testSpeaker}
                          className="rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white"
                        >
                          Play test sound
                        </button>
                      )}
                    </div>
                  )
                }
              />
              {speakerCheck !== "ok" && !speakerPlaying && speakerTested && (
                <div className="mt-1 flex items-center gap-2 pl-8">
                  <span className="text-xs text-white/60">Did you hear it?</span>
                  <button onClick={() => setSpeakerCheck("ok")} className="rounded-full bg-verify px-3 py-1 text-xs font-semibold text-white">Yes</button>
                  <button onClick={testSpeaker} className="rounded-full border border-white/20 px-3 py-1 text-xs text-white/80">Retry</button>
                </div>
              )}
            </div>

            <div className="aspect-[4/3] overflow-hidden rounded-[18px] border border-white/10">
              {selfView}
            </div>
          </div>

          <div className="mt-6 flex items-center justify-between">
            <button onClick={() => router.back()} className="text-sm text-white/50 hover:underline">Not now</button>
            <button
              onClick={() => setStep("rules")}
              className="rounded-full bg-trust px-6 py-2.5 text-sm font-semibold text-white"
            >
              Continue →
            </button>
          </div>
        </div>
      </div>
    );
  }

  // ── Step 2: recording & conduct rules ───────────────────────────────────
  if (step === "rules") {
    const DONT = [
      "Switch tabs, apps, or windows, or minimise this one",
      "Use another device, a second screen, or notes off-camera",
      "Have anyone else in frame, or step out of frame yourself",
      "Paste an answer instead of speaking your own",
    ];

    return (
      <div className="fixed inset-0 z-[60] grid place-items-center bg-ink p-6">
        <div className="w-full max-w-md rounded-[22px] bg-white p-7">
          <div className="mx-auto grid h-12 w-12 place-items-center rounded-full bg-warn/10 text-2xl">🛡️</div>
          <h1 className="display mt-4 text-center text-xl text-ink">Recording &amp; conduct rules</h1>
          <p className="mt-2 text-center text-sm text-muted">
            This interview is recorded from here on. One warning is given for a first slip — a repeat
            ends the interview immediately, unscored.
          </p>

          <p className="mt-5 text-xs font-semibold uppercase tracking-widest text-warn">Don&apos;t</p>
          <ul className="mt-2 space-y-2">
            {DONT.map((line) => (
              <li key={line} className="flex gap-2 text-sm text-ink">
                <span className="mt-0.5 shrink-0 text-warn">✕</span>
                <span>{line}</span>
              </li>
            ))}
          </ul>

          <div className="mt-5 rounded-[10px] bg-paper px-4 py-3 text-xs text-muted">
            🎥 Camera on, speak your answers out loud — treat it like the real room.
          </div>

          <button
            onClick={() => { unlockAudio(); setStep("countdown"); }}
            className="mt-6 w-full rounded-full bg-trust px-5 py-3 text-sm font-semibold text-white"
          >
            I understand, begin interview
          </button>
          {/* Back to wherever this room was opened from — the job's apply
              page for an employer-JD interview, the text-mock page for a
              course mock. Never a hardcoded destination: a candidate who
              declines a spoken employer interview should not be quietly
              dropped into the plain-text one instead, since here that
              interview is a gate on Apply, not an alternative format. */}
          <button
            onClick={() => router.back()}
            className="mt-3 block w-full text-center text-sm text-muted hover:underline"
          >
            Not now
          </button>
        </div>
      </div>
    );
  }

  // ── Step 3: countdown ────────────────────────────────────────────────────
  if (step === "countdown") {
    return (
      <div className="fixed inset-0 z-[60] grid place-items-center bg-ink p-6">
        <div className="grid w-full max-w-4xl gap-6 sm:grid-cols-[1fr_1.4fr] sm:items-center">
          <div className="aspect-[4/3] overflow-hidden rounded-[18px] border border-white/10">
            {selfView}
          </div>
          <div className="text-center sm:text-left">
            <p className="text-sm text-white/60">Interview starts in</p>
            <p className="display mt-1 text-8xl text-white">{countdown}</p>
          </div>
        </div>
      </div>
    );
  }

  // Shown in place of the whole room the moment a violation closes the
  // interview — the candidate reads the actual reason instead of it
  // scrolling past in a banner on the way to an auto-redirect.
  if (closedReason !== null) {
    return (
      <div className="fixed inset-0 z-[60] grid place-items-center bg-ink p-6">
        <div className="w-full max-w-md rounded-[22px] bg-white p-7 text-center">
          <p className="text-4xl">🍀</p>
          <h1 className="display mt-3 text-xl text-ink">Interview closed</h1>
          <p className="mt-3 rounded-[10px] bg-warn/10 px-4 py-3 text-sm font-medium text-ink">{closedReason}</p>
          <p className="mt-4 text-sm text-muted">
            This attempt is spent and won&apos;t be scored — but that&apos;s alright. Take a breath, and good luck on your next one.
          </p>
          <button
            onClick={() => router.push(`/student-ai-mock/${id}`)}
            className="mt-6 w-full rounded-full bg-trust px-5 py-3 text-sm font-semibold text-white"
          >
            Back to interviews
          </button>
        </div>
      </div>
    );
  }

  // ── Step 4: live — split panel + running transcript ─────────────────────
  const statusLine = phase === "thinking" ? "Thinking…" : phase === "grading" ? "Preparing your scorecard…" : speaking ? "Speaking" : listening ? "Listening to your answer…" : "Waiting for your answer";
  const transcriptLine = listening && transcript !== "" ? transcript : captions ? question?.body ?? "" : "";

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

      {/* Proctoring warning — tab switch, face out of frame, or a second face */}
      {warningText !== null && (
        <div className="mx-5 rounded-[10px] bg-warn/90 px-4 py-2 text-center text-sm font-semibold text-white">
          {warningText}
        </div>
      )}

      {/* Idle check-in — not a violation, just a nudge. Clears the moment
          there's any activity again. */}
      {warningText === null && stillThereNudge && (
        <div className="mx-5 rounded-[10px] bg-white/10 px-4 py-2 text-center text-sm font-medium text-white/80">
          👋 Are you still there? Take your time, or tap Skip to move to the next question.
        </div>
      )}

      {/* Split panel: interviewer tile + candidate tile, side by side. */}
      <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col overflow-y-auto px-5 py-5">
        <div className="grid gap-4 sm:grid-cols-2">
          <div className={`relative aspect-video overflow-hidden rounded-[16px] border-2 bg-white/5 transition-colors ${speaking || phase === "thinking" ? "border-trust" : "border-white/10"}`}>
            <div className="grid h-full place-items-center">
              <div className="flex flex-col items-center">
                <AiAvatar phase={avatarPhase} />
              </div>
            </div>
            <span className="absolute bottom-2 left-3 rounded-full bg-black/50 px-2.5 py-1 text-xs font-medium text-white">Interviewer</span>
          </div>

          <div className="relative aspect-video overflow-hidden rounded-[16px] border-2 border-white/10 bg-ink-2">
            {selfView}
            <span className="absolute right-3 top-2 flex items-center gap-1.5 rounded-full bg-black/60 px-2.5 py-1 text-[11px] font-medium text-white">
              <span className="h-1.5 w-1.5 rounded-full bg-warn" /> Rec
            </span>
            <span className="absolute bottom-2 left-3 rounded-full bg-black/50 px-2.5 py-1 text-xs font-medium text-white">You</span>
          </div>
        </div>

        {/* Running transcript — the current question, or the candidate's own words while answering. */}
        <div className="mt-4 min-h-[92px] rounded-[14px] border border-white/10 bg-white/5 p-4">
          <div className="flex items-center justify-between">
            <span className="mono text-[10px] uppercase tracking-widest text-white/40">Transcription</span>
            <span className="mono text-[10px] uppercase tracking-widest text-white/40">{statusLine}</span>
          </div>
          {transcriptLine !== "" ? (
            <p className={`mt-2 text-sm leading-relaxed ${listening && transcript !== "" ? "italic text-white/70" : "text-white"}`}>
              {listening && transcript !== "" ? `"${transcriptLine}"` : transcriptLine}
            </p>
          ) : (
            <p className="mt-2 text-sm text-white/40">
              {phase === "grading" ? "Grading your interview…" : "…"}
            </p>
          )}
          {micError && <p className="mt-2 text-sm text-warn">{micError}</p>}
        </div>
      </main>

      {/* Control bar */}
      <footer className="flex flex-wrap items-center justify-center gap-3 px-5 pb-6 pt-3">
        {session.ready_to_finish ? (
          <p className="w-full text-center text-xs text-white/60">That&apos;s the full round — end the interview to get your scorecard.</p>
        ) : !voiceSupported ? (
          // No SpeechRecognition in this browser (Brave and some other
          // Chromium forks block it by default) — typing is the only way
          // to answer at all here, not a lesser option next to the mic.
          <form
            onSubmit={(e) => { e.preventDefault(); const t = typedAnswer; setTypedAnswer(""); void sendAnswer(t); }}
            className="flex w-full max-w-xl items-center gap-2"
          >
            <input
              value={typedAnswer}
              onChange={(e) => setTypedAnswer(e.target.value)}
              // A pasted answer is almost certainly composed somewhere else —
              // another tab, an AI assistant — not spoken from the candidate's
              // own head. Blocked outright, not just discouraged, and counted
              // against the same proctoring strike as leaving the tab.
              onPaste={(e) => {
                e.preventDefault();
                raiseViolation("⚠️ Pasting isn't allowed — type your own answer. One more time and this interview closes.");
              }}
              disabled={phase === "thinking" || phase === "grading"}
              placeholder="Voice input isn't available in this browser — type your answer…"
              className="flex-1 rounded-full border border-white/20 bg-white/10 px-4 py-3 text-sm text-white outline-none placeholder:text-white/40 focus:border-trust disabled:opacity-40"
            />
            <button
              type="submit"
              disabled={typedAnswer.trim() === "" || phase === "thinking" || phase === "grading"}
              className="shrink-0 rounded-full bg-trust px-5 py-3 text-sm font-semibold text-white disabled:opacity-40"
            >
              Send
            </button>
          </form>
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

        {!session.ready_to_finish && (
          <button
            onClick={skipQuestion}
            disabled={phase === "thinking" || phase === "grading"}
            className="rounded-full border border-white/20 px-4 py-3 text-sm text-white/80 hover:border-white/40 disabled:opacity-40"
            title="Move to the next question without answering this one"
          >
            Skip question
          </button>
        )}

        {canFinish ? (
          <button
            onClick={finish}
            disabled={phase === "grading"}
            className="rounded-full bg-warn px-5 py-3 text-sm font-semibold text-white disabled:opacity-40"
          >
            {phase === "grading" ? "Grading…" : "End interview"}
          </button>
        ) : (
          // Same reasoning as "Not now" above — back to wherever this room
          // was opened from, not a silent drop into the text-mode page.
          <button
            onClick={() => router.back()}
            className="rounded-full border border-white/20 px-4 py-3 text-sm text-white/80 hover:border-white/40"
          >
            Leave
          </button>
        )}
      </footer>
    </div>
  );
}
