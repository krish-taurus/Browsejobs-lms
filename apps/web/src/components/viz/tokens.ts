/**
 * Dark command-deck visualisation tokens.
 *
 * The categorical order below is not a taste call — it was produced by
 * stepping candidates through the palette validator against the dark chart
 * surface (#0a0f1c) until every check passed: OKLCH lightness inside the
 * dark band (0.48–0.67), chroma floor, colour-vision-deficiency separation
 * on every adjacent pair, normal-vision separation, and 3:1 contrast
 * against the surface.
 *
 * The blue/violet pairing used earlier failed badly — ΔE 0.6 under protan,
 * which is indistinguishable — so the two are deliberately far apart in the
 * order here and never land on adjacent series.
 *
 * Assign these in fixed order and never cycle. A seventh series folds into
 * "Other" or becomes a small multiple.
 */

export const SURFACE = {
  /** Page canvas — the deepest layer. */
  base: "#05070d",
  /** Card / chart surface the palette was validated against. */
  card: "#0a0f1c",
  /** Raised surface for nested panels. */
  raised: "#111827",
  /** Hairline borders. */
  line: "rgba(255,255,255,0.08)",
  lineStrong: "rgba(255,255,255,0.14)",
} as const;

export const INK = {
  primary: "#f4f7fb",
  secondary: "rgba(244,247,251,0.68)",
  muted: "rgba(244,247,251,0.42)",
  faint: "rgba(244,247,251,0.22)",
} as const;

/** Fixed categorical order. Validated as a set — do not reorder casually. */
export const SERIES = [
  "#4d8ef7", // blue    — the primary measure
  "#0da06e", // green   — verified / kept promise
  "#b07c00", // amber
  "#9d6bf5", // violet
  "#cf4f88", // pink
  "#0a9fc4", // cyan
] as const;

export const SERIES_NAMES = ["blue", "green", "amber", "violet", "pink", "cyan"] as const;

/**
 * Status colours are reserved: never reused as "series 4", and always shipped
 * with a label or icon so state is never carried by colour alone.
 */
export const STATUS = {
  good: "#0da06e",
  warning: "#b07c00",
  critical: "#e05561",
  neutral: "rgba(244,247,251,0.42)",
} as const;

/** Single-hue sequential ramp for magnitude (light → dark reads low → high). */
export const SEQUENTIAL = ["#12305e", "#1b4b90", "#2765c4", "#4d8ef7", "#8ab6fb"] as const;

/** One signature easing across the whole product. */
export const EASE = [0.16, 1, 0.3, 1] as const;

export const DUR = { fast: 0.25, base: 0.5, slow: 0.9, draw: 1.3 } as const;
