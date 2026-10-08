export const SCENE_IDS = ["ring", "score", "sphere", "eclipse", "globe", "grid"] as const;
export type SceneId = (typeof SCENE_IDS)[number];

export const SCENE_ANCHORS = ["center", "top", "right", "halo", "behind"] as const;
export type SceneAnchor = (typeof SCENE_ANCHORS)[number];
export type ScenePins = "cities" | "claims";

export type SceneSnapshot = {
  scene: SceneId;
  progress: number;
  anchor: SceneAnchor;
  pins: ScenePins;
  /** False when the section in view has no object of its own. */
  shown: boolean;
};

const SERVER_SNAPSHOT: SceneSnapshot = { scene: "ring", progress: 0, anchor: "center", pins: "cities", shown: true };
let snapshot: SceneSnapshot = SERVER_SNAPSHOT;
const listeners = new Set<() => void>();

export function isSceneAnchor(value: string | undefined): value is SceneAnchor {
  return !!value && (SCENE_ANCHORS as readonly string[]).includes(value);
}

export function getSceneState(): SceneSnapshot {
  return snapshot;
}

export function getServerSceneState(): SceneSnapshot {
  return SERVER_SNAPSHOT;
}

export function subscribeScene(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

/** Drop the canvas object when the section in view does not own one. */
export function hideScene(): void {
  if (!snapshot.shown) return;
  snapshot = { ...snapshot, shown: false };
  listeners.forEach((listener) => listener());
}

export function setSceneState(
  scene: SceneId,
  progress: number,
  extra?: { anchor?: SceneAnchor; pins?: ScenePins; shown?: boolean },
): void {
  const next = Math.min(1, Math.max(0, progress));
  const anchor = extra?.anchor ?? "center";
  const pins = extra?.pins ?? "cities";
  const shown = extra?.shown ?? true;
  if (
    snapshot.scene === scene &&
    snapshot.anchor === anchor &&
    snapshot.pins === pins &&
    snapshot.shown === shown &&
    Math.abs(snapshot.progress - next) < 0.01
  ) {
    return;
  }
  snapshot = { scene, progress: next, anchor, pins, shown };
  listeners.forEach((listener) => listener());
}
