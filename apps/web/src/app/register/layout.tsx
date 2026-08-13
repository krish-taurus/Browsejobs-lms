import type { ReactNode } from "react";
import { NO_INDEX } from "@/lib/seo";

/**
 * The sign-up page is a client-rendered OTP form, so it exports no metadata of
 * its own — this layout carries the noindex. Marketing traffic still lands here
 * happily; there is simply nothing for a crawler to rank.
 */
export const metadata = NO_INDEX;

export default function RegisterLayout({ children }: { children: ReactNode }) {
  return <>{children}</>;
}
