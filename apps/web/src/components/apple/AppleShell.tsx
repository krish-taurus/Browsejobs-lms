import type { ReactNode } from "react";
import { ApShell } from "@/components/ap/ApShell";
import "./apple.css";

/** Older Apple-styled pages now share the site-wide Apple-direction shell (components/ap). */
export function AppleShell({ children, dark = false }: { children: ReactNode; dark?: boolean }) {
  return (
    <ApShell>
      <div id="content" className={dark ? "apple-site is-dark" : "apple-site"}>
        {children}
      </div>
    </ApShell>
  );
}
