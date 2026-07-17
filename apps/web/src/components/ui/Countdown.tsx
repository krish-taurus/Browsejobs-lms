"use client";

import { useEffect, useState } from "react";

/**
 * A display-only countdown to a server-provided deadline (PRD §6.5). The server is
 * authoritative on whether a submission is late; this only shows time remaining and
 * fires `onExpire` once. Turns warn-red under a minute.
 */
export function Countdown({ deadline, onExpire }: { deadline: string; onExpire?: () => void }) {
  const target = new Date(deadline).getTime();
  const [remaining, setRemaining] = useState<number>(() => Math.max(0, target - Date.now()));

  useEffect(() => {
    let fired = false;
    const tick = () => {
      const ms = Math.max(0, target - Date.now());
      setRemaining(ms);
      if (ms <= 0 && !fired) {
        fired = true;
        onExpire?.();
      }
    };
    tick();
    const id = setInterval(tick, 500);
    return () => clearInterval(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [deadline]);

  const totalSec = Math.ceil(remaining / 1000);
  const mm = String(Math.floor(totalSec / 60)).padStart(2, "0");
  const ss = String(totalSec % 60).padStart(2, "0");
  const urgent = totalSec <= 60;

  return (
    <span
      className={`mono rounded-full px-3 py-1 text-sm font-semibold tabular-nums ${
        urgent ? "bg-warn/10 text-warn" : "bg-sky text-deep"
      }`}
      aria-label="Time remaining"
    >
      {mm}:{ss}
    </span>
  );
}
