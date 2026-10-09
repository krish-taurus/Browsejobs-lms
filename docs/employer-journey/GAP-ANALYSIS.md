# Employer WhatsApp journey — gap analysis

**Date:** 7 October 2026 · **Updated:** 7 October 2026 (candidate connectors). The mission-control preview is specified in `REQUIREMENTS.md` and sketched at `/employers/mission-control-demo`.
**Scope:** Read-only review of the monorepo (`apps/web`, `apps/api`, migrations, jobs, services, env examples, public copy). No application code was changed.
**Question:** For the founder’s ten-step, WhatsApp-first employer hiring journey, what already exists in code, what is only a label or a marketing sentence, and what is missing?

This document is the evidence file. The build plan is in `REQUIREMENTS.md` in this folder.

---

## How to read the labels

| Label | Meaning |
|---|---|
| **Built** | A person can use it today, with a real database row, API, and (where it is a screen) a page. Tests exist. |
| **Partially built** | A real piece is in the repo, but it does not do the step the founder described. Often it lives on the website dashboard, not on WhatsApp, or the vendor call is a placeholder. |
| **Not built** | No code path does this. |

“Mocked” means the code pretends: a sample row, a hardcoded badge, a null client, or a public sentence that describes a bot the server does not run.

---

## Scoreboard

| # | Step | Status | In one line |
|---|---|---|---|
| 1 | QR onboarding onto a company WhatsApp bot, several people on one company | **Partially built** | Company accounts and email invites exist. There is no QR and no WhatsApp login. |
| 2 | Raise a job by voice note or text; ranked CVs back in about 4–5 seconds | **Partially built** | The website can hear a sentence and rank BrowseJobs students. WhatsApp cannot. The 4–5 second send does not exist. |
| 3 | “Start reaching out?” then AI voice calls, interest and a first screen | **Not built** | No outbound dialler. The public FAQ already says so. |
| 4 | L1 AI interview, then a “who cleared” report | **Partially built** | Async written L1 exists on the API. No WhatsApp report. No candidate page for that round. |
| 5 | L2, then a detailed report per finalist | **Partially built** | Same engine as L1, second round. Report is scores in the dashboard, not a WhatsApp brief. |
| 6 | Per-job switch to run the whole pipeline with no yes/no stops | **Partially built** | Score rules can move someone to Shortlisted, L1, or L2. They cannot run calls, human rounds, BGV, or offers. |
| 7 | Optional human round: ask interviewers on WhatsApp, book a Zoom/Meet link | **Not built** | The stage exists. Nobody is asked for a slot, and no meeting is created for it. |
| 8 | Pre-BGV (EPFO / DigiLocker) and a summary to HR | **Partially built** | A manual review queue and placeholder HTTP clients exist. No vendor contract is wired, and the public FAQ says “not yet”. |
| 9 | Offer letter from the company’s template, emailed | **Not built** | “Offer” is a pipeline label. No letter, no template, no email. |
| 10 | Candidate engagement bot, dropout-risk alert, joining details | **Not built** | A dropout score exists for **students in a batch**, not for someone who might skip joining. |
| — | Client candidate data (Excel, WhatsApp file, email, Drive, database/ATS, Naukri, LinkedIn) | **Not built** | Spreadsheet import, CV parsing, Drive, and Naukri/LinkedIn code exist for other jobs. None of them load a client’s private candidate list. |

| Supporting piece | Status |
|---|---|
| Company and multi-user accounts | **Built** (website and email, not WhatsApp) |
| Auth | **Built** for the website portal |
| CV database and matching | **Partially built** (BrowseJobs students only, keyword overlap, not a general CV search). A client’s own files are not in that search. |
| Notifications | **Partially built** (WhatsApp works for other products; this journey barely uses it) |
| Audit logs | **Partially built** (workspace and stage changes; not calls, offers, or bot decisions) |
| Consent and DPDP | **Partially built** (student data requests; no consent to be called or WhatsApp-hired) |
| Admin dashboard | **Built** for onboarding companies and a BGV review queue |
| Employer dashboard | **Built**, and it is the real product today. The founder’s “no dashboard needed” path is the gap. |

---

## What the site says, and what the code does

Two voices live in the repo. They do not match.

**The employers page is mostly honest.** `apps/web/src/content/employers.ts` keeps a labelled `ROADMAP` for a public API, webhooks, CSV import, full background verification, and semantic search. The FAQ on that page says, in plain language:

