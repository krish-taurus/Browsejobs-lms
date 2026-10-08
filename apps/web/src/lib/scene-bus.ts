export const SCENE_IDS = ["ring", "score", "sphere", "eclipse", "globe", "grid"] as const;
export type SceneId = (typeof SCENE_IDS)[number];
export type SceneSnapshot = { scene: SceneId; progress: number };

const SERVER_SNAPSHOT: SceneSnapshot = { scene: "ring", progress: 0 };
let snapshot: SceneSnapshot = SERVER_SNAPSHOT;
const listeners = new Set<() => void>();

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

export function setSceneState(scene: SceneId, progress: number): void {
  const next = Math.min(1, Math.max(0, progress));
  if (snapshot.scene === scene && Math.abs(snapshot.progress - next) < 0.01) return;
  snapshot = { scene, progress: next };
  listeners.forEach((listener) => listener());
}
