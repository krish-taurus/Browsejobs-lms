/**
 * Checks the lip-sync analyzer against synthetic interviews. Run with:
 *   node apps/web/scripts/check-lip-sync.mts
 * (Node 22.18+ runs TypeScript directly.) The analyzer is a heuristic; these
 * scenarios pin down what it must and must not flag so a change to a threshold
 * cannot silently break that.
 */
import assert from "node:assert/strict";
import { LipSyncAnalyzer, SpeechDetector, type LipFlag } from "../src/lib/lip-sync.ts";

type Phrase = { start: number; end: number };

/** Ten answers of 3-5 s, 1.8 s apart, from t = 2 s. */
function phrases(count = 10): Phrase[] {
  const out: Phrase[] = [];
  let t = 2000;
  for (let i = 0; i < count; i++) {
    const len = 3000 + (i % 3) * 1000;
    out.push({ start: t, end: t + len });
    t += len + 1800;
  }

  return out;
}

type Options = {
  /** How long after the sound starts the lips start (and after it stops, they stop). 40 = a real speaker. */
  lagMs?: number;
  /** The mouth never moves while sound plays. */
  mouthStill?: boolean;
  /** No sound at all, but the lips move during the "phrases". */
  silent?: boolean;
  /** No face is found. */
  noFace?: boolean;
  /** The interviewer is the one speaking (mic readings are discarded). */
  interviewer?: boolean;
};

function run(opts: Options = {}): { flags: LipFlag[]; summary: ReturnType<LipSyncAnalyzer["summary"]> } {
  const flags: LipFlag[] = [];
  const analyzer = new LipSyncAnalyzer((f) => flags.push(f));
  const list = phrases();
  const end = list[list.length - 1].end + 3000;
  const lag = opts.lagMs ?? 40;
  const speakingAt = (t: number) => list.some((p) => t >= p.start && t <= p.end);
  const lipsAt = (t: number) => list.some((p) => t >= p.start + lag && t <= p.end + lag);

  let nextMouth = 0;
  for (let t = 0; t <= end; t += 25) {
    analyzer.audio(t, opts.interviewer ? null : opts.silent ? false : speakingAt(t));
    if (t >= nextMouth) {
      nextMouth += 110;
      if (opts.noFace) {
        analyzer.mouth(t, null);
      } else if (!opts.mouthStill && lipsAt(t)) {
        // Talking: the jaw opens and closes about four times a second.
        const phase = Math.sin((t / 1000) * 2 * Math.PI * 4);
        analyzer.mouth(t, { open: 0.16 + 0.12 * phase, shape: 0.1 + 0.08 * Math.cos((t / 1000) * 2 * Math.PI * 3) });
      } else {
        analyzer.mouth(t, { open: 0.02 + 0.004 * Math.sin(t), shape: 0.02 });
      }
    }
  }
  analyzer.audio(end + 2000, false);

  return { flags, summary: analyzer.summary() };
}

const kinds = (flags: LipFlag[]) => [...new Set(flags.map((f) => f.kind))].sort();
let passed = 0;
function check(name: string, fn: () => void): void {
  fn();
  passed += 1;
  console.log(`ok  ${name}`);
}

check("a real speaker (lips start with the voice) raises nothing", () => {
  const { flags, summary } = run({ lagMs: 40 });
  assert.deepEqual(kinds(flags), []);
  assert.ok((summary.matchPct ?? 0) >= 85, `match ${summary.matchPct}`);
  assert.ok(summary.medianLagMs !== null && summary.medianLagMs < 250, `lag ${summary.medianLagMs}`);
});

check("a camera that runs slightly behind the sound is still not a lip-sync flag", () => {
  const { flags } = run({ lagMs: 180 });
  assert.deepEqual(kinds(flags), []);
});

check("lips that keep following the voice by about half a second are flagged", () => {
  const { flags, summary } = run({ lagMs: 500 });
  assert.ok(kinds(flags).includes("lip_lag"), JSON.stringify(summary));
  const lag = flags.find((f) => f.kind === "lip_lag");
  assert.ok((lag?.lagMs ?? 0) >= 350);
});

check("speech with a mouth that never moves is flagged, with seconds and the share that moved", () => {
  const { flags, summary } = run({ mouthStill: true });
  assert.deepEqual(kinds(flags), ["lip_voice_mismatch"]);
  assert.ok((summary.matchPct ?? 100) < 15, `match ${summary.matchPct}`);
  assert.ok((flags[0].secs ?? 0) >= 3);
});

check("lips moving with no sound at all are flagged", () => {
  const { flags } = run({ silent: true });
  assert.ok(kinds(flags).includes("lip_no_voice"));
});

check("no face found: nothing is claimed either way", () => {
  const { flags, summary } = run({ noFace: true });
  assert.deepEqual(flags, []);
  assert.equal(summary.matchPct, null);
  assert.equal(summary.facePct, 0);
});

check("while the interviewer speaks the mic proves nothing", () => {
  const { flags, summary } = run({ interviewer: true, mouthStill: true });
  assert.deepEqual(flags, []);
  assert.equal(summary.speechSecs, 0);
});

check("the same kind of flag is not repeated every phrase", () => {
  const { flags } = run({ mouthStill: true });
  assert.ok(flags.length <= 4);
  for (let i = 1; i < flags.length; i++) assert.ok(flags[i].atMs - flags[i - 1].atMs >= 20_000);
});

check("the speech detector follows loudness over a quiet room", () => {
  const d = new SpeechDetector();
  let t = 0;
  for (; t < 1000; t += 25) assert.equal(d.push(t, 0.004), false);
  let on = false;
  for (; t < 1300; t += 25) on = d.push(t, 0.09);
  assert.equal(on, true);
  // Short dips inside a word do not end it; 200 ms of quiet does.
  assert.equal(d.push(t, 0.003), true);
  let off = true;
  for (t += 25; t < 1900; t += 25) off = d.push(t, 0.003);
  assert.equal(off, false);
});

console.log(`\n${passed} checks passed`);
