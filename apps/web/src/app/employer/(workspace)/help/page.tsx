import { PageHead, Tile, Label } from "@/components/employer/ui";

const FAQS = [
  {
    q: "How does the AI write a job description?",
    a: "Describe the role from the dashboard — spoken or typed. It drafts the JD, a matching mock interview and a grading rubric together, so every applicant is scored against the same bar automatically.",
  },
  {
    q: "Can I edit a JD after it's posted?",
    a: "Yes, from the job's own page. Changing the required skills regenerates its mock interview to match, so the two never drift apart.",
  },
  {
    q: "How is an applicant's score calculated?",
    a: "Each interview is graded against the rubric attached to that JD at the time they applied — never against a generic template.",
  },
  {
    q: "Who can see applicant data in my workspace?",
    a: "Anyone you've added under Team, scoped to their role there. Remove someone's access any time from that page.",
  },
];

export default function EmployerHelpPage() {
  return (
    <div className="space-y-5 pb-10">
      <PageHead kicker="Support" title="Help & support" sub="Answers to what comes up most, and a direct line for everything else." />

      <div className="grid gap-3">
        {FAQS.map((f) => (
          <Tile key={f.q} hover={false}>
            <p className="font-display text-[15px] font-bold text-[#050505]">{f.q}</p>
            <p className="mt-2 text-sm leading-relaxed text-[#65676b]">{f.a}</p>
          </Tile>
        ))}
      </div>

      <Tile hover={false}>
        <Label>Still stuck?</Label>
        <p className="mt-2 max-w-lg text-sm leading-relaxed text-[#65676b]">
          Write in and a real person from BrowseJobs will get back to you — usually within a
          business day.
        </p>
        <a
          href="mailto:support@browsejobs.ai?subject=Employer%20support"
          className="mt-4 inline-flex items-center gap-2 rounded-full bg-[#1877f2] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#166fe5]"
        >
          Email support@browsejobs.ai
        </a>
      </Tile>
    </div>
  );
}
