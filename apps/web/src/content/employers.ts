/**
 * Employer landing content (/employers). BrowseJobs sells the *process* to
 * hiring teams — the pipeline that runs before their first interview slot is
 * booked. Brand voice rules apply (CLAUDE.md §Brand Voice): no hype adjectives,
 * no guaranteed-hire claims, no fabricated performance statistics. Every number
 * shown on the page is either a process fact or a labelled illustration.
 */

/**
 * Employer-side counterpart to the student DISCLAIMER. Rendered wherever the
 * page shows an example funnel or timeline, so nothing on the page can be read
 * as a performance promise.
 */
export const EMPLOYER_DISCLAIMER =
  "Illustrative example of the process, not a performance claim. Actual volumes, timelines and outcomes depend on your role, your market and your selection bar.";

export const EMPLOYER_TAGLINE = "Interview the shortlist. Not the inbox.";

/* ------------------------------- the pipeline ------------------------------ */

export type PipelineStage = {
  id: string;
  step: string;
  kicker: string;
  title: string;
  /** One-line promise, shown under the title. */
  body: string;
  /** Concrete mechanics — what the stage actually does. */
  points: readonly string[];
  accent: string;
  /** Which demo component renders in the scene. */
  demo: "jd" | "shortlist" | "call" | "rounds" | "ats" | "bgv" | "report";
};

export const PIPELINE: readonly PipelineStage[] = [
  {
    id: "jd",
    step: "01",
    kicker: "Job description",
    title: "Drop the JD. It fills itself in.",
    body: "Paste a JD, upload the file, or start from blank. The AI reads it and completes the structured role — skills, seniority, must-haves, screening criteria. Every field stays editable.",
    points: [
      "Paste, upload (PDF/DOCX) or write it manually",
      "Skills, experience band and location extracted into structured fields",
      "Must-have vs nice-to-have split so screening has a real bar",
      "Nothing is locked — override any field the AI proposes",
    ],
    accent: "#1b6df0",
    demo: "jd",
  },
  {
    id: "shortlist",
    step: "02",
    kicker: "Sourcing & shortlist",
    title: "Ranked against the bar you set.",
    body: "Candidates are matched to the structured role and ranked — with the reason for the rank written next to each one. You see why someone placed where they did.",
    points: [
      "Runs on our candidate pool, your ATS/database, or both",
      "Every rank carries a written justification, not just a score",
      "Hard filters (notice period, location, CTC band) applied before ranking",
      "Reject a candidate and the ranking learns the bar for that role",
    ],
    accent: "#7c3aed",
    demo: "shortlist",
  },
  {
    id: "call",
    step: "03",
    kicker: "AI screening call",
    title: "The first call is placed for you.",
    body: "An AI caller reaches each shortlisted candidate, confirms the basics no CV is honest about, and scores the conversation against the role.",
    points: [
      "Availability, notice period, current and expected CTC, location fit",
      "Role-specific screening questions generated from the JD",
      "Full transcript and recording attached to the candidate",
      "Candidates who fail the screen never reach your calendar",
    ],
    accent: "#0ba860",
    demo: "call",
  },
  {
    id: "rounds",
    step: "04",
    kicker: "Interview rounds",
    title: "L1, L2, or a round you design.",
    body: "Technical, functional or fully custom rounds run on the same rails — conducted by the AI interviewer, your panel, or a mix of both.",
    points: [
      "L1 technical screen with role-weighted scoring",
      "L2 depth round on the stack the JD actually names",
      "Custom rounds: your questions, your rubric, your weightings",
      "Hand any round back to a human panel at any point",
    ],
    accent: "#f5a623",
    demo: "rounds",
  },
  {
    id: "ats",
    step: "05",
    kicker: "ATS & customisation",
    title: "Watch every stage move.",
    body: "One board per role. See where each candidate sits, what happened in each round, and where the pipeline is stalling — live.",
    points: [
      "Stage-by-stage board with the full history per candidate",
      "Per-JD customisation: stages, rubrics, thresholds, auto-advance rules",
      "Recordings, transcripts and scores on every card",
      "Bottleneck view — which stage is holding the role up",
    ],
    accent: "#1b6df0",
    demo: "ats",
  },
  {
    id: "bgv",
    step: "06",
    kicker: "Background verification",
    title: "High-level BGV before you commit.",
    body: "A first-pass verification runs on the finalists — so the checks that usually surface late surface early instead.",
    points: [
      "Employment and education consistency checks",
      "Public professional footprint cross-referenced against the CV",
      "Discrepancies flagged with the source, not a pass/fail verdict",
      "Deep BGV stays with your existing provider — this is the early filter",
    ],
    accent: "#7c3aed",
    demo: "bgv",
  },
  {
    id: "report",
    step: "07",
    kicker: "Handover to HR",
    title: "A written brief lands before you meet anyone.",
    body: "Your HR team receives a detailed report per candidate — every round conducted, every score, every flag — before your hiring process even begins.",
    points: [
      "Round-by-round breakdown with scores and reasoning",
      "Screening call transcript and BGV flags in the same document",
      "A recommendation you can disagree with, with the evidence attached",
      "Your panel starts at the interview, not at CV triage",
    ],
    accent: "#0ba860",
    demo: "report",
  },
] as const;

