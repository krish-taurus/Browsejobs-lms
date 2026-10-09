import type { Metadata } from "next";
import type { ReactNode } from "react";
import { PortalShell } from "@/components/taurus/PortalShell";

export const metadata: Metadata = {
  title: "Taurus workspace",
  robots: { index: false, follow: false },
};

export default function TaurusPortalLayout({ children }: { children: ReactNode }) {
  return <PortalShell>{children}</PortalShell>;
}
