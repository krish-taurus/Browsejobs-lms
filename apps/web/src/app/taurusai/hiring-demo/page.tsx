import type { Metadata } from "next";
import Link from "next/link";
import "@/components/ap/pages/taurus-ui.css";
import { taurusDisplayFont } from "@/components/ap/pages/taurus-font";
import { TaurusDemo } from "@/components/taurus/TaurusDemo";
import { absoluteUrl } from "@/lib/seo";

export const metadata: Metadata = {
  title: "Taurus Hiring Demo: One Role, Start to Finish",
  description:
    "Watch Taurus fill a role through WhatsApp: sourcing, CV screening, AI calls, L1 and L2 interviews, pre-BGV, your interview round and the offer. Sample data.",
  alternates: { canonical: absoluteUrl("/taurusai/hiring-demo") },
};

/** Full-screen Taurus hiring story — the "watch how it works" page, also used to record the demo video. */
export default function TaurusHiringDemoPage() {
  return (
    <main className={`tx-ap tx-demo-page ${taurusDisplayFont.variable}`}>
      <h1 className="sr-only">Taurus hiring demo: one role, start to finish</h1>
      <TaurusDemo mode="story" />
      <Link href="/taurusai/recruitment" className="tx-demo-chip" aria-label="About Taurus hiring">
        <span className="tx-demo-chip-long">About Taurus hiring</span>
        <span className="tx-demo-chip-short">About</span> <span aria-hidden="true">›</span>
      </Link>
    </main>
  );
}
