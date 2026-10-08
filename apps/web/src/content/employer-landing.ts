/**
 * Public /employers page. Copy is Dr Krish Bharggav's hiring story.
 *
 * Numbers allowed on this page: 90 days → 3 days, 75% clear, "30 years" inside
 * his positioning sentence, and the fictional scores inside the labelled
 * sample report. Do not add salaries, client logos, testimonials, or other rates.
 *
 * Hiring enquiries go to /employers/enquire. The published hello@ address
 * stays in the footer for everything else.
 */

export const EMPLOYER_META = {
  path: "/employers",
  title: "Hire in 3 days, not 90",
  description:
    "Share a role on WhatsApp. Bots screen applicants, run a proctored AI interview, and send a performance report with the CV. Hiring time drops from 90 days to 3 days.",
} as const;

export const EMPLOYER_WAYS = [
  {
    id: "partner",
    title: "Onboard BrowseJobs as your hiring partner",
    body: "We run hiring with you. You share the role on WhatsApp. The bots screen, interview, verify, and keep the candidate in the loop. You meet people who already cleared.",
    cta: "Onboard us as your hiring partner",
    href: "/employers/enquire?path=partner",
    primary: true,
  },
  {
    id: "tool",
    title: "Use our tool for your own hiring",
    body: "Your team runs the same bots on the roles you hire for. You stay in charge. The interview, the report, and the WhatsApp updates still happen.",
    cta: "Use our tool for your own hiring",
    href: "/employers/enquire?path=tool",
    primary: false,
  },
] as const;

export const EMPLOYER_BOTS = [
  {
    id: "screening",
    mark: "A",
    name: "Screening Bot",
    body: "Screens applicants for the role you shared.",
  },
  {
    id: "interview",
    mark: "B",
    name: "Interview Bot",
    body: "Runs the proctored AI interview and scores it. 75% or more counts as clear.",
  },
  {
    id: "bgv",
    mark: "C",
    name: "BGV Bot",
    body: "Runs background verification.",
  },
  {
    id: "chat",
    mark: "D",
    name: "Candidate Interaction Bot",
    body: "Chats with the candidate on WhatsApp. It keeps them in the loop until they join, and after they join. That cuts drop-offs and no-shows.",
  },
] as const;

export const HIRING_JOURNEY = [
  {
    kicker: "Day 0",
    title: "Role shared on WhatsApp",
    body: "You send the role in a WhatsApp message. The bots take it from there.",
  },
  {
    kicker: "Screening Bot",
    title: "Applicants are screened",
    body: "The bot reads who applied and keeps the people who fit the role.",
  },
  {
    kicker: "Interview Bot",
    title: "Proctored AI interview",
    body: "The bot runs the interview and scores it. 75% or more counts as clear.",
  },
  {
    kicker: "Report",
    title: "Report and CV, delivered",
    body: "You get a full performance report and the CV. You are not opening a surprise.",
  },
  {
    kicker: "You",
    title: "You meet pre-qualified people",
    body: "You interview only candidates who already cleared.",
  },
  {
    kicker: "BGV Bot",
    title: "Background verification",
    body: "The bot runs the background check.",
  },
  {
    kicker: "Offer",
    title: "You make the offer",
    body: "The hire is your decision.",
  },
  {
    kicker: "Candidate Interaction Bot",
    title: "In the loop until they join, and after",
    body: "The bot keeps chatting on WhatsApp so the candidate knows what happens next. That continues after they join.",
  },
  {
    kicker: "~Day 3",
    title: "The short path is done",
    body: "The old way is 90 days of manual calls and surprises. This path is 3 days on WhatsApp bots.",
  },
] as const;

export const EMPLOYER_FAQ = [
  {
    q: "How does hiring go from 90 days to 3 days?",
    a: "You share the role on WhatsApp on day 0. Screening, a proctored AI interview, the report, your interview with people who already cleared, background verification, and the offer follow on the bot path. That path is about 3 days. The old way is 90 days of manual calls and surprises.",
  },
  {
    q: "What does a clear score mean?",
    a: "The Interview Bot scores the proctored AI interview. 75% or more counts as clear. You interview candidates who cleared. A score under that is not sent to you as a clear.",
  },
  {
    q: "What do we actually receive?",
    a: "A full performance report and the CV. The report has the overall score, a skill breakdown, a proctoring summary, communication notes, strengths and risks, transcript excerpts, and BGV status. The sample on the how-it-works page is an example, not a real person.",
  },
  {
    q: "Are the interviews proctored?",
    a: "Yes. Every interview is fully proctored. The report says what the checks saw, such as face present, no tab switches, and no second voice.",
  },
  {
    q: "What do the four bots do?",
    a: "The Screening Bot screens applicants. The Interview Bot runs the proctored AI interview and scores it. The BGV Bot runs background verification. The Candidate Interaction Bot chats on WhatsApp and keeps the candidate in the loop until they join, and after they join.",
  },
  {
    q: "Can we hire with you, or use the tool ourselves?",
    a: "Both. You can onboard BrowseJobs as your hiring partner, and we run the path with you. Or your team can use the tool for your own hiring. The two links on the employers page tell us which one you want.",
  },
  {
    q: "Do you guarantee a hire?",
    a: "Nobody can guarantee a hire. The market decides. What changes is the process: pre-vetted, pre-interviewed candidates, a written report, and WhatsApp bots instead of manual chasing.",
  },
] as const;

/** Fictional example. Every figure here is an illustration inside the labelled sample. */
export const SAMPLE_REPORT = {
  label: "Sample report: example candidate",
  note: "This is an example. Not a real person, and not a real company.",
  name: "Sample Candidate",
  role: "Data Engineer",
  score: 82,
  scoreMax: 100,
  result: "Clear",
  barNote: "75% or more counts as clear. This example scored 82.",
  skills: [
    { label: "SQL", score: 88 },
    { label: "Pipeline design", score: 84 },
    { label: "Communication", score: 76 },
    { label: "Problem solving", score: 71 },
  ],
  proctoring: ["Face present", "No tab switches", "No second voice", "One person in frame"],
  communication:
    "Answers were direct. The candidate named the steps, then the check that would catch a bad file.",
  strengths: [
    "Explained a pipeline in order: land, check, load.",
    "Named who uses the warehouse.",
  ],
  risks: ["Light on what happens when a file arrives late."],
  transcript: [
    {
      who: "Interview Bot",
      text: "Walk me through a pipeline you would ship this month.",
    },
    {
      who: "Sample Candidate",
      text: "I would land the raw files, check them, and load a warehouse the analyst can query.",
    },
  ],
  bgv: "Example status: checks started. Education and the last employer are listed on the CV. This is not a finished verification of a real person.",
  cv: {
    headline: "Data Engineer",
    summary: "Builds pipelines that land files, check them, and load a warehouse.",
    roles: [
      { company: "Example Retail Co.", title: "Data Engineer", when: "Recent example role" },
      { company: "Example Logistics Ltd.", title: "Analytics Engineer", when: "Earlier example role" },
    ],
    skills: ["SQL", "Python", "Warehouses"],
  },
} as const;
