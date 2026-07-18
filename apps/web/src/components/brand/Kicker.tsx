import type { ReactNode } from "react";

/**
 * The signature mono uppercase kicker (design system). `tone="verify"` for
 * free/verified sections only — never decorative.
 */
export function Kicker({
  children,
  tone = "trust",
  className = "",
}: {
  children: ReactNode;
  tone?: "trust" | "verify" | "muted" | "sky";
  className?: string;
}) {
  const color =
    tone === "verify" ? "text-verify" : tone === "muted" ? "text-muted" : tone === "sky" ? "text-sky/70" : "text-trust";
  return <p className={`kicker ${color} ${className}`}>{children}</p>;
}
