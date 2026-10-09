import Link from "next/link";
import { JobIcon } from "@/components/jobs/JobIcons";
import { FOCUS_RING } from "@/components/jobs/ReadinessBanner";

const STEPS = [
  { title: "Take the AI interview", body: "Show your skills for the role." },
  { title: "Unlock your application", body: "Finish the interview to apply." },
  { title: "Get considered", body: "Strong matches can be shortlisted automatically." },
];

/** "Your path to an offer" — the three steps a direct-hiring role takes. */
export function CareerGuidanceCard() {
  return (
    <section aria-labelledby="path-title" className="rounded-[18px] border border-line bg-white p-6 shadow-soft">
      <h2 id="path-title" className="display text-lg text-ink">Your path to an offer</h2>
      <ol className="mt-5">
        {STEPS.map((step, i) => (
          <li key={step.title} className="relative flex gap-4 pb-6 last:pb-0">
            {i < STEPS.length - 1 && (
              <span aria-hidden="true" className="absolute left-[17px] top-10 bottom-1 border-l-2 border-dashed border-line" />
            )}
            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full border-2 border-trust bg-white text-sm font-semibold text-trust">
              {i + 1}
            </span>
            <div className="pt-1">
              <p className="text-[15px] font-semibold text-ink">{step.title}</p>
              <p className="mt-1 text-sm text-muted">{step.body}</p>
            </div>
          </li>
        ))}
      </ol>
      <div className="mt-6 flex gap-3 rounded-xl bg-sky p-4">
        <JobIcon name="shieldCheck" className="h-6 w-6 shrink-0 text-trust" />
        <div>
          <p className="text-sm font-semibold text-ink">Two different interviews</p>
          <p className="mt-1 text-[13px] text-muted">Readiness builds your profile. Each job has its own interview to unlock Apply.</p>
        </div>
      </div>
    </section>
  );
}

/** CV tip — points at My CV, and names what's missing when the CV is thin. */
export function UpdateCVCard({ missing }: { missing: string[] }) {
  return (
    <section aria-labelledby="cv-tip-title" className="rounded-[18px] border border-line bg-white p-6 shadow-soft">
      <div className="flex gap-4">
        <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-sky text-trust">
          <JobIcon name="lightbulb" className="h-6 w-6" />
        </span>
        <div>
          <h2 id="cv-tip-title" className="text-[15px] font-semibold text-ink">
            {missing.length > 0 ? "Finish your CV" : "Make your skills count"}
          </h2>
          <p className="mt-1 text-sm text-muted">
            {missing.length > 0
              ? `Your CV is missing ${missing.join(", ")}. It's what an employer opens the moment you apply.`
              : "Keep your CV updated so your matches reflect your experience."}
          </p>
          <Link href="/cv" className={`mt-3 inline-flex min-h-[44px] items-center gap-1.5 rounded-full text-sm font-semibold text-trust hover:underline ${FOCUS_RING}`}>
            {missing.length > 0 ? "Complete my CV" : "Update my CV"} <JobIcon name="arrowRight" className="h-4 w-4" />
          </Link>
        </div>
      </div>
    </section>
  );
}
