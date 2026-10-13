import Link from "next/link";
import { TelLink, WhatsAppLink } from "@/components/contact/PhoneLinks";
import { TaurusDemo } from "@/components/taurus/TaurusDemo";
import { EMPLOYER_PHONE } from "@/content/phones";

/**
 * The employer demo plays the Taurus hiring story on the 3D floor
 * (Obsidian / Titanium / Noir looks) — the same story as /taurusai/hiring-demo,
 * kept at this URL because the employer pages and /demo link here.
 */
export default function MissionControlDemoPage() {
  return (
    <main className="fixed inset-0 bg-ink">
      <h1 className="sr-only">BrowseJobs AI Recruiter demo: one role, start to finish</h1>
      <TaurusDemo mode="story" />
      <p className="mono absolute left-4 top-[72px] z-10 flex gap-2 text-[11px] text-white/80 md:left-6 md:top-[78px]">
        <TelLink
          phone={EMPLOYER_PHONE}
          className="rounded-full border border-white/20 bg-black/40 px-3 py-1.5 backdrop-blur hover:text-white"
        />
        <WhatsAppLink
          phone={EMPLOYER_PHONE}
          className="rounded-full border border-white/20 bg-black/40 px-3 py-1.5 backdrop-blur hover:text-white"
        />
      </p>
      <Link
        href="/employers"
        className="mono absolute right-4 top-[72px] z-10 rounded-full border border-white/20 bg-black/40 px-3 py-1.5 text-[11px] text-white/80 backdrop-blur hover:text-white md:right-6 md:top-[78px]"
      >
        ← Back to employers
      </Link>
    </main>
  );
}