- An outbound dialler is not connected.
- Background verification is not running yet.
- Camera and window-switch proctoring are not captured.
- A rule cannot reject someone and cannot release an offer.
- Self-serve calendar booking is not in the workspace.

**Some answer pages overshoot that.** `apps/web/src/content/answers.ts` describes, for hiring teams, “a disclosed screening call”, later rounds that “run async and proctored”, and candidates “notified on WhatsApp or email”. Those sentences are marketing. There is no screening-call code, proctoring flags are not stored, and sending an employer interview does not send WhatsApp or email (see step 4).

**A badge on the talent pool is a label.** `apps/api/app/Http/Resources/TalentPoolCandidateResource.php` sets `bgb_verified` to `true` for every real student in the pool. The comment in that file says it is a label until a real check exists. Sample demo people (`sample_candidates.bgb_verified`) are the same kind of badge.

**“Taurus” on the employer site is a real voice console, not a WhatsApp bot.** An employer who is already logged into the website can dictate a role. That is built. Scanning a QR from a phone lock screen is not.

**The only QR generator in the repo prints certificate links**, not a WhatsApp connect code (`apps/api/app/Support/Certificates/HtmlCertificateRenderer.php`).

---

## Step 1 — Onboarding by QR, many people on one company

**Status: Partially built.**

### What exists

A company is an `employer_workspaces` row: name, slug, website, industry, size, GSTIN, logo, locations, status (`active` or `suspended`).

- Migration: `apps/api/database/migrations/2026_07_30_150001_create_employer_workspaces_table.php`
- Model: `apps/api/app/Models/EmployerWorkspace.php`
- Decision record: `docs/adr/0051-employer-workspaces.md`

People join that company as `employer_members` with a role:

| Role | What they can do |
|---|---|
| `owner` | Workspace, members, jobs |
| `recruiter` | Jobs and pipeline |
| `hiring_manager` | Read and decide on the pipeline |

There is **no interviewer role**. A human round can store an `assigned_member_id`, but that person is still one of the three roles above.

Invites are email magic links (token, single use, expiry). Optional `name` and `whatsapp` on the invite are labels the inviter typed. They are not a WhatsApp connection.

- Actions: `RegisterEmployerWorkspace`, `OnboardEmployer`, `InviteEmployerMember`, `AcceptEmployerInvite`, `ClaimEmployerInvite`
- Email: `apps/api/app/Listeners/SendEmployerInviteEmail.php`
- Website: `apps/web/src/app/employer/(workspace)/team/page.tsx`, claim page `apps/web/src/app/employer/claim/[token]/page.tsx`
- Admin can onboard a company: `apps/web/src/app/admin/(panel)/employers/page.tsx` and `apps/api/app/Console/Commands/OnboardEmployerCommand.php`
- Login: `POST /api/v1/auth/employer/login` (`EmployerAuthController`, `EmployerLogin`) — email and password, optional OTP

### What is missing for this step

- A QR code whose only job is “open WhatsApp and attach me to this company”.
- A WhatsApp identity on the member (phone number verified against the Business account).
- Routing so HR, a hiring manager, and an interviewer each message the **same** bot and land in the **same** company.
- Onboarding that does not ask anyone to open the website. Today the first door is the employer login page (`apps/web/src/app/employer/page.tsx`).

---

## Step 2 — Job by voice note or text, ranked CVs in about 4–5 seconds

**Status: Partially built.**

### What exists (on the website, after login)

The hiring console (“Taurus”) can take speech or typing and turn it into job fields.

| Piece | File | What it actually does |
|---|---|---|
| Read the sentence | `apps/api/app/Actions/Employers/ReadHiringIntent.php` | Sends the text to the AI gateway (`jd_intent` prompt). Returns title, experience, locations, openings, remote. It is told to leave blanks rather than invent numbers. |
| Draft the JD | `DraftJobDescription.php`, `ReviseJobDescription.php` | AI draft and one-line edits. The employer still publishes. |
| Microphone | `apps/api/app/Http/Controllers/Employer/TranscribeController.php` | Browser recording → ElevenLabs speech-to-text, only if `ELEVENLABS_API_KEY` is set. Chrome can also transcribe on the device. This is **not** a WhatsApp voice note. |
| Spoken reply | `SpeakController` | ElevenLabs text-to-speech back to the browser. |
| Save and publish | `CreateEmployerJob`, `PublishEmployerJob` | Draft → published. Publish also queues a JD-specific mock interview. That mock is **not** instant. |
| Screen | `apps/web/src/app/employer/(workspace)/taurus-ai/page.tsx` | The voice console. |
| Typed form | `apps/web/src/app/employer/(workspace)/jobs/new/page.tsx` | Same create/publish APIs without the mic. |

