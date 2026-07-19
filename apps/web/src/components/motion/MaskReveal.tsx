import type { ReactNode } from "react";

/**
 * Headline mask reveal: each line rises out of an invisible box on load, in
 * sequence — pure CSS (SSR-identical, hydration-proof). The keyframes live in
 * globals.css and the reduced-motion media query disables them entirely.
 */
export function MaskReveal({
  children,
  index = 0,
  className = "",
}: {
  children: ReactNode;
  /** Line number — sets the stagger delay. */
  index?: number;
  className?: string;
}) {
  return (
    <span className={`block overflow-hidden ${className}`}>
      <span className="mask-rise block" style={{ animationDelay: `${index * 90}ms` }}>
        {children}
      </span>
    </span>
  );
}
