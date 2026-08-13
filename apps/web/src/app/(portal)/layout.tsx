import type { ReactNode } from "react";
import { AuthProvider } from "@/lib/auth";
import { PortalShell } from "@/components/portal/PortalShell";
import { NO_INDEX } from "@/lib/seo";

/** Signed-in surface: every route below renders an auth shell to a crawler. */
export const metadata = NO_INDEX;

export default function PortalLayout({ children }: { children: ReactNode }) {
  return (
    <AuthProvider>
      <PortalShell>{children}</PortalShell>
    </AuthProvider>
  );
}
