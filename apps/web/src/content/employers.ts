/**
 * Employer landing content (/employers) — the logged-out marketing page for the
 * employer module that ships at /employer (PRD-E, ADR 0051).
 *
 * PIPELINE describes the hiring flow. Stages that the workspace actually runs
 * (JD drafting, graded ranking, async AI interviews, automation rules, evidence
 * after grading, human-only offers) are written as product. Scenes that run
 * ahead of the code — outbound phone screening, camera proctoring, background
 * verification — are labelled samples in the stage copy and in HONESTY_LIMITS.
 *
 * Anything not yet built also lives in ROADMAP and is rendered in a visually
 * distinct band: the public API, webhooks and ATS import are phase E4.
 * Pre-BGV (EPFO + DigiLocker, consent-based, optional per role) is live.
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
    kicker: "First screen",
    title: "The facts, filed before a person sits down.",
    body: "What runs today is an async, role-specific AI interview — spoken or typed — graded against that job's rubric. The scene beside this is a sample of the facts a first screen should capture. An outbound phone dialler is not connected.",
    points: [
      "Questions are generated with the job description and can be previewed before anyone is invited",
      "A round goes out by hand, or on its own when a score clears the bar you set",
      "Below the bar, nothing is sent and nobody is rejected — a person decides",
      "Notice period, pay and outcome in the scene are a sample checklist, not a live call recording",
    ],
    accent: "#0ba860",
    demo: "call",
  },
  {
    id: "rounds",
    step: "04",
    kicker: "Interview rounds",
    title: "L1, L2, or a round you design.",
    body: "Each round says what it tests, how long the candidate has, and whether it is sent by hand or when a score clears a bar. AI interview and multiple-choice rounds run on the platform. A human round is a conversation you hold — tracked here, not generated.",
    points: [
      "Question source per role: the bank generated for the job, weighted to the skills that round tests",
      "Rounds you design: AI interview, multiple choice, or a human round you run yourself",
      "Questions already asked are not asked again on the next round",
      "A human round cannot be automatic. There is no slot-booking network behind it yet — you record the outcome on the pipeline",
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
      "Applied → Graded → Shortlisted → L1 → L2 → Human round → Offer → Hired",
      "A rule listens for a graded application or a graded round, then advances or parks",
      "When you save a rule, you see how many current graded applicants already clear its bar",
      "Automation can park for review. It cannot reject, and it cannot release an offer",
    ],
    accent: "#1b6df0",
    demo: "ats",
  },
  {
    id: "bgv",
    step: "06",
    kicker: "Evidence",
    title: "See the proof, not just the score.",
    body: "A graded round leaves the answers, the per-dimension scores, and a short read of what was strong and what was thin. Until grading finishes, the score stays empty. The flag panel in the scene is a sample: camera and window-switch checks are not captured yet, so an empty session is not a pass.",
    points: [
      "Dimension scores and an overall, written only after the answers exist",
      "Strong moments and concerns, grounded in what was actually said",
      "Empty means not graded — it is never filled in to look complete",
      "A flag, when you have one, never auto-rejects. Camera checks are not captured, so a blank panel is not a pass",
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
      "Answers, dimension scores and the written summary, only after grading",
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
  "An AI interview runs against the rubric for that role",
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
      "An async AI interview runs against the rubric for that role",
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
      "Role-specific AI interviews included",
      "Pipeline board and per-role customisation",
      "Written brief on every finalist — scores only once graded",
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
        body: "Pay per interview conducted rather than per hire. Priced against your volume and round mix.",
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
    a: "Whoever you choose. AI interview and multiple-choice rounds run asynchronously on the platform, from the question bank generated for that role. A human round is yours: it cannot be sent automatically, and you record the outcome on the pipeline. Self-serve calendar booking is not part of the workspace yet.",
  },
  {
    q: "What exactly does the AI screening call do?",
    a: "The interview that runs today is asynchronous and role-specific. The candidate answers — spoken or typed — against the rubric generated for that job. Scores, a summary, and the strong and weak moments are written only after grading. The phone-call checklist on this page is a sample of facts a first screen should capture. An outbound dialler is not connected, so we do not claim a recording from a call we did not place.",
  },
  {
    q: "Do you run background verification?",
    a: "Yes, as an optional step. With each candidate's consent, pre-BGV checks employment history on EPFO and documents through DigiLocker, and you choose whether to run it for a role. A mismatch is flagged for your review. It never auto-rejects anyone. You also get the interview: the answers, per-dimension scores once graded, and the written strong and weak moments. Camera and window-switch proctoring are not captured.",
  },
  {
    q: "Can candidates tell they are speaking to an AI?",
    a: "They are sitting an interview the product describes as an AI interview, graded against a rubric, not a surprise human screen. Counselling calls on the student side are a different thing: those are recorded and AI-monitored. The employer interview stores the questions and the answers. It does not, today, store a camera feed.",
  },
  {
    q: "Can the automation reject someone without us seeing them?",
    a: "No. A rule can advance a candidate into Shortlisted, L1 or L2, or park them for review. A separate setting can send the next platform round when a score clears its bar. Neither path can reject someone, and neither can release an offer. Offer release always takes an explicit human action.",
  },
  {
    q: "What happens after the six free months?",
    a: "We meet before the period ends and agree the model that fits your hiring — agency at 8% of CTC, or per interview conducted. Nothing is charged without a written agreement first.",
  },
  {
    q: "Is this a training company, a hiring platform, or both?",
    a: "Both, and they stay distinct. BrowseJobs trains people on live programmes — Data Engineering, DevOps & Cloud, Python Backend, Data Analytics — and can run hiring for you against that graded pool, as an agency. Separately, your own workspace runs the AI interview pipeline on applicants you bring. Training does not inflate a score. A purchase never changes a grade.",
  },
  {
    q: "How do you think about bias and fairness?",
    a: "Every candidate on a role is graded against the same rubric, from their answers, not from whether they paid for a programme. Automation may advance someone who cleared a bar you set, or park them for review. It cannot reject them and it cannot release an offer. That is a product limit, not a certificate that the model is unbiased. You still read the answers. A human owns the no, and a human owns the offer.",
  },
  {
    q: "What candidate data do we actually hold?",
    a: "What the candidate consented to share on the application, plus the interview itself: questions, answers, dimension scores once graded, and the written summary. Camera and window-switch proctoring are not captured, so we will not show you a clean integrity report we never recorded. Counselling calls on the student side are recorded and AI-monitored. The public privacy policy is the document for purposes, consent and rights — retention windows on that page are still marked for the founder to fill, and we will not invent them here.",
  },
  {
    q: "Will you connect this to the ATS we already run?",
    a: "Not yet. The pipeline runs in its own workspace. A public API, signed webhooks and CSV or ATS import are on the roadmap. If a launch depends on that connection, say so on the call and we will be straight about timing.",
  },
] as const;

/* ----------------------- what the automation actually does ----------------- */

