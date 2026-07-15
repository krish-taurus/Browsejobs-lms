import { DISCLAIMER } from "@/content/landing";

/**
 * The mandatory stat disclaimer (spec §3.4). MUST render immediately after any
 * stat band, outcome figure, or salary number. Muted mono, never paraphrased.
 */
export function Disclaimer({ className = "" }: { className?: string }) {
  return (
    <p className={`mono text-[11px] leading-relaxed text-muted ${className}`}>
      {DISCLAIMER}
    </p>
  );
}
