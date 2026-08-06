/**
 * Copy and fixtures for /for-employers — the employer landing page.
 *
 * Everything here describes capability that exists in the employer portal
 * (PRD-E). Two rules held the writing:
 *
 *  - No outcome statistics. Brand voice forbids hype and any claim that
 *    hiring is certain, and an invented "cuts screening time 70%" would be
 *    exactly that. The page sells what the product *does*, which we can
 *    show, rather than what it will get you, which the market decides.
 *  - The sample report is a fictional candidate, labelled as one wherever
 *    it renders. It is shaped against the real `CandidateProfileData` the
 *    portal renders, so it is a preview and not a mock-up.
 */

import type { CandidateProfileData, TalentPoolCandidate } from "@/lib/employer";

/* ------------------------------------------------------------------ hero */

export const employerHero = {
  kicker: "For employers",
  title: "Every applicant arrives",
  highlight: "already interviewed.",
  sub: "Design the interview once — the skills, the rubric, the rounds — and every candidate who applies has already sat it, proctored. You open a shortlist with scores, evidence and verified credentials, not a folder of CVs.",
  primary: "Start free for 6 months",
  secondary: "Sign in to your workspace",
} as const;

/**
 * The launch offer. Figures are the ones the business set; the page states
 * them plainly and does not dress them up with an urgency countdown or a
 * struck-through "was" price that never existed.
 */
export const freeOffer = {
  kicker: "Launch offer",
  title: "Six months.",
  highlight: "₹0.",
  sub: "Vetted candidates, the full hiring workspace, and every report on this page — free for your first six months. No fee per hire, no fee per shortlist, no charge for the assessments your applicants sit.",
  months: 6,
  price: 0,
  included: [
    { title: "Vetted candidates", body: "Assessed, proctored and credential-checked before they reach your pipeline." },
    { title: "Unlimited roles", body: "Post as many JDs as you are hiring for. The assessment designer comes with each." },
    { title: "Every report", body: "Scores, per-competency breakdowns, session integrity and verification outcomes." },
    { title: "Your whole team", body: "Recruiters and hiring managers on the same workspace, at no extra seat cost." },
  ],
  closer: "Nothing to lose: if the pool is wrong for your roles, you will know inside a week and it will have cost you nothing.",
} as const;

/* ------------------------------------------------------------- use cases */

export type UseCase = {
  id: string;
  tag: string;
  title: string;
  situation: string;
  problem: string;
  solution: string;
  features: string[];
};

export const useCases: UseCase[] = [
  {
    id: "volume",
    tag: "Volume",
    title: "Three openings, four hundred applicants",
    situation:
      "A junior data role goes up. The inbox fills in a weekend and every CV claims Python, SQL and “strong communication”.",
    problem:
      "Reading them ranks nobody. CVs are self-reported, so the strongest writer wins the first round and the strongest engineer may never be read.",
    solution:
      "Applicants sit your assessment before they reach you. The pipeline arrives sorted by a score that came from answers to your questions, and a distribution chart shows how many clear the bar you set — so the bar is a decision, not a guess.",
    features: ["Assessment designer", "Score distribution", "Automation rules"],
  },
  {
    id: "niche",
    tag: "Niche skill",
    title: "Hiring for a skill nobody on your team has",
    situation:
      "You need a data engineer. Your interviewers are backend developers. They can tell whether an answer sounds confident, not whether it is right.",
    problem:
      "The first technical screen becomes a vocabulary test, and the mis-hire is discovered in month three.",
    solution:
      "Pick the role and the skills; the role taxonomy proposes the core and optional ones that matter for it. Weight the competencies you actually need, and every answer is graded against that rubric — dimension by dimension, with the reasoning shown.",
    features: ["Role taxonomy", "Competency weights", "Per-dimension scoring"],
  },
  {
    id: "agency",
    tag: "Agency spend",
    title: "Paying for a shortlist you cannot audit",
    situation:
      "An agency sends four profiles and a fee. You are told they are pre-screened. You cannot see what the screen was.",
    problem:
      "You are buying somebody else's judgement without the working, and re-running your own screen anyway.",
    solution:
      "You write the screen. You see every answer, every score, the rubric it was graded against, and how long the session ran. When a candidate is rejected, the reason is on file — and so is the evidence for keeping one.",
    features: ["Question set + answers", "Grading rubric", "Stage timeline"],
  },
  {
    id: "credentials",
    tag: "Credentials",
    title: "A CV that does not survive the background check",
    situation:
      "The offer is out. Then the degree cannot be confirmed, or the two years at the last company turn out to be eight months.",
    problem:
      "Verification happens last, when reversing the decision is expensive and public.",
    solution:
      "Verification happens on the candidate's profile, before you meet them. Identity and education are pulled from DigiLocker's issued documents; employment is checked against the EPFO contribution record. You see outcomes on the candidate screen — pass, pending, failed — from the first time you open it.",
    features: ["DigiLocker checks", "EPFO employment history", "Outcome-only reporting"],
  },
];

