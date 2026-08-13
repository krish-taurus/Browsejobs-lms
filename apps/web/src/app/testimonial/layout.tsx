import type { ReactNode } from "react";
import { NO_INDEX } from "@/lib/seo";

/**
 * The candidate review gate is reached from a private link and can hold
 * unpublished, below-threshold feedback. It must never be indexed.
 */
export const metadata = NO_INDEX;

export default function TestimonialLayout({ children }: { children: ReactNode }) {
  return <>{children}</>;
}
