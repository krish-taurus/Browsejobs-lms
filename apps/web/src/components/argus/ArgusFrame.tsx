import type { ReactNode } from "react";
import { ArgusFooter, ArgusNav } from "./ui";
import "./argus.css";

export function ArgusFrame({ children }: { children: ReactNode }) {
  return (
    <div className="argus">
      <div className="argus-glow" aria-hidden />
      <div className="argus-grain" aria-hidden />
      <div className="argus-mobile-glow" aria-hidden />
      <ArgusNav />
      <div className="argus-content">{children}</div>
      <ArgusFooter />
    </div>
  );
}
