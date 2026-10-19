/**
 * Lip-sync check for employer interviews: does the mouth the camera sees move
 * when, and only when, the answer is being spoken?
 *
 * Runs entirely in the candidate's browser on two streams the room already has:
 *  - the microphone (is someone speaking right now?), and
 *  - the face model's mouth scores (is the mouth moving right now?).
 * Nothing but a few numbers and, at a flagged moment, one still photo leaves
 * the device. Three things are looked for:
 *
 *  - lip_voice_mismatch: a stretch of speech while the mouth stays still. Someone
 *    else is talking (or the sound is played) while the candidate sits there.
 *  - lip_lag: the lips start a beat AFTER the sound, phrase after phrase. A real
 *    speaker's mouth opens with the voice; someone miming to another person's
 *    words has to hear them first.
 *  - lip_no_voice: lips moving for a while with no sound at all (miming, or
 *    reading silently while a hidden helper speaks).
 *
 * All three are estimates with known false positives (a bad mic, a still frame,
 * a camera that drops frames). They are reported as POSSIBLE, with the numbers,
 * for a person to judge next to the recording. They are never used to fail
 * anyone on their own.
 */

export const LIP_SYNC = {
  /** Mouth samples (about 9 per second) in the window that decides "the mouth is moving". */
  MOUTH_WINDOW: 6,
  /** Opening range (jawOpen, 0 to 1) across the window that counts as movement. */
  OPEN_RANGE: 0.06,
  /** Same for the combined funnel, pucker and stretch scores. */
  SHAPE_RANGE: 0.12,
  /** Opening above the recent floor that counts as the mouth starting to open. */
  ONSET_RISE: 0.05,
  /** A mouth still for this long before an opening counts as an onset. */
  STILL_MS: 500,
  MIN_SPEECH_MS: 1200,
  MERGE_GAP_MS: 350,
  /** Speech this long with the mouth still is a mismatch. */
  NO_LIPS_MIN_MS: 2500,
  NO_LIPS_MAX_ACTIVE: 0.3,
  /** Lips moving this long with no sound. */
  LIPS_ONLY_MIN_MS: 3000,
  LIPS_ONLY_MAX_SPEECH: 0.15,
  /** The camera path adds a delay of its own, so only a clear lag counts. */
  LAG_MIN_ONSETS: 6,
  LAG_FLAG_MS: 350,
  LAG_FLAG_SHARE: 0.7,
  LAG_LIMIT_MS: 1000,
  /** The same kind of flag at most this often, and at most this many per interview. */
  FLAG_GAP_MS: 20_000,
  MAX_FLAGS_PER_KIND: 4,
} as const;

export type LipFlagKind = "lip_voice_mismatch" | "lip_lag" | "lip_no_voice";

export type LipFlag = {
  kind: LipFlagKind;
  /** Milliseconds since the interview went live. */
  atMs: number;
  secs?: number;
  lagMs?: number;
  pct?: number;
  n?: number;
};

export type LipSummary = {
  /** Seconds of the candidate's own speech that were checked. */
  speechSecs: number;
  /** Share of that speech with the mouth moving, 0 to 100; null when there was too little to say. */
  matchPct: number | null;
  /** Median delay of the mouth behind the voice at the start of phrases, in ms; null with too few phrases. */
  medianLagMs: number | null;
  onsets: number;
  /** Share of the interview with exactly one face tracked, 0 to 100. */
  facePct: number;
  flags: number;
};

type MouthPoint = { t: number; open: number; shape: number } | { t: number; open: null; shape: null };

/** Turns microphone loudness into "speaking / not speaking", with a noise floor that adapts. */
export class SpeechDetector {
  private floor = 0.004;

  private on = false;

  private since = 0;

  private lastSound = 0;

  /** rms is 0 to 1 over a short window (25 ms). */
  push(tMs: number, rms: number): boolean {
    if (!this.on && rms < this.floor * 2.2) {
      // Quiet: let the floor follow the room noise, slowly.
      this.floor = this.floor * 0.97 + Math.min(rms, 0.05) * 0.03;
    }
    const loud = rms > Math.max(0.012, this.floor * 3.2);
    if (loud) {
      this.lastSound = tMs;
      if (!this.on) {
        if (this.since === 0) this.since = tMs;
        if (tMs - this.since >= 60) this.on = true;
      }
    } else {
      this.since = 0;
      if (this.on && tMs - this.lastSound >= 200) this.on = false;
    }

    return this.on;
  }
}

