import type { Metadata } from "next";
import Link from "next/link";
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
    <main className="fixed inset-0 bg-ink">
      <h1 className="sr-only">Taurus hiring demo: one role, start to finish</h1>
      <TaurusDemo mode="story" />
      <Link
        href="/taurusai/recruitment"
        className="mono absolute right-4 top-[72px] z-10 rounded-full border border-white/20 bg-black/40 px-3 py-1.5 text-[11px] text-white/80 backdrop-blur hover:text-white md:right-6 md:top-[78px]"
      >
        About Taurus hiring →
      </Link>
    </main>
  );
}