### How CVs are ranked today

Two different scores exist. Neither is “search the whole CV database and WhatsApp the top files in 4–5 seconds”.

**1. Talent pool (closest to what the founder asked).** `apps/api/app/Support/Employers/LmsTalentMatcher.php`

- Runs when someone opens the pool on a job in the dashboard.
- Only **students** (`user_type = student`) who have finished the AI Readiness Interview (`cv_mock_completed_at`) and whose CV actually overlaps the role.
- Score is a fixed formula: skills 50%, readiness 20%, mock average 15%, CV-readiness interview 15%. No AI call, on purpose, so the page stays fast.
- It loads matching students in PHP and scores them there. That is fine for a small pool. It is the wrong shape for a guaranteed 4–5 second reply once the pool is large, and it never runs from WhatsApp.
- A second click can shortlist someone and WhatsApp them (`ShortlistTalentPoolCandidate` + template `talent_pool_shortlisted`). That message tells the **candidate** they were shortlisted. It does not send CVs **to the employer**.
- Demo rows: `SampleTalentMatcher` and `sample_candidates`, only when the API is asked for samples (`is_sample: true`).

**2. Match stored when someone applies.** `apps/api/app/Support/JobFeed/RelevanceScorer.php` → `scoreForEmployerJob()`

- Keyword overlap between the job’s skill list and the student’s profile, plus a role-title bonus.
- Saved as `employer_job_applications.cv_match_pct`.
- Used as an optional gate on automation rules. It is **not** shown on the employer application card.

There is no embedding / “describe the person in a sentence” search. The employers page lists that under `ROADMAP`.

### Why 4–5 seconds is not true today

A WhatsApp voice note would have to be downloaded, transcribed, parsed, matched, and sent back. None of that chain exists. Speech-to-text alone, plus the AI parse, usually takes longer than 4–5 seconds before matching starts. The dashboard path also waits on a human to publish, and publishing queues a mock, which is a separate AI job.

### What is missing

- Inbound WhatsApp text and voice-note handling for employers (today’s webhook only stores inbound **text**, and only for CRM leads and support tickets — see notifications below).
- A reply that contains the top CVs (name, match %, one-line why, link or PDF).
- A match that does not require the student to have finished the readiness interview, if the founder wants a wider CV database.
- A measured budget: acknowledge immediately, send the list as soon as it is ready, and do not block on mock generation.

---

## Step 3 — AI voice calls to check interest and screen

**Status: Not built.**

### What people might think is this, and is not

| Thing in the repo | What it really is |
|---|---|
| Vapi voice mocks | A **student** opens a mock in the **browser**. `HttpVapiClient` creates a Vapi **web** call (`/call/web`) and the portal opens `join_url`. Files: `apps/api/app/Support/Mocks/HttpVapiClient.php`, `StartVoiceMock.php`, `apps/api/app/Http/Controllers/Webhooks/VoiceWebhookController.php`. If `VAPI_API_KEY` is empty, the portal falls back to an in-browser room. |
| `RETELL_API_KEY` in `apps/api/.env.example` | Named only. No code reads it. |
| Employer FAQ | States that an outbound dialler is not connected (`apps/web/src/content/employers.ts`). |
| Answer pages | Describe a “screening call” that the API does not place. |

There is no `call_logs` table, no “I spoke to 10, 5 are interested” report, and no job that dials a candidate.

### What is missing

Outbound calls, a spoken disclosure that the caller is an AI, interest and availability captured in a structured way, a WhatsApp summary back to the requester, and consent to be called (see DPDP below).

---

## Step 4 — L1 AI interview and a clearance report

**Status: Partially built.**

### What exists

After someone is on a job, the company can define rounds and send an L1.