export class LipSyncAnalyzer {
  private readonly points: MouthPoint[] = [];

  private speaking = false;

  private segStart = 0;

  private lastSpeech = 0;

  private silentSince = 0;

  private readonly lags: number[] = [];

  private pendingOnset: number | null = null;

  private speechMs = 0;

  private matchedMs = 0;

  private faceSamples = 0;

  private totalSamples = 0;

  private lipsRun: { start: number; samples: number; speech: number } | null = null;

  private readonly emitted: Record<LipFlagKind, number[]> = { lip_voice_mismatch: [], lip_lag: [], lip_no_voice: [] };

  private flagCount = 0;

  private readonly onFlag: (flag: LipFlag) => void;

  private readonly cfg: typeof LIP_SYNC;

  constructor(onFlag: (flag: LipFlag) => void, cfg: typeof LIP_SYNC = LIP_SYNC) {
    this.onFlag = onFlag;
    this.cfg = cfg;
  }

  /** One mouth reading (about every 110 ms). null = no single face to read. */
  mouth(tMs: number, sample: { open: number; shape: number } | null): void {
    this.totalSamples += 1;
    if (sample === null) {
      this.points.push({ t: tMs, open: null, shape: null });
      this.trim(tMs);
      this.lipsRun = null;

      return;
    }
    this.faceSamples += 1;
    this.points.push({ t: tMs, open: sample.open, shape: sample.shape });
    this.trim(tMs);

    // Mouth onset: opening above its recent floor after a still moment.
    if (this.pendingOnset !== null && tMs - this.pendingOnset <= this.cfg.LAG_LIMIT_MS) {
      const rose = this.openedAt(tMs);
      if (rose) {
        this.lags.push(tMs - this.pendingOnset);
        this.pendingOnset = null;
      }
    } else if (this.pendingOnset !== null) {
      this.pendingOnset = null; // the mouth never opened with this phrase
    }

    // Lips moving with no sound.
    const moving = this.isMoving();
    if (moving && this.speaking === false) {
      this.lipsRun ??= { start: tMs, samples: 0, speech: 0 };
      this.lipsRun.samples += 1;
    } else if (moving) {
      if (this.lipsRun !== null) this.lipsRun.speech += 1;
    } else if (this.lipsRun !== null) {
      this.closeLipsRun(tMs);
    }
    if (this.lipsRun !== null && tMs - this.lipsRun.start >= this.cfg.LIPS_ONLY_MIN_MS) {
      this.closeLipsRun(tMs);
    }
  }

  /** One sound reading (about every 25 ms). null = the interviewer is speaking, so the mic proves nothing. */
  audio(tMs: number, speech: boolean | null): void {
    if (speech === null) {
      if (this.speaking) this.endSegment(tMs);
      this.speaking = false;
      this.silentSince = 0;

      return;
    }

    if (speech) {
      this.lastSpeech = tMs;
      this.silentSince = 0;
      if (!this.speaking) {
        // A new phrase. It is an onset if the mouth was still before it.
        const after = tMs - this.lastEnd > 400;
        this.speaking = true;
        this.segStart = tMs;
        if (after && this.mouthWasStill(tMs)) this.pendingOnset = tMs;
      }
    } else if (this.speaking) {
      if (this.silentSince === 0) this.silentSince = tMs;
      if (tMs - this.silentSince >= this.cfg.MERGE_GAP_MS) {
        this.endSegment(this.lastSpeech);
        this.speaking = false;
        this.silentSince = 0;
      }
    }
  }

  summary(): LipSummary {
    const median = this.lags.length >= this.cfg.LAG_MIN_ONSETS ? [...this.lags].sort((a, b) => a - b)[Math.floor(this.lags.length / 2)] : null;

    return {
      speechSecs: Math.round(this.speechMs / 1000),
      matchPct: this.speechMs >= 5000 ? Math.round((this.matchedMs / this.speechMs) * 100) : null,
      medianLagMs: median === null ? null : Math.round(median),
      onsets: this.lags.length,
      facePct: this.totalSamples === 0 ? 0 : Math.round((this.faceSamples / this.totalSamples) * 100),
      flags: this.flagCount,
    };
  }

  private lastEnd = 0;

