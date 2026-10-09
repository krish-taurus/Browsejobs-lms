"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useAuth } from "@/lib/auth";
import { ApiError } from "@/lib/api";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { employerApi, type JdDraft, type MemberRow } from "@/lib/employer";
import { digitsFromWords, readJdIntent, type JdIntent } from "@/lib/jd-intent";
import { JobDescription } from "@/components/jobs/JobDescription";
import { DocumentIcon, MicIcon, PipelineIcon, RobotIcon, SendIcon, UsersIcon } from "@/components/employer/icons";
import { TaurusRobotScene, type TaurusRobotHandle } from "./components/TaurusRobotScene";
import { QuickPrompt } from "./components/QuickPrompt";
import type { TaurusRobotState } from "@/lib/taurus/mount-taurus";

/**
 * Taurus AI — PRD-E emerald redesign (approved kit, Sept 2026) of what used
 * to be the Dashboard's "AI command centre" (NeuralOps), now its own page
 * per the kit's nav placement. The conversation logic below (understand →
 * draft → revise → post) is carried over from that component largely
 * unchanged — it already talked to the real endpoints; only the presentation
 * and the robot changed. "Find candidates" and "Review my pipeline" are real
 * links to the actual Jobs/Pipeline pages rather than chat answers, because
 * no chat-backed candidate-search or pipeline-summary endpoint exists yet —
 * see the honesty note in IMPLEMENT-TAURUS-AI.md §"Real chat and hiring
 * integration". Drafting a JD is the one capability this page can actually
 * deliver end-to-end, so that's what the composer and quick prompt do.
 */

type Turn = { who: "you" | "ops"; text: string };
type Question = "title" | "experience" | "location" | "openings";

/**
 * Interview-round setup, in chat, right after a JD goes live (per the
 * employer's own ask — everything through Taurus, not a separate settings
 * screen). One round at a time, same "ask one thing, parse one thing"
 * shape as the JD questions above: a round count, then each round's kind,
 * then — depending on kind — either questions to add or who runs it.
 */
type RoundKind = "ai_interview" | "human" | "mcq";
type RoundDraft = {
  name: string;
  kind: RoundKind;
  selected_questions: string[];
  assigned_member_id: number | null;
  assigned_member_name: string | null;
};
type RoundSetupPhase =
  | { step: "count" }
  | { step: "kind"; index: number }
  | { step: "ai_questions"; index: number }
  | { step: "human_assignee"; index: number };