- Rounds table: `employer_job_rounds` (`apps/api/database/migrations/2026_07_31_140001_create_employer_job_rounds_table.php`)
- Default process: L1 “role fit”, L2 “depth”, kind `ai_interview`, sent manually unless the company turns on auto (`apps/api/app/Support/Employers/InterviewProcess.php`)
- Send: `apps/api/app/Actions/Employers/SendInterviewRound.php` creates an `employer_interviews` row (status `invited` → `in_progress` → `submitted` → `graded` or `expired`) and fires `EmployerInterviewInvited`
- The candidate API accepts **written answers**: `apps/api/app/Http/Controllers/Me/EmployerInterviewController.php` (`index`, `show`, `start`, `submit`)
- Grading is a queued job: `apps/api/app/Jobs/GradeEmployerInterview.php` using prompt `apps/api/resources/prompts/employer_interview_grade.v1.md`. If the model fails, it does **not** invent a score.
- Pipeline stage `l1` exists (`apps/api/app/Enums/EmployerApplicationStage.php`)
- Moving someone to L1 can send the round (`CreateInterviewOnStageEntry`)
- Website for the company: job page and candidate page under `apps/web/src/app/employer/(workspace)/jobs/[id]/`

### Gaps that make this “partial”

- **`EmployerInterviewInvited` has no listener.** Nothing texts or emails the candidate when L1 is sent. They would have to find it in the API. There is no Next.js page that calls `me/employer-interviews`.
- The round is not a phone call and not the browser voice room. The pre-apply mock **does** use the voice room (`StartEmployerJobMock` → `apps/web/src/app/(portal)/student-ai-mock/[id]/room/page.tsx`). L1 does not.
- The company sees scores in the portal. There is no WhatsApp message of the form “X attended, Y cleared; send L2?”.
- Proctoring is not part of this round (see the proctoring note under step 5).

### Related, but it is the candidate’s practice mock, not L1

Before applying, a candidate must finish a JD-specific mock (`ApplyToEmployerJob`). That mock is the student mock interviewer (ADR 0029 / 0031): questions, turns, a scorecard, optional Vapi or the browser room. It is the right engine to reuse. It is not the employer’s L1 report on WhatsApp.

---

## Step 5 — L2, then a detailed report per finalist

**Status: Partially built.**

L2 is the same `EmployerInterview` path with `key = l2` and stage `l2`. Auto-send of the next platform round, when a score clears a bar, is `apps/api/app/Listeners/DispatchAutomaticInterviewRounds.php`. That listener **sends the round**. It does **not** move the pipeline stage (the comment in the file says stage changes stay with the rules engine so the decision stays visible).

What the company can read today on the candidate page:

- Answers, dimension scores, overall score, grading summary (`EmployerInterview` columns)
- Profile assembly: `apps/api/app/Support/Employers/CandidateProfile.php`
- That profile sets `proctoring_captured` to `false` on purpose

What does not exist:

- A per-candidate written brief delivered on WhatsApp (or as a PDF) to the requester
- Hiring-manager comments and @mentions (described on the employers page; no thread in the schema)
- A downloadable graded report PDF

### Proctoring — do not treat the marketing line as the product

The student mock **room** (`student-ai-mock/[id]/room/page.tsx`) watches tab visibility and blur in the browser and can abandon the mock after strikes (`MockController::abandon`). A face model is referenced there. An optional webcam recording can be stored on the **mock** (`recording_url`) and shown to the employer as a signed link.

None of those signals are saved as face-match, window-switch, or snapshot rows. Employer L1/L2 never see them. The employers FAQ already says camera and window-switch proctoring are not captured.

---

## Step 6 — Run the whole pipeline without asking yes/no

**Status: Partially built.**

### What exists

Per job, `employer_automation_rules` can say: when a mock or an interview is graded, if the score (and optionally `min_cv_match_pct`) clears a line, **advance** to `shortlisted`, `l1`, or `l2`, or **park** for a person to look.

- Model: `apps/api/app/Models/EmployerAutomationRule.php`
- Runner: `apps/api/app/Support/Employers/AutomationEvaluator.php` (queued)
- Default rule seeded when a job is published: `SeedDefaultAutomationRule`
- UI on the job page; tests in `apps/api/tests/Feature/Employers/AutomationRuleTest.php`
- Separate switch on each round: `dispatch = auto` plus `auto_min_score` sends the next **platform** round (`DispatchAutomaticInterviewRounds`)

The public FAQ matches the code: a rule cannot reject someone, and it cannot release an offer.

### What is missing for “the whole pipeline, one toggle”

There is no per-job autonomous flag that also covers:

- outreach calls
- the “shall I set up interviews?” pause
- L2 approval
- human-round booking
- pre-BGV
- the offer