/* ------------------------------------------------------------------ steps */

export type Step = {
  n: string;
  title: string;
  body: string;
  detail: string[];
  /** Which micro-visual the stepper renders for this step. */
  visual: "workspace" | "jd" | "mock" | "rounds" | "publish" | "grade" | "decide";
};

export const steps: Step[] = [
  {
    n: "01",
    title: "Open a workspace",
    body: "Your company, your team, your pipeline. Invite colleagues with the access their job needs — an owner runs the account, a recruiter moves candidates, a hiring manager reads and comments.",
    detail: ["Owner · recruiter · hiring manager", "Company profile and industry", "Nothing shared across workspaces"],
    visual: "workspace",
  },
  {
    n: "02",
    title: "Draft the role",
    body: "Give it a title. The JD drafter returns a description, responsibilities and must-haves, and the role taxonomy proposes the core skills that role tends to require plus the optional ones. Everything is editable — it is a first draft, not a decision.",
    detail: ["AI-drafted description", "Suggested core + optional skills", "Experience band and locations"],
    visual: "jd",
  },
  {
    n: "03",
    title: "Design the assessment",
    body: "Choose which skills the assessment focuses on, weight the competencies that matter for this role, set the mix of question formats and how many questions. That design generates the interview every applicant sits.",
    detail: ["Focus skills", "Competency weights", "Format mix and question count"],
    visual: "mock",
  },
  {
    n: "04",
    title: "Design the rounds",
    body: "Build your process as a sequence: an AI interview, an MCQ round, a human conversation. Each round gets its own focus skills, its own weights, its own response window — and can dispatch automatically above a score you choose, or wait for you.",
    detail: ["AI interview · MCQ · human", "Per-round rubric and window", "Auto-dispatch above your floor"],
    visual: "rounds",
  },
  {
    n: "05",
    title: "Publish, and invite",
    body: "The role goes live to candidates on BrowseJobs. You can also work the other direction: open the talent pool for this JD and invite trained candidates whose skills match, whether or not they were looking.",
    detail: ["Live on the BrowseJobs job feed", "Matched talent pool per JD", "Direct invitations to apply"],
    visual: "publish",
  },
  {
    n: "06",
    title: "Applications arrive graded",
    body: "Every applicant completes your assessment, and the grader scores it against your rubric — overall, then dimension by dimension, with what went well and where they were weaker. Your automation rules advance or park candidates at the threshold you set.",
    detail: ["Scored against your rubric", "Strengths and concerns, in writing", "Rules advance or park automatically"],
    visual: "grade",
  },
  {
    n: "07",
    title: "Read, decide, move",
    body: "Open a candidate and you get the whole picture: score, competency breakdown, verification outcomes, session facts, background. Shortlist them and contact details unlock. Then send the next round, make the offer, or reject with the reason on record.",
    detail: ["Full candidate report", "Contact unlocks at shortlist", "Ten-stage pipeline to hired"],
    visual: "decide",
  },
];

/* ------------------------------------------------------------- proctoring */

/**
 * Session integrity. Employers discount an unsupervised online assessment,
 * and they are right to — so the page states what is watched rather than
 * asking for the benefit of the doubt.
 */