/**
 * Two products, stated separately so a training buyer and a SaaS buyer do not
 * read each other's promise.
 */
export const PRODUCT_SPLIT = [
  {
    id: "platform",
    kicker: "The workspace",
    title: "Run the interviews yourself.",
    body: "You publish the role in a BrowseJobs workspace. Inbound applicants are ranked once they have a graded interview. Your team — owner, recruiter, hiring manager — moves the pipeline. The AI drafts the role, writes the interview, and scores answers against the rubric. It does not hire anyone.",
  },
  {
    id: "supply",
    kicker: "The candidate pool",
    title: "Or take people we have already trained.",
    body: "The same company runs live programmes and a placement effort. On the agency model we source from that graded pool, operate the pipeline, and hand you a written brief per finalist. You are not buying a course. You are hiring from people who have already been interviewed against a bar.",
  },
] as const;

/** Machine vs person. Every line is a behaviour enforced in the employer module. */
export const AUTOMATION_SPLIT = {
  machine: [
    "Draft a structured role from a title and notes, or extract skills from a job description you paste. You publish it. The draft does not go live on its own.",
    "On publish, generate that role's interview questions and a rubric. You can preview and regenerate. The rubric is not editable per candidate, so scores stay comparable.",
    "Rank applicants who have been graded. Leave everyone else underneath, with an invite to the interview — ungraded is not a zero.",
    "Send the next platform round when a score lands above the threshold you set on that round. Sending the round does not move the pipeline stage.",
    "Advance a stage, or park for review, when an automation rule fires. Rules listen for “application graded” or “interview graded”, and only into Shortlisted, L1 or L2.",
    "Write dimension scores, an overall, and a short summary after answers exist. If grading is delayed, the score stays empty and the screen says so.",
  ],
  person: [
    "Publish, pause or close the role.",
    "Choose which competencies to weight, which skills the round tests, and whether a round is sent by hand or on a score.",
    "Decide what happens below the bar. The product sends nothing and rejects no one.",
    "Run any human round. A human round cannot be set to automatic — there is nothing for the platform to send.",
    "Reject, with a reason. Terminal rejection is not an automation action.",
    "Release an offer. Offer release is an explicit action by someone with permission. It is never a rule.",
    "Override a rule. An override is a decision, not an error.",
  ],
} as const;

