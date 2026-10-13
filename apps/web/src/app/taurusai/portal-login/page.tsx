import type { Metadata } from "next";
import { TaurusLoginForm } from "@/components/taurus/PortalAuthForms";

export const metadata: Metadata = {
  title: "Sign in to Taurus",
  robots: { index: false, follow: false },
};

export default function TaurusLoginPage() {
  return <TaurusLoginForm />;
}