export const proctoring = {
  kicker: "Session integrity",
  title: "Every interview is",
  highlight: "proctored",
  sub: "The assessment is not a link someone opens in one tab with the answers in another. Sessions are monitored for cheating and misconduct, the signals are recorded against the attempt, and what was observed is on the report you read.",
  signals: [
    {
      key: "focus",
      label: "Leaving the session",
      body: "Switching tabs or away from the window is counted and timestamped, not merely noticed.",
    },
    {
      key: "paste",
      label: "Pasted answers",
      body: "Text arriving by paste rather than by typing is recorded, so a perfect answer that was never written shows as one.",
    },
    {
      key: "timing",
      label: "Answer timing",
      body: "How long each answer took is kept. A hard question answered instantly reads differently from one worked through.",
    },
    {
      key: "review",
      label: "Flagged for review",
      body: "A session carrying misconduct signals is flagged on the candidate report instead of being quietly averaged into a score.",
    },
  ],
  closer:
    "You are not asked to trust the score. You are shown the session it came from — and if we did not observe something, the report says so rather than leaving a gap you would read as clean.",
} as const;

/* ------------------------------------------------------------------ bento */

export type Capability = { title: string; body: string; tone: "trust" | "employer" | "verify" };

export const capabilities: Capability[] = [
  {
    title: "JD drafter",
    body: "A title in, a working description out — responsibilities, must-haves and a skill list you edit rather than compose.",
    tone: "trust",
  },
  {
    title: "Role taxonomy",
    body: "Role families, titles and the skills that go with them, so the skill list on a JD is not one person's memory of the last hire.",
    tone: "trust",
  },
  {
    title: "Assessment designer",
    body: "Focus skills, competency weights, format mix, question count. Change the design and the assessment regenerates.",
    tone: "employer",
  },
  {
    title: "Round designer",
    body: "Your process as a sequence of rounds — AI, MCQ or human — each with its own rubric, window and dispatch rule.",
    tone: "employer",
  },
  {
    title: "Automation rules",
    body: "On graded, above a score, advance or park. Before you switch a rule on, it tells you how many current candidates it would have matched.",
    tone: "employer",
  },
  {
    title: "Talent pool",
    body: "BrowseJobs-trained candidates matched to your JD, with skill overlap, what is missing, mock history and a readiness index.",
    tone: "verify",
  },
  {
    title: "Proctored sessions",
    body: "Focus loss, pasted text and answer timing recorded against every attempt, with misconduct flagged on the report.",
    tone: "verify",
  },
  {
    title: "Verified profiles",
    body: "Identity and education from DigiLocker, employment against the EPFO record. Outcomes only — we hold the documents, you see the result.",
    tone: "verify",
  },
  {
    title: "Pipeline analytics",
    body: "Funnel by stage, applications against graded over time, score distribution against your threshold, and what is waiting on you.",
    tone: "trust",
  },
  {
    title: "Team and roles",
    body: "Recruiters move the pipeline, hiring managers read it. Every stage change is written to the candidate's timeline with who did it.",
    tone: "trust",
  },
];

/* ------------------------------------------------------- honest trade-offs */

export const advantages: { title: string; body: string }[] = [
  {
    title: "You are reading evidence, not claims",
    body: "A score on this platform came from answers to questions you set. The answers are on the page next to it.",
  },
  {
    title: "The screen is consistent",
    body: "Every applicant meets the same rubric, in the same format, whether they applied first or four hundredth.",
  },
  {
    title: "The score came from a watched session",
    body: "Interviews are proctored. Tab switches, pasted answers and answer timing are recorded against the attempt and shown to you.",
  },
  {
    title: "Credentials are checked before the interview",
    body: "Identity, education and employment are verified on the candidate's profile — not discovered after the offer.",
  },
  {
    title: "Your first round runs without you",
    body: "Rounds dispatch on a schedule and a score floor. Nobody blocks a candidate by being on leave.",
  },
  {
    title: "Rejections have reasons attached",
    body: "Stage moves carry a note and an actor. Six months later you can still answer why a candidate did not progress.",
  },
  {
    title: "Candidate contact stays closed until shortlist",
    body: "You assess the work first. It also means applying here does not cost a candidate their inbox.",
  },
];

export const limits: { title: string; body: string }[] = [
  {
    title: "The trained pool is concentrated",
    body: "BrowseJobs trains for data, cloud, backend and analytics roles. Anyone can apply to your JD, but the matched talent pool is deepest where we teach — if you are hiring a brand designer, that advantage is not there.",
  },
  {
    title: "Verification takes as long as the source does",
    body: "Checks depend on DigiLocker and EPFO. A candidate who has not submitted, or a record that has not been issued, shows as pending — and we report pending as pending rather than dressing it up.",
  },
  {
    title: "Proctoring flags, it does not judge",
    body: "Integrity signals tell you a session left the window or an answer was pasted. They do not prove intent, and we do not reject anyone on a signal alone — the flag is on the report for you to weigh.",
  },
  {
    title: "A rubric you write badly grades badly",
    body: "Weights and focus skills are yours. The system applies them consistently; it does not rescue a design that measures the wrong thing.",
  },
  {
    title: "An AI round is a filter, not a verdict",
    body: "It is good at whether an answer is correct and complete. It does not tell you whether someone will be good to work with — that is what your human round is still for.",
  },
];

