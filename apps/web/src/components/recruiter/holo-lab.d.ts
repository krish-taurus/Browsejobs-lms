export type HoloAgentSpec = {
  id: string;
  name: string;
  zone: string;
  status: string;
  task?: string;
  progress?: number;
  platform?: string;
  role?: string;
  color?: string | null;
  token?: boolean;
  callsign?: string;
  example?: boolean;
};

export type HoloLab = {
  sync: (list: HoloAgentSpec[], counts?: Record<string, number>) => void;
  resize: () => void;
  recenter: () => void;
  setSheet: (open: boolean) => void;
  select: (id: string | null) => void;
  setPortrait: (node: HTMLCanvasElement | null) => void;
  destroy: () => void;
};

export function createHoloLab(
  canvas: HTMLCanvasElement,
  hooks?: {
    getInsets?: (w: number, h: number) => { l: number; r: number; t: number; b: number };
    onSelect?: (agent: { id: string } | null) => void;
    reduced?: () => boolean;
  },
): HoloLab;
