import type { ReactNode } from "react";
import { ArgusFooter, ArgusNav } from "./ui";
import "./argus.css";

export function ArgusFrame({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div className={className ? `argus ${className}` : "argus"}>
      <a href="#content" className="argus-skip">
        Skip to content
      </a>
      <div className="argus-glow" aria-hidden />
      <div className="argus-grain" aria-hidden />
      <div className="argus-mobile-glow" aria-hidden />
      <ArgusNav />
      <main id="content" className="argus-content">
        {children}
      </main>
      <ArgusFooter />
    </div>
  );
}
