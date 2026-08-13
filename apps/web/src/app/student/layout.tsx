import type { ReactNode } from "react";
import { NO_INDEX } from "@/lib/seo";

/** Student sign-in — a client-rendered OTP form with nothing to index. */
export const metadata = NO_INDEX;

export default function StudentLoginLayout({ children }: { children: ReactNode }) {
  return <>{children}</>;
}
