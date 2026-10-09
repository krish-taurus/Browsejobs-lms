"use client";

import { useMemo } from "react";
import { opsDemo, recruitmentDemo } from "@/lib/taurus-floor/demo";
import { hiringStory } from "@/lib/taurus-floor/hiring-story";
import { TaurusConsole } from "./TaurusConsole";

type DemoMode = "ops" | "recruitment" | "story";

/** The public, simulated Taurus floors used on /taurusai pages. Sample data only. */
export function TaurusDemo({ mode }: { mode: DemoMode }) {
  const source = useMemo(() => (mode === "ops" ? opsDemo() : mode === "story" ? hiringStory() : recruitmentDemo()), [mode]);
  return (
    <TaurusConsole
      source={source}
      mode={mode === "ops" ? "ops" : "recruitment"}
      variant="hero"
      title={mode === "ops" ? "TAURUS" : "TAURUS · HIRING"}
      subtitle={mode === "ops" ? "Agent command centre" : mode === "story" ? "One role, start to finish" : "Hiring floor · 4 open roles"}
      askPlaceholder="Ask Taurus: what needs me right now?"
    />
  );
}
