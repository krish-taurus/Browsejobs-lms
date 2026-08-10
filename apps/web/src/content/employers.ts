/**
 * Employer landing content (/employers) — the logged-out marketing page for the
 * employer module that ships at /employer (PRD-E, ADR 0051).
 *
 * Everything in PIPELINE describes behaviour that exists today: JD drafting and
 * skill extraction (F2), graded ranking with match rationale (F3), the
 * Caller.Digital AI screening call (CRM `lead_calls`), async proctored L1/L2 and
 * custom rounds (F5), the automation rules engine with dry-run simulation (F6),
 * candidate evidence and the graded report (F4), and human-only offer release.
 *
 * Anything not yet built lives in ROADMAP and is rendered in a visually distinct
 * band so it cannot read as available: the public API, webhooks and ATS import
 * are phase E4, and the full Trust Score verification chain (DigiLocker / PAN /
 * EPFO) needs its own ADR per PRD-E §7 before build.
 *
 * Brand voice rules apply (CLAUDE.md §Brand Voice): no hype adjectives, no
 * guaranteed-hire claims, no fabricated performance statistics. Every number on
 * the page is either a process fact or a labelled illustration.
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
    body: "Paste a JD, or start from a title and a few bullets. The AI drafts the structured role — skills, experience band, locations, knockout questions — and flags requirements that are too vague to screen against. You publish it, not the AI.",
    points: [
      "Draft from a title + notes, or extract structure from a pasted JD",
      "Tagged skills drive the matching; experience band and CTC band are yours to set",
      "Vague requirements flagged before they cost you a bad shortlist",
      "On publish, the JD's own interview mock and grading rubric are generated",
    ],
    accent: "#1b6df0",
    demo: "jd",
  },
  {
    id: "shortlist",
    step: "02",
    kicker: "Applications & the graded pool",
    title: "Ranked, with the reason written down.",
    body: "Graded applicants rank on top, each with a short explanation of why they placed where they did — citing skills, mock evidence and readiness signals. Ungraded applicants sit below with a one-click invite to the mock.",
    points: [
      "“Why #1 is #1” — a written rationale per candidate, not a bare score",
      "Knockout questions and hard filters applied before ranking",
      "Bulk advance, or reject with a templated, candidate-respectful reason",
      "Every candidate ever graded for you stays searchable in your talent pool",
    ],
    accent: "#7c3aed",
    demo: "shortlist",
  },
  {
    id: "call",
    step: "03",
    kicker: "AI screening call",
    title: "The first call is placed for you.",
    body: "An AI caller dials the shortlist automatically and has the conversation your recruiter would have had first — then files the transcript, the recording and a read on how it went.",
    points: [
      "Auto-dialled from the queue; no answer returns the candidate to the queue, not the bin",
      "Full transcript and call recording attached to the candidate record",
      "Outcome and sentiment captured, so the next step is already decided",
      "Multi-language, and every call is recorded and AI-monitored",
    ],
    accent: "#0ba860",
    demo: "call",
  },
  {
    id: "rounds",
    step: "04",
    kicker: "Interview rounds",
    title: "L1, L2, or a round you design.",
    body: "Rounds run async and proctored — the candidate is notified on WhatsApp or email and completes within a window you set. L1 to L2 can unlock the same day.",
    points: [
      "Question source per JD: your own bank, AI-generated, or blended",
      "Custom rounds insertable — coding round, assignment, whatever you run",
      "Proctored like the mocks: face-match, window-switch and snapshot flags",
      "Hand any round to a human panel with self-serve slot booking",
    ],
    accent: "#f5a623",
    demo: "rounds",
  },
  {
    id: "ats",
    step: "05",
    kicker: "Pipeline & automation",
    title: "Watch every stage move.",
    body: "Kanban or table, one board per role. Every transition — yours or a rule's — is an event with the actor named on the candidate's timeline.",
    points: [
      "Applied → Graded → Shortlisted → L1 → L2 → Offer, plus stages you insert",
      "Rules on thresholds and SLAs: “mock ≥ 70% → shortlist”, “no review in 24h → nudge”",
      "Simulate a rule before enabling it, against your last 30 days of applicants",
      "Automation can park for review but never rejects outright, and never releases an offer",
    ],
    accent: "#1b6df0",
    demo: "ats",
  },
  {
    id: "bgv",
    step: "06",
    kicker: "Evidence & integrity",
    title: "See the proof, not just the score.",
    body: "Every round leaves evidence you can inspect: the replay, the transcript beside it, per-dimension scores, and the proctoring record — with flags shown as evidence for a human to weigh.",
    points: [
      "Video replay with a seekable transcript — click a line, the video jumps",
      "Per-rubric scores, so you can see which dimension carried the result",
      "Proctoring flags with snapshot evidence and warning counts",
      "A flag never auto-rejects anyone — it surfaces the evidence and waits for you",
    ],
    accent: "#7c3aed",
    demo: "bgv",
  },
  {
    id: "report",
    step: "07",
    kicker: "Decision & offer",
    title: "A written brief before you meet anyone.",
    body: "Your team gets a graded report per candidate — every round, every score, every flag — so the first human conversation starts at the interview, not at CV triage.",
    points: [
      "Round-by-round breakdown with scores and reasoning",
      "Screening-call transcript and proctoring record in the same view",
      "Hiring-manager comments and @mentions kept internal to your workspace",
      "Offer release always takes an explicit human action — it is never automated",
    ],
    accent: "#0ba860",
    demo: "report",
  },
] as const;

/* --------------------------------- roadmap --------------------------------- */

