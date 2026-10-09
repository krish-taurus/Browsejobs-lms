/**
 * Hand-written types for floor.mjs (plain JS, see the header there) — the
 * narrow surface React uses, so we don't need @types/three.
 */

export type FloorLook = "obsidian" | "titanium" | "noir";
export type FloorStatus = "working" | "thinking" | "needs" | "error" | "idle" | "offline";

export type FloorZone = { key: string; label: string };

export type FloorAgent = {
  id: string;
  name: string;
  zone: string;
  status: FloorStatus;
  task?: string | null;
  progress?: number;
  /** When status is "needs": the action awaiting a human decision. */
  approval?: string | null;
};

export type FloorOptions = {
  look?: FloorLook;
  zones?: FloorZone[];
  reducedMotion?: boolean;
  interactive?: boolean;
  /** Scroll-to-zoom without a modifier key. Off on marketing pages so the page still scrolls. */
  wheelZoom?: boolean;
  showZoneLabels?: boolean;
  numberZones?: boolean;
  /** Pixels from the top that open approval cards must stay below (clear of the HUD). */
  topSafe?: () => number;
  onSelect?: (id: string) => void;
  onApprove?: (id: string) => void;
  onDelivered?: (id: string) => void;
  onError?: (error: Error) => void;
};

export type TaurusFloor = {
  setLook(look: FloorLook, intro?: boolean): void;
  sync(agents: FloorAgent[]): void;
  setZones(zones: FloorZone[]): void;
  deliver(id: string): void;
  stream(id: string, count?: number, color?: string): void;
  flow(fromZone: string, toZone: string, count?: number): void;
  focus(id: string, seconds?: number): void;
  pulse(): void;
  dispose(): void;
};

export const FLOOR_LOOKS: Record<FloorLook, unknown>;

export function createTaurusFloor(container: HTMLElement, options?: FloorOptions): TaurusFloor;