`ALLOWED_TARGET_STAGES` stops at L2. Time-based reminders (“no reply in 48 hours”) are not in the rule runner. The “what would this rule have done to last month’s applicants?” count on save only checks mock score, not CV match.

This also **conflicts** with the current employer requirements (`docs/employer-module-requirements.md`), which say offer release always needs a person. The new journey wants a mode where it does not. That is a founder decision, not a small switch (see `REQUIREMENTS.md`).

---

## Step 7 — Human round: WhatsApp availability, then a meeting link

**Status: Not built** for this journey.

### What exists nearby

- Pipeline stage `human_round` and round kind `human`. `SendInterviewRound` refuses to “send” a human round. The company records the outcome on the board. Copy in `NotifyCandidateOfStageChange` says details will follow by email; no mail is actually sent for that sentence.
- **LMS placement interviews** (students booking a BrowseJobs panel, not an employer’s interviewer): `ApplyForInterview`, `NotifyPanelOfInterviewApplication` (WhatsApp template `interview_applied` to panel members — “someone applied”, not “pick a slot”), and `ReviewInterview` which can create a **Zoom** meeting when Zoom is configured.
- Mentor availability and slot finding (`SlotFinder`, `MentorAvailability`) are for mentor sessions, not employer interviewers.
- Zoom itself is real: `CreateZoomMeeting`, `HttpZoomClient`, a license pool, admin settings. Used for classes and those placement interviews.
- Google Meet: no code.

### What is missing

- Interviewers as people who can be messaged on WhatsApp for **this job**
- A slot offer, a tap to accept, a hold so two people cannot take the same slot
- Creating a Zoom (or Meet) link at that moment
- Invites to the candidate, the interviewer, and HR

---

## Step 8 — Pre-BGV, then “shall we offer?”

**Status: Partially built.** The plumbing is real. The check the founder described is not live, and the site says so.

### What exists

A verification state machine: not started → pending → verified, failed, or expired.

- Gateway: `apps/api/app/Support/Verification/VerificationGateway.php`
- Kinds: identity, education, employment, documents (`VerificationKind`)
- Default route for every kind is **`manual`** (`apps/api/config/verification.php`). An ops person settles it.
- Admin queue: `apps/web/src/app/admin/(panel)/verifications/page.tsx`
- Admin can type vendor URLs and keys under Settings → Background verification (`apps/api/config/platform_settings.php`, env names `DIGILOCKER_*`, `EPFO_*`, `VERIFY_*_PROVIDER` in `apps/api/.env.example`)
- `DigiLockerProvider` and `EpfoProvider` POST to `{base}/v1/verify` and `{base}/v1/employment/verify`. If the URL or key is empty, or the call fails, the result stays **pending**. They do not store the passbook or the document. Tests fake HTTP (`apps/api/tests/Feature/Verification/BgvProviderTest.php`).
- Candidates can upload files, including a kind called `offer_letter`, for a human to look at (`CandidateDocument`, `DocumentVault.tsx`, `AssessCandidateDocument`). That is “check a file the candidate uploaded”, not “pull EPFO and then offer”.
- The employer profile can show verification **outcomes**. It does not show documents.

### Why this is not step 8

- The HTTP clients are a **generic adapter**, not Perfios, Setu, or DigiLocker’s real consent flow. No ADR for a chosen vendor is in `docs/adr/` (comments mention “ADR 0052”; that file is not in the repo).
- Nothing starts a check because HR said yes on WhatsApp.
- Nothing sends HR “here is his pre-BGV”.
- The employers FAQ: “Do you run background verification? Not yet.”

---

## Step 9 — Offer letter from the company template, by email

**Status: Not built.**

- Stage `offer` and stage `hired` are labels on `employer_job_applications`. `MoveApplicationStage` can set them. Automation is not allowed to.
- There is no `offers` table, no uploaded company template, no PDF render of an offer, no email of that PDF, no accept/decline, no e-sign.
- The candidate hears an in-app line (“open your applications to see the terms”) from `NotifyCandidateOfStageChange`. There are no terms attached.
- `CandidateDocument` kind `offer_letter` is an upload for verification, not a letter BrowseJobs generates.
- Email as a channel **does** exist for other messages (`SendEmailMessage`, `MessageMail`, SMTP settings in `.env.example`). It is not pointed at offers.

**Decided, 7 October 2026.** A person always releases the offer letter, even in autonomous mode. The employer FAQ and `docs/employer-module-requirements.md` stay as written. Autonomous mode does not send the letter.