/* --------------------------- the turnaround story -------------------------- */

/**
 * Before/after framing. These are *process* statements — which work happens on
 * whose desk — not measured time savings, so they carry no numeric claim.
 */
export const TAT_BEFORE = [
  "Post the role, wait for the inbox to fill",
  "Recruiter reads hundreds of CVs by hand",
  "Chase candidates for notice period and CTC",
  "Half the L1 slots are no-shows or mismatches",
  "BGV starts after the offer — surprises land late",
] as const;

export const TAT_AFTER = [
  "JD in, structured role out — same sitting",
  "Ranked shortlist with written reasoning",
  "AI screening call confirms the basics first",
  "L1/L2 run only on candidates who cleared the screen",
  "BGV flags surface before your panel spends an hour",
] as const;

/* -------------------------------- use cases -------------------------------- */

export type UseCase = {
  title: string;
  scenario: string;
  flow: readonly string[];
  outcome: string;
  accent: string;
};

export const USE_CASES: readonly UseCase[] = [
  {
    title: "Volume hiring",
    scenario:
      "A services firm opens 40 QA engineer seats across two cities and has three recruiters to fill them.",
    flow: [
      "One JD in, one structured role out",
      "Pool ranked against the bar, hard filters applied first",
      "AI caller screens the ranked list in parallel",
      "L1 runs automatically; recruiters only join L2",
    ],
    outcome:
      "The recruiting team spends its hours on final rounds instead of CV triage and screening calls.",
    accent: "#1b6df0",
  },
  {
    title: "Niche senior role",
    scenario:
      "A product company needs one staff data engineer and cannot afford a bad hire or a six-month search.",
    flow: [
      "JD tuned by hand after AI extraction — must-haves made strict",
      "Custom L1 built around the exact stack, not a generic screen",
      "Two custom rounds with the hiring manager's own rubric",
      "High-level BGV on the final two before the panel meets them",
    ],
    outcome:
      "A small, defensible shortlist with the reasoning written down for every rejection.",
    accent: "#7c3aed",
  },
  {
    title: "Agency model",
    scenario:
      "A startup has no ATS, no recruiter and needs five engineers before the next funding milestone.",
    flow: [
      "Runs entirely on the BrowseJobs candidate pool",
      "We operate the pipeline end to end",
      "Founder gets the written brief per finalist",
      "Optional CRM configured to how the team actually hires",
    ],
    outcome:
      "The founder only ever meets candidates who have already cleared screening and BGV.",
    accent: "#0ba860",
  },
] as const;

/* ------------------------------ delivery models ---------------------------- */