  private endSegment(endMs: number): void {
    this.lastEnd = endMs;
    const dur = endMs - this.segStart;
    if (dur < this.cfg.MIN_SPEECH_MS) return;

    const inside = this.points.filter((p) => p.t >= this.segStart && p.t <= endMs && p.open !== null);
    if (inside.length < 4) return; // no face for most of it: nothing to compare
    const active = inside.filter((p) => this.movingAt(p.t)).length;
    const share = active / inside.length;
    this.speechMs += dur;
    this.matchedMs += dur * share;

    if (dur >= this.cfg.NO_LIPS_MIN_MS && inside.length >= 8 && share < this.cfg.NO_LIPS_MAX_ACTIVE) {
      this.flag({ kind: "lip_voice_mismatch", atMs: this.segStart, secs: Math.round(dur / 1000), pct: Math.round(share * 100) });
    }
    this.checkLag(endMs);
  }

  private checkLag(atMs: number): void {
    if (this.lags.length < this.cfg.LAG_MIN_ONSETS) return;
    const recent = this.lags.slice(-12);
    const sorted = [...recent].sort((a, b) => a - b);
    const median = sorted[Math.floor(sorted.length / 2)];
    const late = recent.filter((l) => l > 250).length / recent.length;
    if (median >= this.cfg.LAG_FLAG_MS && late >= this.cfg.LAG_FLAG_SHARE) {
      this.flag({ kind: "lip_lag", atMs, lagMs: Math.round(median), pct: Math.round(late * 100), n: recent.length });
    }
  }

  private closeLipsRun(tMs: number): void {
    const run = this.lipsRun;
    this.lipsRun = null;
    if (run === null) return;
    const total = run.samples + run.speech;
    const dur = tMs - run.start;
    if (dur >= this.cfg.LIPS_ONLY_MIN_MS && total > 0 && run.speech / total <= this.cfg.LIPS_ONLY_MAX_SPEECH) {
      this.flag({ kind: "lip_no_voice", atMs: run.start, secs: Math.round(dur / 1000) });
    }
  }

  private flag(flag: LipFlag): void {
    const times = this.emitted[flag.kind];
    if (times.length >= this.cfg.MAX_FLAGS_PER_KIND) return;
    if (times.length > 0 && flag.atMs - times[times.length - 1] < this.cfg.FLAG_GAP_MS) return;
    times.push(flag.atMs);
    this.flagCount += 1;
    this.onFlag(flag);
  }

  private trim(tMs: number): void {
    while (this.points.length > 0 && tMs - this.points[0].t > 30_000) this.points.shift();
  }

  /** Is the mouth moving in the window ending at the latest sample? */
  private isMoving(): boolean {
    return this.movingAt(this.points[this.points.length - 1]?.t ?? 0);
  }

  private movingAt(tMs: number): boolean {
    const idx = this.points.findIndex((p) => p.t >= tMs);
    const end = idx === -1 ? this.points.length : idx + 1;
    const window = this.points.slice(Math.max(0, end - this.cfg.MOUTH_WINDOW), end).filter((p) => p.open !== null) as { open: number; shape: number }[];
    if (window.length < 3) return false;
    const range = (pick: (p: { open: number; shape: number }) => number) => Math.max(...window.map(pick)) - Math.min(...window.map(pick));

    return range((p) => p.open) > this.cfg.OPEN_RANGE || range((p) => p.shape) > this.cfg.SHAPE_RANGE;
  }

  /** Was the mouth still for the STILL_MS before this moment? */
  private mouthWasStill(tMs: number): boolean {
    const before = this.points.filter((p) => p.t >= tMs - this.cfg.STILL_MS && p.t < tMs && p.open !== null) as { open: number }[];
    if (before.length < 3) return false;

    return Math.max(...before.map((p) => p.open)) - Math.min(...before.map((p) => p.open)) < 0.04;
  }

  /** Has the mouth just opened above where it was a moment ago? */
  private openedAt(tMs: number): boolean {
    const recent = this.points.filter((p) => p.t >= tMs - 800 && p.t <= tMs && p.open !== null) as { t: number; open: number }[];
    if (recent.length < 3) return false;
    const latest = recent[recent.length - 1];
    const floor = Math.min(...recent.slice(0, -1).map((p) => p.open));

    return latest.open - floor > this.cfg.ONSET_RISE;
  }
}
