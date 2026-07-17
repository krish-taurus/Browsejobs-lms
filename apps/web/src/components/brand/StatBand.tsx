import { MonoCounter } from "@/components/motion/MonoCounter";
import { Disclaimer } from "@/components/brand/Disclaimer";

export type Stat = {
  value: number;
  label: string;
  prefix?: string;
  suffix?: string;
  decimals?: number;
  plain?: boolean;
};

/**
 * The 4-up mono stat band with rolling counters, ALWAYS followed by the mandatory
 * disclaimer (design system §3.4). `tone="dark"` for ink surfaces.
 */
export function StatBand({
  stats,
  tone = "dark",
  className = "",
}: {
  stats: readonly Stat[];
  tone?: "dark" | "light";
  className?: string;
}) {
  const num = tone === "dark" ? "text-white" : "text-ink";
  const label = tone === "dark" ? "text-sky/70" : "text-muted";
  const border = tone === "dark" ? "border-white/10" : "border-line";
  const disc = tone === "dark" ? "text-sky/50" : "";

  return (
    <div className={className}>
      <div className={`grid grid-cols-2 gap-8 border-t ${border} pt-9 md:grid-cols-4`}>
        {stats.map((s) => (
          <div key={s.label} className="text-center">
            <div className={`display text-3xl md:text-4xl ${num}`}>
              <MonoCounter
                value={s.value}
                prefix={s.prefix ?? ""}
                suffix={s.suffix ?? ""}
                decimals={s.decimals ?? 0}
                plain={s.plain ?? false}
              />
            </div>
            <p className={`mt-2 text-sm ${label}`}>{s.label}</p>
          </div>
        ))}
      </div>
      <div className="mx-auto mt-8 max-w-2xl text-center">
        <Disclaimer className={disc} />
      </div>
    </div>
  );
}