export const notForYou = [
  "You want CVs to read yourself and would rather not define a rubric.",
  "You are hiring for roles where a live portfolio review is the real screen.",
  "You need someone to start this week — designing the process is an afternoon, and candidates need a window to sit it.",
  "You expect a guaranteed shortlist size. Applications depend on the role, the pay and the market, and nobody controls that.",
];

/* --------------------------------------------------- sample report fixture */

/**
 * A fictional candidate, in the exact shape the employer portal renders
 * (`CandidateProfileData`). Fields a real report can be missing are missing
 * here too — the employment check is still pending, the document check has
 * not started, and contact is withheld because the stage is Graded. A
 * preview that only showed the happy path would be a sales lie.
 */
export const sampleReport: CandidateProfileData = {
  application: {
    id: 0,
    stage: "graded",
    mock_score: 78,
    mock_attempts: 2,
    applied_at: "2026-07-14T09:20:00Z",
    graded_at: "2026-07-14T11:05:00Z",
  },
  candidate: {
    id: null,
    name: "Ananya R.",
    email: null,
    phone: null,
    contact_visible: false,
  },
  cv: {
    summary:
      "Two years building batch pipelines for a logistics analytics team. Owns the nightly load from operations systems into the warehouse, and the SQL models the ops dashboards read from.",
    skills: ["Python", "SQL", "Airflow", "dbt", "PostgreSQL", "AWS S3", "Docker"],
    experience: [
      { title: "Data Engineer", org: "Meridian Logistics", period: "2024 — present" },
      { title: "Analyst (Operations)", org: "Meridian Logistics", period: "2023 — 2024" },
    ],
    education: [{ title: "B.E. Information Science", org: "VTU", period: "2019 — 2023" }],
  },
  verification: {
    badge: false,
    checks: [
      { kind: "identity", label: "Identity", status: "verified", status_label: "Verified", verified_at: "2026-06-30T00:00:00Z" },
      { kind: "education", label: "Education", status: "verified", status_label: "Verified", verified_at: "2026-07-02T00:00:00Z" },
      { kind: "employment", label: "Employment history", status: "pending", status_label: "Pending", verified_at: null },
      { kind: "documents", label: "Supporting documents", status: "not_started", status_label: "Not started", verified_at: null },
    ],
  },
  interview: {
    overall: 78,
    graded_by: "ai",
    competencies: [
      { name: "SQL and data modelling", score: 88 },
      { name: "Python for data", score: 81 },
      { name: "Pipeline design", score: 74 },
      { name: "Debugging and failure handling", score: 66 },
      { name: "Communication", score: 79 },
    ],
    strengths: [
      "Wrote a correct window function for the running-total question on the first attempt, and explained why a self-join would have been slower.",
      "Described idempotent reruns without being prompted — reload by partition rather than truncating the table.",
      "Chose columnar storage for the analytics table and gave the reason: the queries read three columns out of forty.",
    ],
    concerns: [
      "Asked how they would handle a task that failed at 03:00, the answer stopped at “rerun it” — no mention of alerting, backfill order, or downstream dependencies.",
      "Named dbt tests but could not describe one they had written.",
      "Cost was not mentioned once across the pipeline design questions.",
    ],
    session: {
      mode: "text",
      duration_seconds: 2_640,
      status: "completed",
      completed_at: "2026-07-14T10:52:00Z",
      proctoring_captured: true,
    },
  },
};

/**
 * The integrity signals shown alongside the sample session.
 *
 * These sit outside `CandidateProfileData` because the employer profile
 * payload does not carry them yet — `CandidateProfile::session()` still
 * returns a flat `proctoring_captured` and nothing else. Keeping the fixture
 * separate means this page is not pretending the API shape already has a
 * field it does not, and whoever wires the signals through has one list to
 * match.
 */