export const DELIVERY_MODELS = [
  {
    id: "integrated",
    label: "On your data",
    title: "Plug into your stack",
    body: "The pipeline runs against your existing ATS, candidate database and career-site inflow. Your data stays yours; we add the screening layer on top.",
    points: [
      "Connect your ATS or candidate database",
      "Your inbound applicants ranked and screened in place",
      "Existing pipelines and stage names preserved",
      "Optional CRM layer, configured to your hiring model",
    ],
    accent: "#1b6df0",
  },
  {
    id: "agency",
    label: "On our data",
    title: "Run it as an agency engagement",
    body: "No ATS, no sourcing team, no problem. We run the whole pipeline on the BrowseJobs pool and hand you finalists with the full paper trail.",
    points: [
      "Sourcing from the BrowseJobs candidate pool",
      "We operate JD, shortlist, screening and rounds",
      "You receive the written brief per finalist",
      "Scales up and down with your open roles",
    ],
    accent: "#0ba860",
  },
] as const;

/* --------------------------------- pricing --------------------------------- */

export const PRICING = {
  free: {
    label: "First 6 months",
    price: "Free",
    body: "Onboard, connect your roles and run the full pipeline at no cost for six months. No card, no lock-in.",
    points: [
      "Full pipeline — JD to handover brief",
      "AI screening calls included",
      "ATS board and per-JD customisation",
      "High-level BGV on finalists",
    ],
  },
  paid: {
    label: "After the free period",
    body: "Two ways to continue. Which one fits depends on how you hire — we work it out with you before anything is charged.",
    // Deep navy, never green: green is reserved for free/verified surfaces
    // (CLAUDE.md §Design System semantic colour rules) and these are paid tiers.
    options: [
      {
        title: "Agency model",
        headline: "8% of CTC",
        body: "Per successful hire, calculated on the candidate's annual CTC. We run sourcing and the full pipeline.",
        note: "Rate confirmed in writing before the engagement starts.",
        accent: "#0e3fa9",
      },
      {
        title: "Per interview",
        headline: "Shared on the call",
        body: "Pay per interview conducted rather than per hire — including the AI caller. Priced against your volume and round mix.",
        note: "Interview-only hiring is also available on this model. Pricing is discussed in the onboarding meeting.",
        accent: "#0e3fa9",
      },
    ],
  },
  crm: {
    title: "CRM, if you want it",
    body: "A hiring CRM can be added and customised to your process — stages, ownership, follow-ups and reporting shaped around how your team already works. Scope and pricing are agreed separately.",
  },
} as const;

/* ----------------------------------- FAQ ----------------------------------- */

export const EMPLOYER_FAQ = [
  {
    q: "Do we have to replace our ATS?",
    a: "No. The pipeline can run against your existing ATS and candidate database, keeping your stages and your data in place. The CRM layer is optional and only added if you want it.",
  },
  {
    q: "Who conducts the interview rounds?",
    a: "Whoever you choose. L1 and L2 can be conducted by the AI interviewer, by your panel, or split between them. Custom rounds use your questions and your rubric, and any round can be handed back to a human at any point.",
  },
  {
    q: "What exactly does the AI screening call cover?",
    a: "Availability, notice period, current and expected CTC, location fit, and role-specific questions generated from your JD. The full recording and transcript are attached to the candidate record.",
  },
  {
    q: "How deep is the background verification?",
    a: "It is a high-level first pass — employment and education consistency, and public professional footprint cross-referenced against the CV. Discrepancies are flagged with their source. Formal deep BGV stays with your existing provider.",
  },
  {
    q: "Can candidates tell they are speaking to an AI?",
    a: "Yes. Candidates are told at the start of the call, and every call is recorded and AI-monitored — the same standard we hold ourselves to on the student side.",
  },
  {
    q: "What happens after the six free months?",
    a: "We meet before the period ends and agree the model that fits your hiring — agency at 8% of CTC, or per interview conducted. Nothing is charged without a written agreement first.",
  },
] as const;

/* --------------------------------- the form -------------------------------- */

export const HIRING_VOLUMES = [
  "1–5 roles",
  "6–20 roles",
  "21–50 roles",
  "50+ roles",
  "Not sure yet",
] as const;
