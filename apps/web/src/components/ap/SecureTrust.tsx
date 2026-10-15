import Link from "next/link";
import { IntegrityDemo } from "@/components/seo/IntegrityDemo";

const COPY = {
  employers: {
    eyebrow: "Why teams trust it",
    heading: "An interview you can believe.",
    lead: "Remote interviews are easy to game: a hidden second screen, a prompter off camera, a voice that is not the face. BrowseJobs interviews are built to read gaze, lip-sync and speaker audio live, and to hand you the evidence. You decide.",
  },
  home: {
    eyebrow: "Why your pass counts",
    heading: "Interviews employers trust.",
    lead: "Every BrowseJobs interview is built to check that the answers are yours: where you look, whether voice and lips match, whether anyone else is helping. So when you clear 75%, recruiters know it was earned.",
  },
} as const;

/** Shared trust band for the home page and /employers. Same demo and same fine print on both, so the claim cannot drift. */
export function SecureTrust({ audience }: { audience: keyof typeof COPY }) {
  const c = COPY[audience];
  return (
    <section className="s-black chapter" id="secure">
      <div className="wrap center">
        <p className="eyebrow">{c.eyebrow}</p>
        <h2 className="h-section">{c.heading}</h2>
        <p className="lead">{c.lead}</p>
        <div className="cta-row">
          <Link className="btn btn-primary" href="/secure-interview">
            How we keep it honest
          </Link>
        </div>
      </div>
      <div className="wrap" style={{ maxWidth: 1000 }}>
        <IntegrityDemo />
      </div>
      <div className="wrap center">
        <p className="fine">
          Staged demonstration. The integrity layer is in pilot: today&apos;s interviews do not yet capture camera or
          window-switch checks. A flag sends the interview to a person to review. It never rejects anyone on its own.
        </p>
      </div>
    </section>
  );
}
