import type { Metadata } from "next";
import { TaurusClaimForm } from "@/components/taurus/PortalAuthForms";

export const metadata: Metadata = {
  title: "Join your Taurus workspace",
  robots: { index: false, follow: false },
};

export default async function TaurusClaimPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  return <TaurusClaimForm token={token} />;
}
