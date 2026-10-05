/**
 * Hand-written types for the supplied robot modules (plain JS, no bundled
 * .d.ts) — narrow to exactly the API documented in ROBOT-INTEGRATION.md
 * rather than pulling in @types/three for a surface this small.
 */

export type TaurusRobotState = "idle" | "listening" | "thinking" | "speaking" | "error";

export type TaurusView = {
  robot: unknown;
  setState(state: TaurusRobotState): void;
  setSpeechEnergy(value: number): void;
  wave(): void;
  dispose(): void;
};

export function mountTaurus(
  container: HTMLElement,
  options?: { onError?: (error: Error) => void }
): TaurusView;