export const sampleIntegrity = {
  clean: true,
  signals: [
    { label: "Left the session window", value: "0 times", clean: true },
    { label: "Answers arriving by paste", value: "0 of 12", clean: true },
    { label: "Fastest answer", value: "1 min 24 s", clean: true },
    { label: "Flagged for review", value: "No", clean: true },
  ],
} as const;

/** The threshold drawn on the sample distribution — an employer's own bar. */
export const sampleThreshold = 70;

/** Sample score distribution, in the shape the dashboard's histogram takes. */
export const sampleDistribution = [
  { band: "0–39", floor: 0, count: 41 },
  { band: "40–54", floor: 40, count: 63 },
  { band: "55–69", floor: 55, count: 88 },
  { band: "70–84", floor: 70, count: 52 },
  { band: "85–100", floor: 85, count: 17 },
];

/* ------------------------------------------------------------ talent pool */

/** Three fictional matches, in the portal's `TalentPoolCandidate` shape. */
export const sampleTalentPool: TalentPoolCandidate[] = [
  {
    candidate_id: 1,
    name: "Rahul M.",
    match_score: 92,
    skill_match_pct: 86,
    matched_skills: ["Python", "SQL", "Airflow", "dbt", "Spark"],
    missing_skills: ["Kafka"],
    readiness_index: 88,
    mock_average: 84,
    mock_attempts: 6,
    training: { course: "Data Engineering", status: "completed" },
    cv_ready: true,
  },
  {
    candidate_id: 2,
    name: "Sneha K.",
    match_score: 84,
    skill_match_pct: 74,
    matched_skills: ["Python", "SQL", "PostgreSQL", "Docker"],
    missing_skills: ["Airflow", "Spark"],
    readiness_index: 79,
    mock_average: 77,
    mock_attempts: 4,
    training: { course: "Data Engineering", status: "in_progress" },
    cv_ready: true,
  },
  {
    candidate_id: 3,
    name: "Imran S.",
    match_score: 71,
    skill_match_pct: 62,
    matched_skills: ["SQL", "Python", "AWS"],
    missing_skills: ["dbt", "Airflow", "Spark"],
    readiness_index: 68,
    mock_average: 71,
    mock_attempts: 3,
    training: { course: "Data Analytics", status: "completed" },
    cv_ready: false,
  },
];

/* -------------------------------------------------------------------- faq */

export const employerFaq: { q: string; a: string }[] = [
  {
    q: "Do we have to use your assessment?",
    a: "You have to design one — that is what makes the pipeline sorted. But it is yours: your focus skills, your weights, your question mix, and a human round wherever you want it in the sequence.",
  },
  {
    q: "Can we see how a score was reached?",
    a: "Yes. Every graded round shows the questions, the candidate's answers, the per-dimension scores and the rubric they were graded against. If a score is wrong, you can see where it went wrong.",
  },
  {
    q: "How do you know a candidate did not cheat?",
    a: "Sessions are proctored. Leaving the window, pasting answers and per-answer timing are recorded against the attempt, and anything that looks like misconduct is flagged on the report rather than averaged away. We show you the signals and let you weigh them — a flag is evidence, not a verdict.",
  },
  {
    q: "What does the free six months cover?",
    a: "The whole workspace: unlimited roles, the assessment and round designers, the talent pool, every candidate report, and your full team on it. ₹0 for six months — no fee per shortlist and no fee per hire during that period.",
  },
  {
    q: "When do we get contact details?",
    a: "At Shortlisted. Before that the API does not return them, so nobody on your team can accidentally reach out to a candidate you have not decided on.",
  },
  {
    q: "What exactly does “verified” mean?",
    a: "That a specific check passed against a specific source — DigiLocker for identity and education, the EPFO record for employment. We report the outcome only. The documents stay with BrowseJobs and are never in the response your portal receives.",
  },
  {
    q: "Are candidates only BrowseJobs students?",
    a: "No. A published JD is open on the job feed. The talent pool — matched, trained, with a mastery record behind them — is the part that is ours, and it is deepest in the fields we teach.",
  },
  {
    q: "Can you promise us a hire?",
    a: "No, and you should distrust anyone who does. Applications depend on the role, the pay and the live market. What we put in writing is the process: a designed assessment, consistent grading, checked credentials and evidence you can audit.",
  },
];