---

## Step 10 — Engagement bot, dropout risk, joining details

**Status: Not built** for hiring candidates.

### The dropout number that already exists is for students in class

`apps/api/app/Support/Scoring/ScoreCalculator.php` (PRD section on coach scores):

`risk_dropout = 55% of “not engaged” + 25% if learning has stalled + 20% if fees are blocked`

It is stored on `student_scores`, shown to counsellors (`apps/web/src/app/admin/(panel)/risk/page.tsx`), and used in digests. Inputs are batch activity and fees. It knows nothing about an offer, a joining date, or a candidate going quiet on WhatsApp.

`CandidatePerformance` (ADR 0050) is also about students: attendance, mocks, assignments.

### Other “engagement” that is not this bot

- `MomentumNudge` on the candidate home page: a nudge to keep applying and learning. Not a post-offer HR chat.
- CRM lead reply tracking: inbound WhatsApp from a **lead** (someone who filled a form), not from a hired candidate.
- No joining-date messages, no “what to bring”, no score that predicts an offer drop, no automatic alert to HR when that score rises.

---

## Candidate data connectors

The founder wants matching to use two pools: BrowseJobs’ own CVs, **and** the client company’s own people (their spreadsheet, their inbox, their Drive, their ATS, Naukri, LinkedIn). Connecting that data should be easy, including from WhatsApp.

**Status for the whole idea: Not built.** Pieces we can reuse are listed below. Nothing today creates a private, company-scoped candidate list, and nothing searches one.

### What exists, and what it is actually for

| Founder’s source | Closest code | What that code really does |
|---|---|---|
| Excel / CSV on the web | `apps/api/app/Support/Import/XlsxReader.php` (first sheet only, no dates or formulas). CSV importers: `ImportLeadsCsv`, `ImportRosterCsv`, `ImportSyllabusCsv`, `ImportMarketJds`, admin job-feed CSV in `JobFeedController`. Screens: admin leads, batch roster, curriculum, market JDs. | Staff tools for **leads, students, syllabi, and job ads**. No employer screen accepts a candidate file. |
| Excel, CSV, or CV PDFs/ZIP on WhatsApp | `WhatsAppController` | Inbound webhook stores **text only**. A document, image, or zip is ignored. |
| Email (inbound address or a connected mailbox) | `SendEmailMessage`, `MessageMail`, `MAIL_*` in `.env.example` | **Outbound** mail only. No inbound parser, no per-company address, no Gmail or Outlook mailbox connection. |
| SharePoint / OneDrive | — | No Microsoft Graph, SharePoint, or OneDrive code. |
| Google Drive | `apps/api/app/Support/Drive/GoogleDriveClient.php`, `SyncDriveReviews`, admin setting `google_drive` | One **platform** service account, read-only, listing **images** in a single reviews folder. Not per company, not OAuth by the client, not PDFs or spreadsheets. `GOOGLE_CLIENT_ID` is Google **login** for users (`GoogleAuthController`), and that consent does not include Drive files. |
| Client database or ATS | Employers FAQ and `ROADMAP` in `apps/web/src/content/employers.ts`. `POST me/jobs/{item}/copilot` in ADR 0046. | The public page says CSV/ATS import is **not available**. The copilot route is a stub that returns 501 when the flag is on. No Greenhouse, Lever, Keka, Darwinbox, or Zoho Recruit client. No SFTP drop. `private_pool_candidates` from the employer PRD was never migrated. |
| Naukri (“Knockery”) and LinkedIn **candidates** | `ScraperAdapter`, `ApifyTransport`, `feed:add-scraper`, ADR 0048. Also `HttpJSearchTransport` (ADR 0045). LinkedIn **profile text** for a student: `OptimizeLinkedin`. | These pull **job advertisements** onto the student job board (Naukri and LinkedIn via Apify actors; JSearch as a licensed job API). They do not pull a recruiter’s resume database. ADR 0048 records that scraping job ads was a conscious break of the earlier “no scraping” rule, with terms-of-service risk. That code must not be pointed at Resdex or LinkedIn Recruiter. |
| CV parsing (needed once files arrive) | `CvController::importCv`, prompt `cv_parse`, `PdfExtractor`, `DocxExtractor` | A **logged-in student** uploads one PDF, docx, or text file. Text is extracted and the AI fills **their** `CvProfile`. A scanned PDF with no text layer fails honestly. There is no ZIP unpack, no batch, and no path that stores the result on a company instead of the student. |
| De-duplication | `ImportLeadsCsv` skips a lead whose phone already exists. Job-feed ingest fingerprints company+title+location (ADR 0045). | Same idea, wrong tables. Nothing de-dupes people inside a company pool, and nothing stops a client file from being written onto the shared student pool — because the client file is never imported. |

