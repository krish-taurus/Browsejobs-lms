import type { ReactNode } from "react";
import { AuthProvider } from "@/lib/auth";
import { PortalShell } from "@/components/portal/PortalShell";
import { parseLockedFeatures } from "@/lib/locked-features";

// The lock list is read per request rather than baked into the build, so
// switching a feature on or off is an .env edit plus a service restart.
export const dynamic = "force-dynamic";

export default function PortalLayout({ children }: { children: ReactNode }) {
  const lockedFeatures = parseLockedFeatures(process.env.LOCKED_FEATURES);

  return (
    <AuthProvider>
      <PortalShell lockedFeatures={lockedFeatures}>{children}</PortalShell>
    </AuthProvider>
  );
}