/**
 * Not built yet. Rendered in its own clearly-labelled band so nothing here can
 * be mistaken for a shipped feature. Sources: PRD-E §F11 (phase E4) and §7
 * (verification providers each need an ADR before build).
 */
export const ROADMAP = [
  {
    title: "Run it on your own stack",
    body: "A public employer API, HMAC-signed webhooks and CSV/ATS import, so the pipeline reads and writes against the systems you already have.",
  },
  {
    title: "Full background verification",
    body: "A Trust Score built from DigiLocker ID, PAN, education certificates and EPFO employment history — with each component and its status shown, never a black box.",
  },
  {
    title: "Semantic candidate search",
    body: "Describe the person in a sentence and get matches back, with the interpretation shown as chips you can remove. Saved searches and match alerts alongside.",
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
  "You meet the candidate before you have any evidence",
] as const;

export const TAT_AFTER = [
  "JD in, structured role out — same sitting",
  "Ranked shortlist, each rank explained in writing",
  "AI screening call confirms the basics first",
  "L1/L2 run only on candidates who cleared the screen",
  "Replays, transcripts and scores waiting before your first meeting",
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
      "Replays and per-dimension scores read before the panel meets anyone",
    ],
    outcome:
      "A small, defensible shortlist with the reasoning written down for every rejection.",
    accent: "#7c3aed",
  },
  {
    title: "Agency model",
    scenario:
      "A startup has no recruiter and needs five engineers before the next funding milestone.",
    flow: [
      "Runs on the BrowseJobs graded candidate pool",
      "We operate the pipeline end to end",
      "Founder gets the graded report per finalist",
      "Optional CRM configured to how the team actually hires",
    ],
    outcome:
      "The founder only ever meets candidates who have already been screened, interviewed and graded.",
    accent: "#0ba860",
  },
] as const;

/* ------------------------------ delivery models ---------------------------- */

export const DELIVERY_MODELS = [
  {
    id: "own",
    label: "Your own applicants",
    title: "Run your own pipeline",
    body: "Publish the JD, point your inbound applicants at it, and they arrive graded and ranked in your workspace. Your team runs the decisions; the platform does the screening.",
    points: [
      "Your workspace, your JDs, your roles and permissions",
      "Inbound applicants graded and ranked against your bar",
      "Invite anyone already in your hands to take the JD's mock",
      "Optional CRM layer, configured to your hiring model",
    ],
    accent: "#1b6df0",
  },
  {
    id: "agency",
    label: "Our candidate pool",
    title: "Run it as an agency engagement",
    body: "No recruiter, no sourcing team, no problem. We run the whole pipeline on the BrowseJobs graded pool and hand you finalists with the full paper trail.",
    points: [
      "Sourcing from the BrowseJobs graded candidate pool",
      "We operate JD, shortlist, screening and rounds",
      "You receive the graded report per finalist",
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
    a: "Today the pipeline runs in its own workspace — you publish JDs and work candidates there. A public API, webhooks and CSV/ATS import are on the roadmap, not available yet; if connecting your existing systems is a launch requirement for you, say so on the call and we'll be straight about timing.",
  },
  {
    q: "Who conducts the interview rounds?",
    a: "Whoever you choose. L1 and L2 run async and proctored, with questions from your own bank, AI-generated, or a blend. Custom rounds use your questions and your rubric, and any round can be handed to a human panel with self-serve slot booking.",
  },
  {
    q: "What exactly does the AI screening call do?",
    a: "It dials the shortlist automatically and has the first conversation, then files the full transcript, the call recording, the outcome and a read on sentiment against the candidate. If nobody picks up, the candidate returns to the queue rather than being dropped.",
  },
  {
    q: "Do you run background verification?",
    a: "Not yet. Verification is on the roadmap as a Trust Score built from DigiLocker ID, PAN, education certificates and EPFO employment history. What you get today is interview evidence — replays, transcripts, per-rubric scores and the proctoring record.",
  },
  {
    q: "Can candidates tell they are speaking to an AI?",
    a: "Yes. Candidates are told at the start of the call, and every call is recorded and AI-monitored — the same standard we hold ourselves to on the student side.",
  },
  {
    q: "Can the automation reject someone without us seeing them?",
    a: "No. Rules can advance a candidate, unlock the next round, send a nudge or park someone for review — but they can never reject terminally and never release an offer. Offer release always takes an explicit human action from a permitted role.",
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