### What is missing

- A private pool per company, invisible to every other company and not mixed into the BrowseJobs student pool.
- Upload on the employer website, a WhatsApp document handler, and an inbound mailbox.
- Column mapping (AI-suggested, human-confirmed) and batch CV parsing.
- De-dupe on email and phone **inside that company**, with a consent rule before any row is joined to a BrowseJobs student.
- Ongoing sync (Drive, mailbox, ATS). Everything reusable above is either one-shot (CSV paste) or a scheduled pull of **job ads / review images**.
- Official access to Naukri Resdex/RMS or LinkedIn Recruiter. Those products do not offer an open public API (see `REQUIREMENTS.md`).

---

## Supporting pieces

### Company accounts and auth — Built for the website

Covered in step 1. Extra notes:

- A person is a `users` row with `user_type = employer`, same login system as the rest of the app (Sanctum cookie, ADR 0004).
- One person can belong to more than one workspace (the agency case in ADR 0051).
- Every employer table is tenant-scoped. Tests include cross-tenant and cross-workspace denial (for example `apps/api/tests/Feature/Employers/EmployerJobApiTest.php`).
- Suspended workspaces are rejected on employer routes.
- Settings page in the portal is effectively read-only; the API can patch the workspace (`WorkspaceController`) but the screen does not expose that.
- There is no SSO, no API key for the employer’s own systems, no webhooks out to an ATS. Those are on the public `ROADMAP`.

### CV database and matching — Partially built

| Store | Path | Who is in it |
|---|---|---|
| Structured profile | `apps/api/app/Models/CvProfile.php` | One row per user: skills, experience, education |
| Generated CV versions | `apps/api/app/Models/CvDocument.php` | Versions the CV tool produced, including a tailored copy used on apply |
| Uploaded verification files | `candidate_documents` | ID, education, employment papers, offer scans |
| External job-board CVs | — | Not a database we search for employers |

Matching is explained under step 2. There is no vector index. The talent-pool query is “students who finished one interview”, not “every CV we have ever stored”, and it does not see a client’s own files. Connector gaps are in the section above.

### Notifications — Partially built

The messaging hub is real and is the right place to send this journey’s messages.

- One service: `apps/api/app/Support/Messaging/Messenger.php`
- WhatsApp transport: `apps/api/app/Support/WhatsApp/HttpWhatsAppClient.php` (Meta Cloud API)
- Inbound webhook: `POST /api/webhooks/whatsapp`, signature checked (`VerifyWhatsAppWebhookSignature`). Text bodies only. They attach to a CRM lead and, if the sender is a student with an open ticket, to that ticket (`WhatsAppController`, `RecordInboundMessage`). **No bot replies.**
- Email and in-app are live. Web push is a null sender.
- Guards for marketing messages: opt-in, quiet hours 21:00–09:00 IST, and a daily cap (`apps/api/config/messaging.php`). Transactional sends can bypass them. A hiring bot must decide, per message, which side of that line it is on.
- Templates are named in `apps/api/config/whatsapp_templates.php` and synced to Meta on a schedule (`whatsapp:sync-templates`).
- Employer-journey use of this hub today: **one** template, `talent_pool_shortlisted`, to the candidate. Interview invites and “you were invited to apply” events do not send.

### Audit logs — Partially built

`AuditLogger` is called for workspace registration, invites, joins, role changes, removals, job create/publish/status, and admin suspend. Stage moves store an actor on `application_stage_transitions` (`user`, `rule`, or `system`). Automation runs are stored on `employer_automation_runs`.

Not audited, because the features do not exist: bot decisions, call placement, recording access, BGV pulls started by HR, offer release, template changes, or “who watched the recording” beyond what the portal already logs for other evidence.

### Consent and DPDP — Partially built