const STOP_SETUP = /^(stop|cancel|skip all|never ?mind|not now|later)\b/i;
const DONE_ADDING = /^(done|next|skip|no more|that'?s (it|all)|finish(ed)?|nothing else)\b/i;

function parseRoundCount(text: string): number | null {
  const match = digitsFromWords(text).match(/\d{1,2}/);
  if (!match) return null;
  return Math.max(1, Math.min(8, Number(match[0])));
}

function parseRoundKind(text: string): RoundKind | null {
  const t = text.toLowerCase();
  if (/\bhuman\b|\bcall\b|\bon-?site\b|\bpanel\b|\bmanual(ly)?\b|\bmyself\b|\bwe\b/.test(t)) return "human";
  if (/\bmcq\b|multiple[- ]choice|objective/.test(t)) return "mcq";
  if (/\bai\b|interview|automatic|auto|taurus|platform/.test(t)) return "ai_interview";
  return null;
}

function roundDraftName(index: number, total: number): string {
  if (total === 2) return index === 0 ? "L1 — role fit" : "L2 — depth";
  return `Round ${index + 1}`;
}

function buildRoundDrafts(count: number): RoundDraft[] {
  return Array.from({ length: count }, (_, i) => ({
    name: roundDraftName(i, count),
    kind: "ai_interview",
    selected_questions: [],
    assigned_member_id: null,
    assigned_member_name: null,
  }));
}

const MAX_ROUND_RETRIES = 3;

function kindLabel(kind: RoundKind): string {
  return kind === "ai_interview" ? "AI interview" : kind === "human" ? "human round" : "multiple choice";
}

type AudioContextWindow = Window & { webkitAudioContext?: typeof AudioContext };

const PROMPTS: Record<Question, string> = {
  title: "What is the job title? For example: Data Engineer.",
  experience: "How many years of experience? Say a range like 3 to 5, or say any.",
  location: "Which location? Say a city, or say remote, or say any.",
  openings: "How many openings? Say a number, or say one.",
};

const START_OVER = /^(new job|new jd|another job|another role|start over|start again|reset|clear)\b/i;
const PASSES = /^(skip|any|anywhere|whatever|does ?n[o']?t matter|no preference|none|next)\b/i;
const SMALL_TALK =
  /^(hi|hii+|hey|hello|yo|namaste|good (morning|afternoon|evening|night)|how (are|r) (you|u)( doing)?|how ?are ?ya|how'?s it going|what'?s up|sup|thanks?|thank you|ty|ok(ay)?|k|cool|nice|great|test(ing)?|hmm+|are you there|you there|who are you|what can you do|what do you do)[ !.?]*$/i;
/**
 * A greeting addressed to the bot by name, or two greetings run together —
 * "Hello, how are you, Taras?" (a heard mis-transcription of "Taurus" is
 * common) — still reads as small talk to a person, but SMALL_TALK alone
 * only matches when that pattern is the *entire* message. Drop the name,
 * then check the whole remainder and, failing that, each comma-separated
 * clause on its own — "Hello" + "how are you" both read as small talk
 * individually even though neither regex alternative matches the compound
 * sentence.
 */
/**
 * Greetings keep arriving with one more trailing word than SMALL_TALK's
 * exact-match alternatives cover — "today", "man", "friend", a name — no
 * fixed enumeration keeps up with that. A short message that *starts* with
 * an unmistakable greeting/chitchat opener is small talk regardless of how
 * it trails off; ROLE_WORDS below still catches the genuine edge case of a
 * hiring request that happens to open with "hi" ("Hi, need a Data Engineer
 * urgently") since that sentence is long and never reaches this check —
 * looksLikeRole() only runs once a title is actually on the table.
 */
const GREETING_OPENER =
  /^(hi|hii+|hey|hello|yo|namaste|good (morning|afternoon|evening|night)|how (are|r) (you|u)|how'?s it going|what'?s up|sup|thanks?|thank you|ty|hmm+|are you there|who are you|what can you do|what do you do)\b/i;

function isSmallTalk(text: string): boolean {
  const withoutName = text
    .replace(/\b(taurus|taras)\b/gi, "")
    .replace(/[,]\s*[!.?]*$/, "")
    .trim();
  const candidate = withoutName || text.trim();
  if (SMALL_TALK.test(candidate)) return true;

  const clauses = candidate.split(/[,]+/).map((c) => c.trim()).filter(Boolean);
  if (clauses.length > 0 && clauses.every((c) => SMALL_TALK.test(c))) return true;

  // A short sentence (≤6 words) that opens with a greeting — "How are you
  // today?", "Hey there, all good?" — trails off in ways no fixed list
  // predicts, so length is the guard instead: long enough to plausibly be a
  // real request and it falls through to the normal reading below. Still
  // excluded even when short if it names an actual role ("Hi, need a Data
  // Engineer") — that "Hi" is a pleasantry attached to a real request, not
  // the whole message.
  const wordCount = candidate.split(/\s+/).filter(Boolean).length;
  return wordCount <= 6 && GREETING_OPENER.test(candidate) && !ROLE_WORDS.test(candidate);
}
const CHITCHAT = "I am well, thank you. I do exactly one thing: write a job description and post it. What role are we hiring for?";
const ROLE_WORDS =
  /\b(engineer|developer|dev|programmer|coder|designer|analyst|manager|architect|scientist|tester|qa|sdet|intern|trainee|apprentice|lead|head|chief|director|officer|executive|associate|consultant|specialist|administrator|admin|recruiter|accountant|auditor|marketer|marketing|sales|bd[em]|support|agent|writer|editor|content|teacher|trainer|tutor|faculty|professor|nurse|doctor|surgeon|pharmacist|therapist|counsell?or|chef|cook|barista|waiter|driver|technician|mechanic|electrician|plumber|operator|coordinator|strategist|researcher|planner|clerk|receptionist|cashier|guard|security|devops|sre|mlops|fullstack|full ?stack|frontend|front ?end|backend|back ?end|android|ios|intern(ship)?|hr|pm|po|cto|ceo|cfo|coo|vp|president|partner|principal|staff|senior|junior|sr|jr)\b/i;
const A_QUESTION = /^(how|what|why|who|when|where|which|can|do|does|is|are|will|would|should)\b/i;

function looksLikeRole(title: string): boolean {
  const t = title.trim();
  if (t.length < 2 || SMALL_TALK.test(t) || A_QUESTION.test(t)) return false;
  return ROLE_WORDS.test(t);
}

function summarise(intent: JdIntent): string {
  const bits: string[] = [];
  if (intent.experienceMin !== null) {
    bits.push(intent.experienceMax !== null ? `${intent.experienceMin}-${intent.experienceMax} years` : `${intent.experienceMin}+ years`);
  }
  if (intent.locations.length > 0) bits.push(intent.locations.join(", "));
  if (intent.remote) bits.push("remote");
  if (intent.openings !== null) bits.push(`${intent.openings} opening${intent.openings === 1 ? "" : "s"}`);
  return bits.length > 0 ? bits.join(" · ") : "no other details given";
}

function nextQuestion(intent: JdIntent): Question | null {
  if (intent.title.trim().length < 2) return "title";
  if (intent.experienceMin === null) return "experience";
  if (intent.locations.length === 0 && !intent.remote) return "location";
  if (intent.openings === null) return "openings";
  return null;
}

export default function TaurusAiPage() {
  const { user } = useAuth();
  const { workspace } = useWorkspace();
  const firstName = (user?.name ?? "there").split(" ")[0];

  const robotRef = useRef<TaurusRobotHandle>(null);
  const [robotState, setRobotState] = useState<TaurusRobotState>("idle");
  const [speechEnergy, setSpeechEnergyState] = useState(0);

  const [turns, setTurns] = useState<Turn[]>([]);
  // Whether Taurus has actually greeted this conversation aloud yet — the
  // welcome line under "What can I help you with?" used to be decorative
  // text only, never spoken, so clicking "Talk to Taurus" jumped straight to
  // recording with no acknowledgement at all.
  const [greeted, setGreeted] = useState(false);
  const [prompt, setPrompt] = useState("");
  const [intent, setIntent] = useState<JdIntent | null>(null);
  const [asking, setAsking] = useState<Question | null>(null);
  const [doubt, setDoubt] = useState("");
  const [draft, setDraft] = useState<JdDraft | null>(null);
  const [title, setTitle] = useState("");
  const [skills, setSkills] = useState<string[]>([]);
  const [newSkill, setNewSkill] = useState("");

  // Interview-round setup, right after the JD goes live — see RoundSetupPhase.
  const [roundJobId, setRoundJobId] = useState<number | null>(null);
  const [roundSetup, setRoundSetup] = useState<RoundSetupPhase | null>(null);
  const [roundDrafts, setRoundDrafts] = useState<RoundDraft[]>([]);
  // Voice answers can misfire repeatedly (background noise, a garbled
  // transcription) — after a few unreadable answers on the same question,
  // fall back to a sensible default rather than re-asking forever.
  const [roundRetries, setRoundRetries] = useState(0);
  const [mockQuestions, setMockQuestions] = useState<{ text: string; skill: string | null; type: string | null }[]>([]);
  const [teamMembers, setTeamMembers] = useState<MemberRow[]>([]);

  const [busy, setBusy] = useState(false);
  const [posting, setPosting] = useState(false);
  const [listening, setListening] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const logRef = useRef<HTMLDivElement | null>(null);
  const composerRef = useRef<HTMLTextAreaElement | null>(null);
  const audioRef = useRef<HTMLAudioElement | null>(null);
  const ctxRef = useRef<AudioContext | null>(null);
  const rafRef = useRef<number | null>(null);
  const errorFlashRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const mediaStreamRef = useRef<MediaStream | null>(null);
  const chunksRef = useRef<Blob[]>([]);

  useEffect(() => {
    logRef.current?.scrollTo({ top: logRef.current.scrollHeight, behavior: "smooth" });
  }, [turns, draft]);

  /** A device/request failure flashes the robot's error state briefly, then settles back to idle. */
  const flashError = useCallback(() => {
    setRobotState("error");
    if (errorFlashRef.current) clearTimeout(errorFlashRef.current);
    errorFlashRef.current = setTimeout(() => setRobotState("idle"), 1600);
  }, []);

  useEffect(() => () => { if (errorFlashRef.current) clearTimeout(errorFlashRef.current); }, []);

  const stopSpeaking = useCallback(() => {
    if (rafRef.current !== null) cancelAnimationFrame(rafRef.current);
    rafRef.current = null;
    audioRef.current?.pause();
    audioRef.current = null;
    if (typeof window !== "undefined" && "speechSynthesis" in window) window.speechSynthesis.cancel();
    setSpeechEnergyState(0);
  }, []);

  const speakLocally = useCallback((text: string) => {
    if (typeof window === "undefined" || !("speechSynthesis" in window)) return;
    window.speechSynthesis.cancel();
    const utter = new SpeechSynthesisUtterance(text);
    const voices = window.speechSynthesis.getVoices();
    const voice = voices.find((v) => v.lang === "en-IN") ?? voices.find((v) => /en-GB|en-US/.test(v.lang));
    if (voice) utter.voice = voice;
    utter.rate = 1.02;
    let fade = 0;
    utter.onstart = () => {
      setRobotState("speaking");
      fade = window.setInterval(() => setSpeechEnergyState((v) => Math.max(0, v - 0.12)), 45);
    };
    utter.onboundary = () => setSpeechEnergyState(0.55 + Math.random() * 0.45);
    const done = () => { window.clearInterval(fade); setSpeechEnergyState(0); setRobotState("idle"); };
    utter.onend = done;
    utter.onerror = done;
    window.speechSynthesis.speak(utter);
  }, []);

  const speak = useCallback(
    async (text: string) => {
      stopSpeaking();
      let clip: Blob | null = null;
      try {
        clip = await employerApi.speak(workspace.id, text);
      } catch {
        clip = null;
      }
      if (!clip) { speakLocally(text); return; }

      const url = URL.createObjectURL(clip);
      const audio = new Audio(url);
      audioRef.current = audio;
      const finish = (ok: boolean) => {
        URL.revokeObjectURL(url);
        if (audioRef.current === audio) audioRef.current = null;
        if (rafRef.current !== null) cancelAnimationFrame(rafRef.current);
        rafRef.current = null;
        setSpeechEnergyState(0);
        setRobotState("idle");
        if (!ok) speakLocally(text);
      };
      audio.onended = () => finish(true);
      audio.onerror = () => finish(false);

      try {
        await audio.play();
        setRobotState("speaking");
        const w = window as AudioContextWindow;
        const Ctx = window.AudioContext ?? w.webkitAudioContext;
        if (Ctx) {
          const ctx = ctxRef.current ?? new Ctx();
          ctxRef.current = ctx;
          if (ctx.state === "suspended") void ctx.resume();
          const analyser = ctx.createAnalyser();
          analyser.fftSize = 512;
          ctx.createMediaElementSource(audio).connect(analyser);
          analyser.connect(ctx.destination);
          const wave = new Uint8Array(analyser.fftSize);
          const step = () => {
            if (audio.paused || audio.ended) return;
            analyser.getByteTimeDomainData(wave);
            let sum = 0;
            for (let i = 0; i < wave.length; i += 1) { const d = (wave[i] - 128) / 128; sum += d * d; }
            setSpeechEnergyState(Math.min(1, Math.sqrt(sum / wave.length) * 4.5));
            rafRef.current = requestAnimationFrame(step);
          };
          step();
        }
      } catch {
        finish(false);
      }
    },
    [workspace.id, speakLocally, stopSpeaking],
  );

  const say = useCallback(
    (who: Turn["who"], text: string, aloud = false) => {
      setTurns((t) => [...t, { who, text }]);
      if (aloud && who === "ops") void speak(text);
    },
    [speak],
  );

  /**
   * Speaks the welcome line the first time this conversation actually
   * starts. Returns true when it just greeted (the caller's own action
   * should wait — the mic button uses this to greet-then-arm on the first
   * press rather than starting to record over its own greeting); returns
   * false on every call after the first, when the caller should proceed
   * immediately as normal.
   */
  const ensureGreeting = useCallback(() => {
    if (greeted) return false;
    setGreeted(true);
    say("ops", `Hi ${firstName}! Tell me what you're hiring for, and we'll take the next step together.`, true);
    return true;
  }, [greeted, firstName, say]);

  const understand = useCallback(
    async (text: string): Promise<JdIntent> => {
      setBusy(true);
      setRobotState("thinking");
      try {
        const { data } = await employerApi.readIntent(workspace.id, text);
        return {
          title: data.title,
          experienceMin: data.experience_min_years,
          experienceMax: data.experience_max_years,
          locations: data.locations,
          openings: data.openings,
          remote: data.remote,
          notes: text,
        };
      } catch {
        return readJdIntent(text);
      } finally {
        setBusy(false);
        setRobotState("idle");
      }
    },
    [workspace.id],
  );

  const draftJd = useCallback(
    async (ready: JdIntent) => {
      setDraft(null);
      setBusy(true);
      setRobotState("thinking");
      say("ops", `Writing a JD for ${ready.title} — ${summarise(ready)}. One moment.`, true);
      try {
        const res = await employerApi.draftJd(workspace.id, {
          title: ready.title,
          notes: ready.notes,
          experience_min_years: ready.experienceMin ?? undefined,
          experience_max_years: ready.experienceMax ?? undefined,
          locations: ready.locations.length > 0 ? ready.locations : undefined,
        });
        setDraft(res.data);
        setTitle(ready.title);
        setSkills(res.data.skills);
        say("ops", "Here is the draft. Change anything you like, then post it — nothing goes live until you do.", true);
      } catch (err) {
        const message = err instanceof ApiError ? (err.firstError ?? err.message) : "Drafting is unavailable right now.";
        setError(message);
        say("ops", message, true);
        flashError();
      } finally {
        setBusy(false);
        setRobotState("idle");
      }
    },
    [workspace.id, say, flashError],
  );

  const reviseDraft = useCallback(
    async (instruction: string) => {
      if (!draft) return;
      setBusy(true);
      setRobotState("thinking");
      try {
        const { data } = await employerApi.reviseJd(workspace.id, {
          title: title.trim() || intent?.title || "",
          description: draft.description,
          skills,
          instruction,
        });
        setDraft({ ...draft, description: data.description });
        setSkills(data.skills);
        say("ops", data.summary, true);
      } catch (err) {
        const message = err instanceof ApiError ? (err.firstError ?? err.message) : "Could not make that change.";
        say("ops", message, true);
        flashError();
      } finally {
        setBusy(false);
        setRobotState("idle");
      }
    },
    [draft, workspace.id, title, intent, skills, say, flashError],
  );

  const askRoundKind = useCallback(
    (index: number, drafts: RoundDraft[]) => {
      setRoundSetup({ step: "kind", index });
      say("ops", `Round ${index + 1} — "${drafts[index].name}". Should this be an AI interview or a human round?`, true);
    },
    [say],
  );

  /**
   * The one way round setup ever ends, however it ends — every round
   * configured, "skip" at the very first question, or "stop" mid-way. The
   * JD is still just a draft up to this point (post() deliberately did not
   * publish it), because clicking "Post it live" is what asked for rounds to
   * be set up BEFORE anything goes live, not after — so publishing has to
   * happen here, at the end of the conversation, whichever way it ends,
   * or clicking that button would silently leave the JD unpublished.
   */
  const exitRoundSetup = useCallback(
    async (drafts: RoundDraft[] | null) => {
      if (roundJobId === null) {
        setRoundSetup(null);
        return;
      }
      setBusy(true);
      try {
        if (drafts && drafts.length > 0) {
          await employerApi.saveRounds(
            workspace.id,
            roundJobId,
            drafts.map((d) => ({
              name: d.name,
              kind: d.kind,
              dispatch: "manual",
              enabled: true,
              assigned_member_id: d.assigned_member_id,
              selected_questions: d.selected_questions,
            })),
          );
        }
        await employerApi.publishJob(workspace.id, roundJobId);

        const summary = drafts && drafts.length > 0
          ? " " + drafts
            .map((d, i) => {
              const bits = [kindLabel(d.kind)];
              if (d.kind === "ai_interview" && d.selected_questions.length > 0) {
                bits.push(`${d.selected_questions.length} question${d.selected_questions.length === 1 ? "" : "s"} you added`);
              }
              if (d.kind === "human") bits.push(d.assigned_member_name ? `assigned to ${d.assigned_member_name}` : "not assigned yet");
              return `Round ${i + 1} — ${d.name}: ${bits.join(", ")}.`;
            })
            .join(" ")
          : "";
        say("ops", `🎉 JD is live!${summary} You can fine-tune any of this anytime — just tell me, or open the JD's Process tab.`, true);
      } catch (err) {
        const message = err instanceof ApiError ? (err.firstError ?? err.message) : "Could not publish that JD.";
        say("ops", `${message} It's saved as a draft — you can publish it and set up rounds from the JD's Process tab.`, true);
      } finally {
        setBusy(false);
        setRoundSetup(null);
      }
    },
    [roundJobId, workspace.id, say],
  );

  const advanceOrFinish = useCallback(
    (nextIndex: number, drafts: RoundDraft[]) => {
      if (nextIndex < drafts.length) {
        askRoundKind(nextIndex, drafts);
      } else {
        void exitRoundSetup(drafts);
      }
    },
    [askRoundKind, exitRoundSetup],
  );

  const handleRoundAnswer = useCallback(
    async (text: string) => {
      if (!roundSetup) return;
      const said = text.trim();

      if (STOP_SETUP.test(said)) {
        say("ops", "No problem — publishing now without them. Set rounds up anytime from the JD's Process tab.", true);
        void exitRoundSetup(null);
        return;
      }

      if (roundSetup.step === "count") {
        if (PASSES.test(said)) {
          say("ops", "No problem — publishing now without rounds set up. You can add them anytime from the JD's Process tab.", true);
          void exitRoundSetup(null);
          return;
        }
        const count = parseRoundCount(said);
        if (count === null) {
          if (roundRetries + 1 >= MAX_ROUND_RETRIES) {
            setRoundRetries(0);
            say("ops", "I'm having trouble hearing a number — let's go with 2 rounds, the usual setup. You can change this anytime from the JD's Process tab.", true);
            const drafts = buildRoundDrafts(2);
            setRoundDrafts(drafts);
            askRoundKind(0, drafts);
            return;
          }
          setRoundRetries((n) => n + 1);
          say("ops", "Sorry, I didn't catch a number. How many rounds should this role have — try something like 2? You can also just type it.", true);
          return;
        }
        setRoundRetries(0);
        const drafts = buildRoundDrafts(count);
        setRoundDrafts(drafts);
        askRoundKind(0, drafts);
        return;
      }

      if (roundSetup.step === "kind") {
        const { index } = roundSetup;
        let kind = parseRoundKind(said);
        if (kind === null) {
          if (roundRetries + 1 >= MAX_ROUND_RETRIES) {
            say("ops", `I'll set Round ${index + 1} as an AI interview by default — you can change this anytime from the JD's Process tab.`, true);
            kind = "ai_interview";
          } else {
            setRoundRetries((n) => n + 1);
            say("ops", `Sorry, please say "AI interview" or "human round" for Round ${index + 1}. You can also just type it.`, true);
            return;
          }
        }
        setRoundRetries(0);
        const drafts = roundDrafts.map((d, i) => (i === index ? { ...d, kind: kind as RoundKind } : d));
        setRoundDrafts(drafts);

        if (kind === "ai_interview") {
          // Generation starts the moment the JD is created (not on publish
          // any more — see CreateEmployerJob), so by the time the employer
          // has answered "how many rounds" and "AI or human" it's often
          // actually done. The one-shot fetch from startRoundSetup() was
          // taken too early to catch that — a fresh check right now, when a
          // suggestion is actually about to be shown, is the one that matters.
          let bank = mockQuestions;
          if (bank.length === 0) {
            try {
              const r = await employerApi.mock(workspace.id, roundJobId ?? 0);
              bank = (r.data.questions ?? []).map((q) => ({ text: q.text, skill: q.skill, type: q.type }));
              setMockQuestions(bank);
            } catch {
              // Still not ready (or generation fell through to nothing) — falls through to the honest message below.
            }
          }

          if (bank.length > 0) {
            const sample = bank.slice(0, 5).map((q, i2) => `${i2 + 1}. ${q.text}`).join("\n");
            say(
              "ops",
              `For an AI interview, here are a few questions already in this JD's bank:\n${sample}\n\nType any extra question you want asked in this round, one at a time. Say "done" when you're happy with it.`,
              true,
            );
          } else {
            say(
              "ops",
              "The question bank for this JD is still generating — it'll be used automatically once ready. You can still type specific questions you want asked in this round now, one at a time, or say \"done\" to move on.",
              true,
            );
          }
          setRoundSetup({ step: "ai_questions", index });
          return;
        }

        if (kind === "human") {
          if (teamMembers.length === 0) {
            say(
              "ops",
              `No one is on your workspace team yet, so I can't assign Round ${index + 1} to anyone. Invite a teammate from the Team page, then assign this later from the JD's Process tab.`,
              true,
            );
            advanceOrFinish(index + 1, drafts);
            return;
          }
          const shown = teamMembers.slice(0, 20);
          const listing = shown.map((m, i2) => `${i2 + 1}. ${m.user?.name ?? m.user?.email ?? `Member #${m.id}`}`).join("\n");
          say("ops", `Round ${index + 1} — who will take this? Reply with a number, or type their name.\n${listing}`, true);
          setRoundSetup({ step: "human_assignee", index });
          return;
        }

        // mcq — nothing further to configure conversationally right now.
        advanceOrFinish(index + 1, drafts);
        return;
      }

      if (roundSetup.step === "ai_questions") {
        const { index } = roundSetup;
        if (DONE_ADDING.test(said)) {
          advanceOrFinish(index + 1, roundDrafts);
          return;
        }
        const drafts = roundDrafts.map((d, i) =>
          i === index ? { ...d, selected_questions: [...d.selected_questions, said] } : d,
        );
        setRoundDrafts(drafts);
        say("ops", `Added: "${said}". Type another question, or say "done".`, true);
        return;
      }

      if (roundSetup.step === "human_assignee") {
        const { index } = roundSetup;
        if (/^skip$/i.test(said)) {
          say("ops", "Left unassigned — you can pick someone later from the JD's Process tab.", true);
          advanceOrFinish(index + 1, roundDrafts);
          return;
        }
        const shown = teamMembers.slice(0, 20);
        const byNumber = /^\d+$/.test(said) ? shown[Number(said) - 1] : undefined;
        const byName = byNumber ?? teamMembers.find((m) => (m.user?.name ?? "").toLowerCase().includes(said.toLowerCase()));
        if (!byName) {
          if (roundRetries + 1 >= MAX_ROUND_RETRIES) {
            setRoundRetries(0);
            say("ops", `I couldn't match that to anyone — leaving Round ${index + 1} unassigned for now. Pick someone anytime from the JD's Process tab.`, true);
            advanceOrFinish(index + 1, roundDrafts);
            return;
          }
          setRoundRetries((n) => n + 1);
          say("ops", "Sorry, I couldn't match that to anyone on your team. Try their name again, or say \"skip\" to leave it unassigned.", true);
          return;
        }
        setRoundRetries(0);
        const drafts = roundDrafts.map((d, i) =>
          i === index ? { ...d, assigned_member_id: byName.id, assigned_member_name: byName.user?.name ?? null } : d,
        );
        setRoundDrafts(drafts);
        say("ops", `Got it — ${byName.user?.name ?? "they"} will take Round ${index + 1}.`, true);
        advanceOrFinish(index + 1, drafts);
        return;
      }
    },
    [roundSetup, roundDrafts, roundRetries, mockQuestions, teamMembers, roundJobId, workspace.id, say, askRoundKind, advanceOrFinish, exitRoundSetup],
  );

  const startRoundSetup = useCallback(
    (jobId: number) => {
      setRoundJobId(jobId);
      setRoundDrafts([]);
      setRoundRetries(0);
      say(
        "ops",
        "Before this goes live, let's set up the interview process. How many rounds should this role have? Most roles use 2 — say a number from 1 to 8, or say skip to publish now without setting them up.",
        true,
      );
      setRoundSetup({ step: "count" });
      // The question bank is only generated on publish, and this JD isn't
      // published yet — so this will normally come back empty here, and an
      // AI round falls back to "add your own questions" below. Fetched
      // anyway in case it's a re-entry into round setup for an already-
      // published JD (the process tab reuses this same page in future).
      employerApi
        .mock(workspace.id, jobId)
        .then((r) => setMockQuestions((r.data.questions ?? []).map((q) => ({ text: q.text, skill: q.skill, type: q.type }))))
        .catch(() => setMockQuestions([]));
      employerApi
        .members(workspace.id)
        .then((r) => setTeamMembers(r.data))
        .catch(() => setTeamMembers([]));
    },
    [workspace.id, say],
  );

  /**
   * `null` means "already handled — say nothing else". advance() itself
   * speaks the re-ask for an unparseable answer; run() used to *also* print
   * PROMPTS[asking] right after (nextQuestion() re-deriving the same
   * unanswered question from the unchanged intent it got back), landing two
   * "ops" bubbles back to back for one thing the candidate said. Returning
   * null instead of the unchanged intent lets run() recognise that and stop.
   */
  const advance = useCallback(
    (base: JdIntent, answer: string): JdIntent | null => {
      const said = answer.trim();
      const passing = PASSES.test(said);
      const merged: JdIntent = { ...base };

      if (asking === "title" && !passing) merged.title = readJdIntent(said).title || said;

      if (asking === "experience") {
        if (/fresher|entry|no experience/i.test(said)) {
          merged.experienceMin = 0;
        } else if (passing) {
          merged.experienceMin = 0;
        } else {
          const heard = readJdIntent(`${said} years`);
          if (heard.experienceMin === null) {
            say("ops", "Sorry, I did not catch a number. How many years? For example: 3 to 5, or say any.", true);
            return null;
          }
          merged.experienceMin = heard.experienceMin;
          merged.experienceMax = heard.experienceMax;
        }
      }

      if (asking === "location") {
        if (/remote|work from home|wfh/i.test(said)) {
          merged.remote = true;
        } else if (!passing) {
          merged.locations = [said.replace(/\s+/g, " ").replace(/\b\w/g, (c) => c.toUpperCase())];
        } else {
          merged.remote = true;
        }
      }

      if (asking === "openings") {
        const number = digitsFromWords(said).match(/\d{1,3}/);
        merged.openings = number ? Number(number[0]) : 1;
      }

      merged.notes = `${base.notes} ${said}`.trim();
      return merged;
    },
    [asking, say],
  );

  const run = useCallback(
    async (said: string) => {
      const text = said.trim();
      if (text === "" || busy || posting) return;

      setPrompt("");
      setError(null);
      say("you", text);
      ensureGreeting();

      if (isSmallTalk(text)) {
        say("ops", asking ? `${CHITCHAT.split(" What role")[0]} ${PROMPTS[asking]}` : CHITCHAT, true);
        return;
      }

      if (roundSetup) {
        void handleRoundAnswer(text);
        return;
      }

      if (draft && !asking) {
        if (START_OVER.test(text)) {
          setDraft(null);
          setIntent(null);
          setTitle("");
          setSkills([]);
          say("ops", "Starting fresh. What are we hiring for?", true);
          return;
        }
        await reviseDraft(text);
        return;
      }

      let merged: JdIntent;
      if (asking === null) {
        merged = await understand(text);
      } else if (asking === "title") {
        const heard = await understand(text);
        merged = { ...(intent ?? heard), title: heard.title || text.trim(), notes: `${intent?.notes ?? ""} ${text}`.trim() };
      } else {
        const advanced = advance(intent ?? readJdIntent(text), text);
        if (advanced === null) return; // advance() already asked the candidate to repeat themselves — nothing more to say
        merged = advanced;
      }

      if ((asking === null || asking === "title") && merged.title.trim() !== "" && !looksLikeRole(merged.title)) {
        const said2 = merged.title.trim();
        if (doubt.toLowerCase() !== said2.toLowerCase()) {
          setDoubt(said2);
          setIntent({ ...merged, title: "" });
          setAsking("title");
          say("ops", `I do not recognise "${said2}" as a job title. If that really is the role, say it again and I will use it. Otherwise, what is the title?`, true);
          return;
        }
        setDoubt("");
      }

      setIntent(merged);
      const question = nextQuestion(merged);
      if (question) {
        setAsking(question);
        say("ops", PROMPTS[question], true);
        return;
      }
      setAsking(null);
      await draftJd(merged);
    },
    [busy, posting, asking, intent, doubt, draft, roundSetup, advance, understand, reviseDraft, say, draftJd, ensureGreeting, handleRoundAnswer],
  );

  const abortRecording = useCallback(() => {
    if (mediaRecorderRef.current?.state === "recording") {
      mediaRecorderRef.current.onstop = null;
      mediaRecorderRef.current.stop();
    }
    mediaStreamRef.current?.getTracks().forEach((t) => t.stop());
    mediaStreamRef.current = null;
    setListening(false);
  }, []);

  useEffect(() => () => abortRecording(), [abortRecording]);

  const transcribeAndRun = useCallback(
    async (blob: Blob) => {
      if (blob.size < 800) { say("ops", "I did not catch that. Try again, or type instead.", true); return; }
      setBusy(true);
      setRobotState("thinking");
      let text = "";
      try {
        const ext = blob.type.includes("mp4") ? "mp4" : blob.type.includes("ogg") ? "ogg" : "webm";
        const form = new FormData();
        form.append("audio", blob, `clip.${ext}`);
        const res = await employerApi.transcribe(workspace.id, form);
        text = res.data.text.trim();
      } catch (err) {
        const message = err instanceof ApiError ? (err.firstError ?? err.message) : "Could not transcribe that. Please type instead.";
        setBusy(false);
        setRobotState("idle");
        say("ops", message, true);
        flashError();
        return;
      }
      setBusy(false);
      setRobotState("idle");
      // Some transcription backends describe non-speech audio in brackets
      // instead of returning empty — "[noise]", "[background noise]",
      // "[silence]", "[inaudible]" — which otherwise gets treated as a real,
      // if nonsensical, answer and sent straight into whatever's being asked.
      if (text === "" || /^\[[^\]]*\]$/.test(text)) {
        say("ops", "I did not catch that — just background noise. Try again, or type instead.", true);
        return;
      }
      await run(text);
    },
    [workspace.id, say, run, flashError],
  );

  const listen = useCallback(async () => {
    if (listening) { mediaRecorderRef.current?.stop(); return; }

    // First-ever press of "Talk to Taurus": greet out loud, then stop —
    // press it again to actually start recording once the greeting has
    // been heard, rather than talking over its own welcome line.
    if (ensureGreeting()) return;

    if (typeof MediaRecorder === "undefined" || !navigator.mediaDevices?.getUserMedia) {
      say("ops", "This browser cannot record audio. Typing works everywhere.", true);
      flashError();
      return;
    }

    stopSpeaking();
    let stream: MediaStream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch {
      say("ops", "Microphone blocked. Allow it for this site and try again.", true);
      flashError();
      return;
    }

    mediaStreamRef.current = stream;
    chunksRef.current = [];
    const mimeType = MediaRecorder.isTypeSupported("audio/webm") ? "audio/webm" : MediaRecorder.isTypeSupported("audio/mp4") ? "audio/mp4" : "";
    const recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
    mediaRecorderRef.current = recorder;
    recorder.ondataavailable = (e) => { if (e.data.size > 0) chunksRef.current.push(e.data); };
    recorder.onstop = () => {
      stream.getTracks().forEach((t) => t.stop());
      mediaStreamRef.current = null;
      setListening(false);
      const clip = new Blob(chunksRef.current, { type: recorder.mimeType || "audio/webm" });
      chunksRef.current = [];
      void transcribeAndRun(clip);
    };
    recorder.start();
    setListening(true);
    setRobotState("listening");
  }, [listening, stopSpeaking, transcribeAndRun, say, flashError, ensureGreeting]);

  const addSkill = useCallback(() => {
    const skill = newSkill.trim();
    if (skill === "" || skills.length >= 30) return;
    setSkills((current) => (current.some((s) => s.toLowerCase() === skill.toLowerCase()) ? current : [...current, skill]));
    setNewSkill("");
  }, [newSkill, skills.length]);

  const post = useCallback(
    async (publish: boolean) => {
      if (!intent || !draft || posting) return;
      setPosting(true);
      setError(null);
      try {
        const created = await employerApi.createJob(workspace.id, {
          title: title.trim() || intent.title,
          description: draft.description,
          skills,
          experience_min_years: intent.experienceMin ?? 0,
          experience_max_years: intent.experienceMax ?? undefined,
          locations: intent.locations,
          remote: intent.remote,
          openings: intent.openings ?? 1,
        });
        if (publish) {
          // Deliberately not published yet: "Post it live" now means "set
          // up the interview process, then go live" — round setup happens
          // here, in this same conversation, and exitRoundSetup() is what
          // actually publishes once it's done (however it ends, including
          // skipping straight through). Publishing here first and asking
          // about rounds after was the old order the employer asked to have
          // reversed.
          setDraft(null);
          setIntent(null);
          setTitle("");
          setSkills([]);
          setPosting(false);
          startRoundSetup(created.data.id);
        } else {
          window.location.href = `/employer/jobs/${created.data.id}?tab=process`;
        }
      } catch (err) {
        const message = err instanceof ApiError ? (err.firstError ?? err.message) : "Could not post that JD.";
        setError(message);
        say("ops", message, true);
        setPosting(false);
        flashError();
      }
    },
    [intent, draft, posting, workspace.id, title, skills, say, flashError, startRoundSetup],
  );

  const canPost = useMemo(() => intent !== null && draft !== null && !posting && title.trim() !== "", [intent, draft, posting, title]);

  const startNewConversation = useCallback(() => {
    abortRecording();
    stopSpeaking();
    setTurns([]);
    setGreeted(false);
    setPrompt("");
    setIntent(null);
    setAsking(null);
    setDoubt("");
    setDraft(null);
    setTitle("");
    setSkills([]);
    setNewSkill("");
    setError(null);
    setRobotState("idle");
    setRoundJobId(null);
    setRoundSetup(null);
    setRoundDrafts([]);
    setRoundRetries(0);
    composerRef.current?.focus();
  }, [abortRecording, stopSpeaking]);

  const started = turns.length > 0 || draft !== null;
  const placeholder =
    asking === "experience" ? "e.g. 3 to 5 years — or say any"
      : asking === "location" ? "e.g. Hyderabad — or remote"
        : asking === "openings" ? "e.g. 3"
          : "Ask about hiring…";

  return (
    <div className="flex h-full flex-col space-y-5 pb-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="font-mono text-[11px] font-semibold uppercase tracking-widest" style={{ color: "var(--bj-dash-primary)" }}>
            Your AI hiring companion
          </p>
          <h1 className="bj-dash-serif mt-1" style={{ fontSize: "var(--bj-dash-title-size)", color: "var(--bj-dash-ink)" }}>AI Recruiter voice</h1>
          <p className="mt-1 text-sm" style={{ color: "var(--bj-dash-muted)" }}>A helping hand for every hiring decision.</p>
        </div>
        <button
          type="button"
          onClick={startNewConversation}
          className="flex shrink-0 items-center gap-2 self-start rounded-full border px-4 py-2 text-sm font-semibold sm:self-auto"
          style={{ borderColor: "var(--bj-dash-primary)", color: "var(--bj-dash-primary)" }}
        >
          <span aria-hidden>+</span> New conversation
        </button>
      </div>

      <div className="grid flex-1 gap-5 lg:grid-cols-2">
        {/* Robot scene */}
        <div className="flex flex-col items-center justify-center rounded-[var(--bj-dash-radius)] border bg-white p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
          {/* A true circle (a square box, rounded-full), not a flex-stretched
              oval — the canvas inside fills this same square so the robot's
              own framing (set by mount-taurus.mjs's resize()) matches the
              backdrop instead of floating small inside a mismatched shape. */}
          <div className="relative aspect-square w-full max-w-[440px]">
            <div className="absolute inset-[3%] rounded-full" style={{ background: "var(--bj-dash-soft)" }} aria-hidden />
            <TaurusRobotScene ref={robotRef} state={robotState} speechEnergy={speechEnergy} />
            <span
              className="absolute right-2 top-2 flex items-center gap-2 rounded-full border bg-white px-3 py-1.5 text-xs font-semibold shadow-sm sm:right-4 sm:top-4"
              style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
            >
              Voice
              <span className="flex items-center gap-1" style={{ color: "var(--bj-dash-primary)" }}>
                <span className="size-1.5 rounded-full" style={{ background: "var(--bj-dash-primary)" }} />
                {robotState === "listening" ? "Listening…" : robotState === "thinking" ? "Thinking…" : robotState === "speaking" ? "Speaking…" : robotState === "error" ? "Hit a snag" : "Ready to help"}
              </span>
            </span>
          </div>
          <button
            type="button"
            onClick={listen}
            disabled={busy || posting}
            aria-pressed={listening}
            className="mt-5 flex items-center gap-2 rounded-full px-6 py-3 text-sm font-semibold text-white transition disabled:opacity-50"
            style={{ background: listening ? "#a5352f" : "var(--bj-dash-primary)" }}
          >
            <MicIcon className="size-4" />
            {listening ? "Stop — send it" : "Talk"}
          </button>
          <p className="mt-2 text-xs" style={{ color: "var(--bj-dash-muted)" }}>Use your voice or type a message.</p>
        </div>

        {/* Chat panel */}
        <div className="flex flex-col rounded-[var(--bj-dash-radius)] border bg-white p-5 sm:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
          <div className="flex items-center gap-2.5">
            <span className="grid size-9 shrink-0 place-items-center rounded-xl" style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}>
              <RobotIcon className="size-4" />
            </span>
            <div>
              <p className="text-sm font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Your hiring assistant</p>
              <p className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>AI Recruiter voice</p>
            </div>
          </div>

          <h2 className="bj-dash-serif mt-4" style={{ fontSize: "clamp(1.4rem, 2.4vw, 1.75rem)", color: "var(--bj-dash-ink)" }}>
            What can I help you with?
          </h2>
          <p className="mt-1 text-sm" style={{ color: "var(--bj-dash-muted)" }}>
            Ask about candidates, job descriptions, or your hiring pipeline.
          </p>

          <div ref={logRef} className="mt-4 min-h-0 flex-1 space-y-3 overflow-y-auto" aria-live="polite">
            {!started && (
              <div className="rounded-2xl px-4 py-3 text-sm" style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-ink)" }}>
                Hi {firstName}! Tell me what you&rsquo;re hiring for, and we&rsquo;ll take the next step together.
              </div>
            )}

            {turns.map((turn, i) => (
              <div key={i} className={turn.who === "you" ? "text-right" : ""}>
                <p className="text-[10px] font-semibold uppercase tracking-widest" style={{ color: "var(--bj-dash-muted)" }}>
                  {turn.who === "you" ? "You" : "AI Recruiter"}
                </p>
                <p
                  className="mt-0.5 inline-block max-w-full whitespace-pre-line rounded-xl px-3.5 py-2 text-sm"
                  style={turn.who === "you"
                    ? { background: "var(--bj-dash-canvas)", color: "var(--bj-dash-ink)" }
                    : { background: "var(--bj-dash-soft)", color: "var(--bj-dash-ink)" }}
                >
                  {turn.text}
                </p>
              </div>
            ))}

            {draft && intent && (
              <div className="rounded-2xl border p-4" style={{ borderColor: "var(--bj-dash-border)" }}>
                <p className="text-[11px] font-semibold uppercase tracking-widest" style={{ color: "var(--bj-dash-muted)" }}>Draft · not posted yet</p>
                <label className="mt-3 block text-sm font-semibold" style={{ color: "var(--bj-dash-ink)" }}>
                  Job title
                  <input
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    className="mt-1 w-full rounded-xl border bg-white px-3 py-2 text-sm outline-none focus:border-[var(--bj-dash-focus)] focus:ring-2 focus:ring-[var(--bj-dash-focus)]/30"
                    style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
                  />
                </label>

                <JobDescription description={draft.description} className="mt-3 text-sm" />

                <div className="mt-3">
                  <p className="text-sm font-semibold" style={{ color: "var(--bj-dash-ink)" }}>Skills the mock will test ({skills.length})</p>
                  <div className="mt-2 flex flex-wrap gap-1.5">
                    {skills.map((skill) => (
                      <span key={skill} className="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium" style={{ background: "var(--bj-dash-soft)", color: "var(--bj-dash-primary)" }}>
                        {skill}
                        <button type="button" onClick={() => setSkills((current) => current.filter((s) => s !== skill))} aria-label={`Remove ${skill}`} className="opacity-70 hover:opacity-100">×</button>
                      </span>
                    ))}
                  </div>
                  <div className="mt-2 flex gap-2">
                    <input
                      value={newSkill}
                      onChange={(e) => setNewSkill(e.target.value)}
                      onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); addSkill(); } }}
                      placeholder="Add a skill and press Enter"
                      aria-label="Add a skill"
                      className="w-full rounded-xl border bg-white px-3 py-2 text-sm outline-none focus:border-[var(--bj-dash-focus)] focus:ring-2 focus:ring-[var(--bj-dash-focus)]/30"
                      style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
                    />
                    <button type="button" onClick={addSkill} className="shrink-0 rounded-xl border px-3 py-2 text-xs font-semibold" style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}>Add</button>
                  </div>
                </div>

                <p className="mt-3 text-xs" style={{ color: "var(--bj-dash-muted)" }}>{summarise(intent)}</p>
                <p className="mt-1 text-xs" style={{ color: "var(--bj-dash-muted)" }}>
                  Say a change and I will make it — &ldquo;add 3+ years of JavaScript to preferred skills&rdquo;. Say &ldquo;new job&rdquo; to start a different role.
                </p>

                <div className="mt-4 flex flex-wrap items-center gap-3">
                  <button type="button" onClick={() => void post(true)} disabled={!canPost} className="rounded-full px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-45" style={{ background: "var(--bj-dash-primary)" }}>
                    {posting ? "Posting…" : "Post it live"}
                  </button>
                  <button type="button" onClick={() => void post(false)} disabled={!canPost} className="rounded-full border px-5 py-2.5 text-sm font-semibold disabled:opacity-50" style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}>
                    Save as draft
                  </button>
                  <span className="text-xs" style={{ color: "var(--bj-dash-muted)" }}>Publishing also generates this JD&rsquo;s mock interview.</span>
                </div>
              </div>
            )}

            {error && <p className="text-sm text-[#a5352f]">{error}</p>}
          </div>

          {!started && (
            <div className="mt-4 border-t pt-4" style={{ borderColor: "var(--bj-dash-border)" }}>
              <p className="text-xs font-semibold uppercase tracking-wide" style={{ color: "var(--bj-dash-muted)" }}>Start with a question</p>
              <div className="mt-2 space-y-2">
                <QuickPrompt
                  icon={<UsersIcon className="size-4" />}
                  title="Find candidates"
                  description="Explore talent for your open roles"
                  href="/employer/pipeline"
                />
                <QuickPrompt
                  icon={<PipelineIcon className="size-4" />}
                  title="Review my pipeline"
                  description="See where each application stands"
                  href="/employer/pipeline"
                />
                <QuickPrompt
                  icon={<DocumentIcon className="size-4" />}
                  title="Draft a job description"
                  description="Turn your requirements into a clear role"
                  onClick={() => composerRef.current?.focus()}
                />
              </div>
            </div>
          )}

          <form
            onSubmit={(e) => { e.preventDefault(); void run(prompt); }}
            className="mt-4 flex items-end gap-2 border-t pt-4"
            style={{ borderColor: "var(--bj-dash-border)" }}
          >
            <textarea
              ref={composerRef}
              value={prompt}
              onChange={(e) => setPrompt(e.target.value)}
              onKeyDown={(e) => { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); void run(prompt); } }}
              placeholder={listening ? "Listening…" : placeholder}
              rows={1}
              disabled={busy || posting}
              aria-label="Message Taurus"
              className="max-h-32 min-h-[44px] flex-1 resize-none rounded-2xl border bg-white px-4 py-2.5 text-sm outline-none focus:border-[var(--bj-dash-focus)] focus:ring-2 focus:ring-[var(--bj-dash-focus)]/30 disabled:opacity-60"
              style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-ink)" }}
            />
            <button
              type="button"
              onClick={listen}
              disabled={busy || posting}
              aria-pressed={listening}
              aria-label={listening ? "Stop recording" : "Speak instead of typing"}
              className="grid size-11 shrink-0 place-items-center rounded-full border transition-colors disabled:opacity-40"
              style={listening ? { borderColor: "#a5352f", color: "#a5352f", background: "#fde8e9" } : { borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}
            >
              <MicIcon className="size-4" />
            </button>
            <button
              type="submit"
              disabled={busy || posting || prompt.trim() === ""}
              aria-label="Send message"
              className="grid size-11 shrink-0 place-items-center rounded-full text-white disabled:opacity-40"
              style={{ background: "var(--bj-dash-primary)" }}
            >
              <SendIcon className="size-4" />
            </button>
          </form>
          <p className="mt-2 text-center text-xs" style={{ color: "var(--bj-dash-muted)" }}>Voice and text, in one conversation.</p>
        </div>
      </div>
    </div>
  );
}
