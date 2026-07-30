import type { ReactNode } from "react";
import { EmployerShell } from "@/components/employer/EmployerShell";

export default function EmployerWorkspaceLayout({ children }: { children: ReactNode }) {
  return <EmployerShell>{children}</EmployerShell>;
}