| What exists | Where |
|---|---|
| Student access and deletion requests | `apps/api/app/Models/DataRequest.php`, `docs/adr/0047-dpdp-data-requests.md`, portal privacy UI |
| Export of profile, CV, enrolments, LMS applications, payments | `apps/api/app/Support/Dpdp/DataExporter.php` |
| Erasure | `apps/api/app/Support/Dpdp/AnonymizeStudent.php` |
| Account consent version | `users.telemetry_consent_at`, `config/dpdp.php` |
| Marketing WhatsApp opt-in | `message_preferences.marketing_opt_in` |

Not built:

- Consent on an employer application (`employer_job_applications` has no consent-scope column). The exporter does not include employer applications.
- A separate opt-in for **AI voice calls** and for **WhatsApp messages from a hiring bot** (different from “send me the daily brief”).
- A record that the candidate was told the caller is an AI, that the call is recorded, and that they can refuse.
- Withdrawal that hides the person from the talent pool and stops further calls.

Calling people who only signed up as students, without a fresh yes, is the main compliance gap in this journey.

### Admin dashboard — Built for ops, not for the bot

| Screen | Path | Use |
|---|---|---|
| Companies | `apps/web/src/app/admin/(panel)/employers/page.tsx` | Onboard, suspend |
| Verification queue | `apps/web/src/app/admin/(panel)/verifications/page.tsx` | Manual BGV |
| Platform keys | `apps/web/src/app/admin/(panel)/settings/page.tsx` | AI, WhatsApp, Vapi, Zoom, BGV URLs |
| Student risk | `apps/web/src/app/admin/(panel)/risk/page.tsx` | Batch dropout, not offer dropout |
| Message log | `apps/web/src/app/admin/(panel)/messages/page.tsx` | Delivery |
| CV approval | `apps/web/src/app/admin/(panel)/cvs/page.tsx` | Student CVs |

### Employer dashboard — Built, and it stays useful

Even if WhatsApp becomes the front door, the system of record is already this portal:

- Home: `apps/web/src/app/employer/(workspace)/dashboard/page.tsx` (counts, funnel)
- Jobs, pipeline (board and table), team, a small command palette (`apps/web/src/components/employer/CommandPalette.tsx` — jump to pages and jobs, not “release offer”)
- Upgrade page is a “contact sales” screen. Credit numbers exist only in `apps/api/config/employers.php`. Nothing deducts them.

---

## Inventory of reusable machinery

These should be extended, not rewritten, when the journey is built.

| Need | Reuse |
|---|---|
| Company, roles, invites, audit | Employer workspace module |
| Parse “I need engineers in Bangalore” | `ReadHiringIntent` and the `jd_intent` prompt |
| Speech to text | ElevenLabs path in `TranscribeController` (new caller: WhatsApp media, not the browser) |
| Rank students against a JD | `LmsTalentMatcher` and `RelevanceScorer` |
| Read a spreadsheet | `XlsxReader` and the admin CSV importers (leads, roster, syllabus, job ads — not candidates) |
| Read one CV file | `PdfExtractor`, `DocxExtractor`, `cv_parse` prompt (student profile only) |
| Read a Google Drive folder | `GoogleDriveClient` (review images only; wrong auth model for a client folder) |
| Send WhatsApp / email safely | `Messenger`, templates, webhook signature check |
| AI of any kind | `apps/api/app/Services/AI/AiGateway.php`, `config/ai.php`, `ai_events` |
| L1/L2 questions and grading | `EmployerInterview`, `GradeEmployerInterview`, JD mock generator |
| Spoken mock in the browser | Student room + Vapi web client |
| Meeting link | Zoom client and license pool |
| Check identity / employment later | `VerificationGateway` (replace the placeholder HTTP with a real vendor) |
| Queues | Redis + Horizon (`QUEUE_CONNECTION`, `HORIZON_PREFIX` in `.env.example`) |
| Honest public limits | `ROADMAP` and the employers FAQ — update them when a step actually ships, not before |

---

## Bottom line

The company can already sign up on the web, post a job, see a ranked list of **trained BrowseJobs students**, run a written L1/L2, and let a score rule move people as far as L2. WhatsApp can already deliver template messages, and one of those templates fires when a student is shortlisted.

The journey the founder described — QR, voice note, CVs back on WhatsApp, AI calls, approval gates or a full auto mode, human scheduling, pre-BGV, offer PDF, and a joining bot — is a new product surface on top of that desk. The largest empty rooms are outbound calling, the WhatsApp conversation itself, offers, anything that happens after a yes to hire, and a private pool of the **client’s** candidates. Naukri and LinkedIn in this repo are job-ad feeds for students, not a way into a client’s resume database.
