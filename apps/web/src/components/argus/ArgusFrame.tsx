import type { ReactNode } from "react";
import { ApShell, type ApCurrent } from "@/components/ap/ApShell";
import "./argus.css";

/**
 * Pages first built on the Argus white theme now sit inside the Apple-direction
 * shell (nav, footer, fonts and tokens from components/ap); their sections keep
 * the .argus content styles, re-tuned by the bridge rules in ap.css.
 */
export function ArgusFrame({ children, className, current }: { children: ReactNode; className?: string; current?: ApCurrent }) {
  const area: ApCurrent | undefined =
    current ?? (className?.includes("student") ? "students" : className?.includes("course") ? "courses" : className?.includes("employ") || className?.includes("enquire") ? "employers" : undefined);
  return (
    <ApShell current={area}>
      <div id="content" className={className ? `argus argus-in-ap ${className}` : "argus argus-in-ap"}>
        {children}
      </div>
    </ApShell>
  );
}
