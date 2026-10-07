import type { ReactNode } from "react";
import { AppleFooter } from "./AppleFooter";
import { AppleNav } from "./AppleNav";
import "./apple.css";

export function AppleShell({ children, dark = false }: { children: ReactNode; dark?: boolean }) {
  return (
    <div className={dark ? "apple-site is-dark" : "apple-site"}>
      <a
        href="#content"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-3 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-[#1d1d1f]"
      >
        Skip to content
      </a>
      <AppleNav dark={dark} />
      <main id="content" className="pt-12">
        {children}
      </main>
      <AppleFooter />
    </div>
  );
}