/**
 * Non-technical dimensions a mock can score. Copied from the role taxonomy
 * the interview designer actually loads — not a marketing rubric.
 */
export const SCORE_DIMENSIONS = [
  { key: "technical_depth", label: "Technical depth", hint: "Correct, concrete detail in the role's core skills." },
  { key: "problem_solving", label: "Problem solving", hint: "Structured reasoning and trade-offs under ambiguity." },
  { key: "communication", label: "Communication", hint: "Clear, concise spoken explanation." },
  { key: "experience_evidence", label: "Evidence of experience", hint: "Real work with outcomes, not generalities." },
  { key: "interpersonal", label: "Interpersonal skills", hint: "Collaboration, conflict handling, stakeholder tone." },
  { key: "domain_knowledge", label: "Domain knowledge", hint: "Understanding of the industry the role sits in." },
  { key: "ownership", label: "Ownership", hint: "Follow-through, accountability, bias to act." },
] as const;

export const QUESTION_FORMATS = [
  { label: "Scenario", hint: "An open situational question, answered in speech or text." },
  { label: "Technical", hint: "Role-specific knowledge." },
  { label: "Multiple choice", hint: "Objective, and scored without a grader's prose." },
  { label: "Communication", hint: "Clarity and structure, rather than content alone." },
  { label: "Behavioural", hint: "Past behaviour as evidence." },
] as const;

/** Dashboard figures the workspace actually renders. No targets, no benchmarks. */
export const WORKSPACE_FACTS = [
  {
    title: "The board",
    body: "Stages run Applied → Graded → Shortlisted → L1 → L2 → Human round → Offer → Hired. You can insert rounds. Graded is written by the interview, not picked off a menu to skip the work.",
  },
  {
    title: "The team",
    body: "Three roles, and no more in the workspace today. Owner manages the workspace and the team. Recruiter posts roles and drives the pipeline. Hiring manager reviews shortlists and evidence.",
  },
  {
    title: "The dashboard",
    body: "What the home screen counts: active roles, applicants graded in the last 7 days, interviews in flight, offers open, a hiring funnel, a score distribution, and a pipeline pulse per role. Those are your numbers. We do not publish a benchmark next to them.",
  },
  {
    title: "The audit",
    body: "A stage change records who did it — a person or a rule. Sending a round is audited. Offer release is audited. If grading was produced by the model or by the fallback path, the profile says which.",
  },
] as const;

/**
 * Claims the animated scenes can suggest that the product does not yet do.
 * Rendered as their own band so they cannot be skimmed as features.
 */
export const HONESTY_LIMITS = [
  {
    title: "No outbound dialler",
    body: "The screening-call scene is a sample checklist. The interview that runs is async. We do not place the call, so we do not attach a recording of one.",
  },
  {
    title: "No proctoring capture",
    body: "Camera and window-switch signals are not recorded. The profile says proctoring was not captured. An empty integrity panel is empty. It is not a pass.",
  },
  {
    title: "No ATS plugin yet",
    body: "A public API, signed webhooks and CSV or ATS import are planned. Until then the pipeline lives in this workspace. “Works on your ATS” would be a false sentence, so it is not on this page.",
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
