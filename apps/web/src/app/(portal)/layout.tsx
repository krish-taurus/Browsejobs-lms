import type { ReactNode } from "react";
import { AuthProvider } from "@/lib/auth";
import { PortalShell } from "@/components/portal/PortalShell";

export default function PortalLayout({ children }: { children: ReactNode }) {
  return (
    <AuthProvider>
      <PortalShell>{children}</PortalShell>
    </AuthProvider>
  );
}
