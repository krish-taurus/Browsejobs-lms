import type { ReactNode } from "react";
import { NO_INDEX } from "@/lib/seo";

/** Superseded landing template, kept for reference — never a search result. */
export const metadata = NO_INDEX;

export default function V2PreviewLayout({ children }: { children: ReactNode }) {
  return <>{children}</>;
}
