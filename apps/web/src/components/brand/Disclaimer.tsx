import { DISCLAIMER } from "@/content/landing";

/**
 * The mandatory stat disclaimer (spec §3.4). MUST render immediately after any
 * stat band, outcome figure, or salary number. Muted mono, never paraphrased.
 */
export function Disclaimer({ className = "", tone = "default" }: { className?: string; tone?: "default" | "argus" }) {
  const base = tone === "argus" ? "argus-disclaimer" : "mono text-[11px] leading-relaxed text-muted";
  return <p className={`${base} ${className}`}>{DISCLAIMER}</p>;
}
