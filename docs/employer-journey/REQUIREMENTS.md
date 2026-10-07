# Employer WhatsApp hiring journey — requirements

**Status:** Draft for Dr Krish Bharggav
**Date:** 7 October 2026 · **Updated:** 7 October 2026 (candidate connectors, mission control)
**Evidence:** `GAP-ANALYSIS.md` in this folder (what the repo does today)
**Does not replace** `browsejobs-lms-requirements.md` (the LMS) or `docs/employer-module-requirements.md` (the website hiring desk). The offer rule now agrees with the employer desk: a person always releases the offer letter, including when autonomous mode is on. That decision is recorded in section 2 and under Decided.

---

## One-page summary

BrowseJobs already has a website where a company logs in, posts a job, and works a pipeline. This project adds a second front door: **WhatsApp**. The website stays. It is the record. WhatsApp is how HR runs the job without living in that screen.

**What the employer feels**

1. Scan a QR. They are on the company bot. Colleagues scan the same company and land in the same account.
2. Send a voice note or a text: “I need tech engineers, Bangalore, salary X.” The bot writes the job and searches **two** places: BrowseJobs CVs, and the company’s own people (a spreadsheet, a WhatsApp file, or an email forward in the first release). Each match says where it came from.
3. It asks “Shall I start reaching out?” On yes, it phones matched candidates, checks interest, and reports who said yes.
4. On yes, those people sit an L1 AI interview built from their CV and what they said on the call. HR gets “who attended, who cleared.”
5. Same for L2. HR then gets a short written report on each person who cleared both.
6. A switch on the job can skip the yes/no questions and run the earlier steps on its own. Default is **ask first**. It always stops before the offer.
7. Optional: “Set up a human round?” The bot asks the interviewers on WhatsApp for a slot, then sends one meeting link to the candidate, the interviewer, and HR.
8. Optional: “Run pre-BGV?” The bot calls an employment-history check (EPFO/PF) and a document check (DigiLocker) and shows HR a short summary.
9. A person must say yes before any offer goes out, even when autonomous mode is on. On that yes, it fills the company’s offer template and emails it to the candidate.
10. From offer until after joining, a WhatsApp bot talks to the candidate the way a careful HR person would. If the replies suggest they may not join, HR is alerted, with a rank. Joining date, place, and what to bring go out before the day.
11. On the website, a live mission-control desk shows the same job: which phase it is in, who is in each phase, and what each bot is doing right now. WhatsApp stays the remote control. The desk is the window.

**What we will not claim**

Nobody is guaranteed a job. The bot must not say “guaranteed”, “100% placement”, or a fixed salary outcome. Salary in the job is what the company offered to pay, not a promise to the candidate about their future. The line already used on the site still holds: hiring depends on the market and the person’s performance.

**What “done” is not**

The public employers page already refuses to claim an outbound dialler, live BGV, or proctoring. Those sentences stay true until the phase that builds them has shipped and been tried on a real number.

**How we ship it**

Six phases. The first one is the only one that must exist before anything else is worth demoing: QR, voice or text job, CVs back on WhatsApp.

| Phase | What HR can do at the end of it | Size |
|---|---|---|
| 1 — MVP | Connect by QR. Send a role. Get ranked BrowseJobs CVs on WhatsApp. The desk already shows the job, the sourcing column, and the WhatsApp thread moving. | Medium. Mostly new conversation code on top of the job and talent-pool code. |
| 1b — Their files | Drop an Excel on the website, send a file in WhatsApp, or forward mail to a company address. Those people are ranked in the same list, labelled with their source. | Medium. Parsing tools exist. The private pool and the WhatsApp/email doors do not. |
| 2 — Calls | Approve outreach. Bot phones candidates and reports interest. | Large. New phone vendor, new consent, new cost control. |
| 3 — L1 and L2 | Approve interviews. Candidates sit them. HR gets the clearance note and the write-up. | Medium. The interview and grading code exist; delivery and the candidate screen do not. |
| 4 — Human round | Interviewers pick a slot on WhatsApp. A Zoom link goes to all three parties. | Medium. Zoom exists. Slot booking for employers does not. |
| 5 — Pre-BGV and offer | HR sees a pre-BGV summary and can send an offer email from their template. | Large. Vendor contracts and a letter generator. |
| 6 — Engagement | The candidate has an HR-style bot through joining, and HR hears dropout risk. | Medium-large. New bot and a new score. Do not reuse the student dropout formula. |
| 7 — Live folders and ATS | Connect Google Drive or OneDrive/SharePoint, or an ATS, and keep the pool updated. | Large. Real OAuth and a vendor. Not a file upload. |
| 8 — Job boards | Bring in Naukri or LinkedIn **only** through the client’s export or an official partner agreement. | Contract first, then a medium build. There is no public API to switch on. |

Size means how much new product this is, next to the employer desk already in the repo. It is not a calendar promise.

The mission-control screen is not an extra hiring step. It is the window onto the steps above. A look-only preview, with sample names and a looping story, is at `/employers/mission-control-demo`. It is not linked from the public site and it is not indexed. The real desk replaces that preview one phase at a time.

**Decisions needed before phase 1 starts**

1. Whose BrowseJobs CVs may we show, and does the client confirm they have the right to upload their own people?
2. ~~May the bot send an offer with no human click?~~ **Decided:** no. A person always releases the offer letter, even in autonomous mode.
3. Which WhatsApp number, and who owns the Meta Business account?
4. Written consent for calls and for this bot, separate from student marketing opt-in.
5. Naukri and LinkedIn: start with the client’s own export (phase 1b), and only chase a partner API if a customer contract needs it.

The full list is at the end.

---

## 1. Who uses it

| Person | How they enter | What they may do |
|---|---|---|
| **Owner** | First QR, or the existing website signup | Connect the company, invite others, upload the offer template, turn autonomous mode on, and release every offer. Autonomous mode does not send the letter. |
| **Recruiter / HR** | QR or invite | Raise jobs, approve each step (unless autonomous), read reports, decide pre-BGV and offers |
| **Hiring manager** | QR or invite | Read shortlists and reports, approve or reject a person, comment |
| **Interviewer** | Invite, new role | Receive slot requests for rounds they are on. They do not see the whole pipeline. |
| **Candidate** | Already on BrowseJobs, or reached by the bot after consent | Receive the call, sit L1/L2, get the meeting link, get the offer, chat with the joining bot |
| **BrowseJobs ops** | Existing admin | Suspend a company, see message failures, settle a BGV check the vendor could not finish |

Rules:

- Several people can sit on one company. The bot must know **who** sent the message (their WhatsApp number) and **which company** they belong to.
- If one number is on two companies, the bot asks which company before it does anything.
- Interviewer is a new role. Today the code only has owner, recruiter, and hiring manager (`apps/api/app/Enums/EmployerRole.php`).
- The website roles stay. WhatsApp does not replace them.

---

## 2. The pipeline, as states

Two switches sit on the **job**:

- **Autonomous** — off by default. When off, the bot stops at every question in the list below. When on, it walks the earlier states and only stops for a hard failure (no consent, vendor down, nobody free for a human round, offer template missing) **and always stops before the offer**. A person must release the offer letter. Autonomous mode never sends it.
- **Human round** — off by default. When off, clearing L2 goes to the offer question (or straight to pre-BGV if that is also on). When on, clearing L2 asks for a human round first.

### Job states

```
draft
  → matching          (CVs being scored)
  → shortlist_sent    (top CVs are on WhatsApp)
  → awaiting_outreach (only if autonomous is off)
  → outreaching
  → awaiting_l1
  → l1_open
  → awaiting_l2
  → l2_open
  → finalists_sent
  → awaiting_human    (skipped if human round is off)
  → scheduling
  → awaiting_bgv      (skipped if the company turned pre-BGV off)
  → bgv_running
  → awaiting_offer
  → offer_sent
  → closed
```

A job can also be `paused` or `closed` by any HR message “stop this role”. Pausing cancels calls not yet placed and stops new invites. It does not delete the record.

### Where a person comes from

Matching reads two lists, then one pipeline:

1. **BrowseJobs pool** — students who are allowed to be shown to employers.
2. **This company’s private pool** — people imported from that company’s file, email, folder, or ATS.

An import does not skip the line. A row becomes a private candidate (`imported` → `ready`), and the next job search can rank them. From `matched` onward they walk the same states as everyone else. Outreach, L1, L2, the human round, pre-BGV, and the offer do not care which door they came through.

Every match HR sees carries a **source**, in words: “BrowseJobs”, “your Excel”, “WhatsApp file”, “email”, “Google Drive”, “OneDrive”, “ATS”, “Naukri export”, “LinkedIn export”. If the same person is in both pools, HR sees **one** card and both labels, and we place **one** call.

```
file / email / folder / ATS
  → imported          (private to this company)
  → ready             (parsed, de-duplicated)
  → matched           (same door as the BrowseJobs pool)
  → outreach_queued → … → joined
```

### Candidate states (one person on one job)

```
imported → ready      (private pool only; skipped for people who only exist in BrowseJobs)
matched
  → outreach_queued
  → called            (or no_answer, after retries)
  → interested        (or declined, or not_suitable)
  → l1_invited → l1_done → l1_cleared / l1_not_cleared
  → l2_invited → l2_done → l2_cleared / l2_not_cleared
  → human_invited → human_scheduled → human_cleared / human_not_cleared
  → bgv_pending → bgv_clear / bgv_review
  → offer_sent → offer_accepted / offer_declined / offer_expired
  → joining → joined
  → withdrawn         (candidate opted out at any point)
```

Every move stores who caused it: a person, the autonomous switch, or a vendor callback. The website pipeline already has stages (`applied`, `graded`, `shortlisted`, `l1`, `l2`, `human_round`, `offer`, `hired`, `rejected`, `withdrawn`). Map the new states onto those so the board and the bot never disagree. Add the extra states (called, interested, bgv, offer accepted) rather than overloading `shortlisted`.

**Approval questions, in order, when autonomous is off**

1. “Here are the top matches. Shall I start reaching out?”
2. “I spoke to N, M are interested. Shall I set up L1?”
3. “L1: X attended, Y cleared. Send L2?”
4. “L2 done. Here are the reports. Set up a human round?” (only if the job allows one)
5. “Human round done. Run pre-BGV?” (only if the company has pre-BGV on)
6. “Here is the pre-BGV summary. Shall we offer?”

Each question has Yes, No, and “show me more” (next page of CVs, or the transcript). Silence does not mean yes. A reminder goes the next business morning, once, then the job waits.

**Autonomous mode** still sends the same updates (“I spoke to 10, 5 are interested, L1 is booked”) so HR is never surprised. It does not wait on questions 1–5. Question 6 always waits. A person must release the offer letter, even when autonomous mode is on.

---

## 3. Candidate data connectors

Matching is useless if the only people in it are BrowseJobs students. The client already has candidates: a spreadsheet, a folder of CVs, an inbox, an ATS, a Naukri login. This section is how those people get in, stay private, and then join the same pipeline as everyone else.

**Rule for every source.** The client’s people live in a **private pool** for that company. They are never copied into the shared BrowseJobs student database. Another company cannot search them. We de-duplicate inside the company (same email or same phone → one person). We link a private row to a BrowseJobs student only when the email matches **and** that student has agreed to be shown to employers. The client’s notes do not become part of the student’s profile.

**Rule for the client’s permission.** Before the first import, the owner confirms a short sentence: they have a proper reason to share these people’s data with BrowseJobs, and those people have been told or will be told before anyone calls them. We store that confirmation. A call still needs the candidate’s own yes (section 11.5). Uploading a file is not consent to be phoned.

**Sync, in two speeds.**

- **One-off.** A file or a zip. We import it, tell HR the counts (“42 new, 7 already in your pool, 3 unreadable”), and stop.
- **Ongoing.** A folder, a mailbox, or an ATS. We check on a schedule (default every few hours, not every minute) and only add what is new. HR can say “stop syncing” on WhatsApp or on the website.

### Excel or CSV on the website — phase 1b, one-off

HR opens the company workspace and drops a `.csv` or `.xlsx`. This is the reliable path when the file is large.

The bot (and the page) do not ask HR to rename columns. An AI pass suggests a mapping: name, email, phone, city, skills, years, current company, notice period, salary if present. HR sees the first five rows with that mapping and replies YES, or fixes one column. We remember the mapping for the next file from that company.

We already have a small Excel reader (`XlsxReader`) and several admin CSV importers. They read leads and syllabi, not candidates, and the Excel reader only takes the first sheet. Phase 1b extends that pattern. It does not start from zero, and it is not done.

### Excel, CSV, or CV PDFs in the WhatsApp chat — phase 1b, one-off

HR sends the file to the bot, or says “I’ll send a spreadsheet.” The bot replies “Send it here.”

Accepted: `.csv`, `.xlsx`, `.pdf`, `.docx`, and a `.zip` of those. The current WhatsApp webhook ignores anything that is not text, so this is new work. WhatsApp’s own limit is about 100MB; we should tell HR that a huge zip is happier on the website link.

A spreadsheet follows the same mapping YES as the website. A PDF or docx uses the CV parser we already run for a student’s own upload (`PdfExtractor`, `DocxExtractor`, prompt `cv_parse`). A scanned photo-PDF often has no text. We say so and ask for a text PDF or a spreadsheet, rather than pretending we read it. OCR can wait.

When the import finishes, the bot says the counts and “These people will be included the next time you search, and in the role we just opened.” If a role is already waiting on matches, we re-run matching.

### Email — phase 1b for forwarding, later for a connected mailbox

**Easy path (phase 1b, can be ongoing).** Each company gets an address like `acme@in.browsejobs.ai`. HR forwards a CV, or a mail with a spreadsheet attached, to that address. We accept mail only to that exact address, ignore the rest, and import attachments the same way as a WhatsApp file. There is no inbound mail code today. Outbound SMTP exists. A provider such as Postmark, Mailgun, or Amazon SES inbound is the usual way to receive it. Cost is small (often included, or a few dollars per thousand inbound messages).

**Connected mailbox (phase 7, ongoing).** “Connect Gmail” or “Connect Outlook” opens a magic link. HR signs in and grants read access to one label or folder (“BrowseJobs”), not the whole mailbox. We poll that folder. This is OAuth (Gmail API, Microsoft Graph). It is a real API and a real consent screen. It is more than phase 1b, so it waits.

### Google Drive, and SharePoint / OneDrive — phase 7, ongoing

**How HR connects.** On WhatsApp: “Connect Drive.” The bot sends a link. HR signs in with Google or Microsoft, picks **one folder**, and comes back. We store a refresh token for that company, encrypted. We do not ask them to paste a password.

**What we sync.** New and changed CSV, Excel, PDF, and docx files in that folder. Same parser and the same private pool. A nightly (or few-hourly) delta is enough.

**Honest status of our code.** Google Drive code exists, and it is the wrong shape: one BrowseJobs service account listing **images** in a reviews folder (`GoogleDriveClient`, `SyncDriveReviews`). Google login for users does not grant Drive access. SharePoint and OneDrive are not in the repo. The official APIs themselves are fine: Google Drive API, and Microsoft Graph for OneDrive and SharePoint. This phase is ordinary OAuth work, not a partnership.

### The client’s database or ATS — phase 7, ongoing

Three ways, from the one we should sell first to the one we should avoid.

1. **Unified ATS API (first choice when they have a known ATS).** One vendor sits between us and Greenhouse, Lever, Workday, Ashby, and others. HR clicks a magic link, signs into **their** ATS, and we read candidates (and sometimes jobs) through the vendor. We do not store their ATS password.
   - **Merge.dev** — widest catalogue. Public plans have typically started around a few hundred to about $650 USD per month, plus a fee per connected company. Confirm the current quote.
   - **Kombo** — often a lower quote, strong on European HR tools. Confirm Indian coverage before signing.
   - **Apideck** — has a free sandbox and paid Unify plans that have often started lower than Merge. Connector quality varies. Confirm the exact ATS.
   - Indian tools (Keka, Darwinbox, Zoho Recruit, greytHR, Freshteam) are **not** a given on those catalogues. Ask the first five customers which ATS they actually use before picking the vendor.
2. **Secure file drop (best fallback).** A per-company SFTP folder or a scheduled CSV the client’s IT pushes. Laravel already knows the word SFTP in its filesystem config; nothing uses it for candidates. One-off or nightly. No OAuth, no per-seat API fee. Easy to explain to a DBA.
3. **Read-only database login (last resort).** A read-only user, IP allow-list, no writes. It works, and it is the easiest way to leak a production database. Only if the client refuses the first two, and only with their security team in the room.

There is no ATS client in the product today. The employers page says so. A student “apply copilot” route returns 501 and is not this feature.

### Naukri and LinkedIn — phase 1b if they export, phase 8 only with a contract

“Knockery” is Naukri.

**There is no switch we can flip.**

- **Naukri Resdex** (their resume database) and **Naukri RMS** do not offer an open public API for a third party to pull a recruiter’s candidates. Access is a Naukri enterprise or partner agreement, or the client exports Excel from their own Naukri login and sends us that file (phase 1b). Logging into their website and scraping it is against their terms. We will not do that.
- **LinkedIn Recruiter** is the same shape. The official route is **Recruiter System Connect (RSC)**, a partner programme you apply for. It is not a public API. Until RSC (or an equivalent) is approved, the client exports from Recruiter and sends the file.

**What the repo already does with those names is a different product.** Apify actors can pull **job advertisements** from Naukri and LinkedIn onto the student job board (ADR 0048). That was a conscious exception to an earlier “do not scrape” rule, and it carries terms risk. Those actors return vacancies, not a client’s private shortlist. Do not reuse them for this.

Phase 8 starts when a customer contract requires a live Naukri or LinkedIn connection, and only after the agreement is signed. Until then, “send me the export” is the honest product.

### What HR sees after any of these

The match list (section 5.2) gains a source on every line:

“1. Asha — 86% — BrowseJobs — Python, Django — gap: Kubernetes”
“2. Ravi — 81% — your Excel (12 Oct) — Python, SQL”

From `matched` onward, Ravi is in the same outreach → L1 → L2 path as Asha. If Ravi has no WhatsApp consent on file, the bot can rank him and **cannot** call him until he opts in. The report says “not contacted — no permission to call” instead of hiding him.

---

## 4. Live hiring mission control

HR can run the job from WhatsApp. They should also be able to open the website and **see it happening**: CVs being scored, a call in progress, an interview, a slot being picked, a BGV check, a chat before joining. The feel to aim for is a calm operations room — dark, precise, alive — not a cartoon and not a second product with a new colour system.

A scripted preview of that room already exists so the look can be judged before it is wired up: `/employers/mission-control-demo`. Every name on it is a sample. It loops a full story in about 90 seconds. It does not call an API.

### Where it lives in the product we already have

The signed-in employer home is `apps/web/src/app/employer/(workspace)/dashboard/page.tsx`, inside `EmployerShell` (sidebar: Taurus AI, Dashboard, Jobs, Pipeline, Team). That page is a quiet summary today: counts, open roles, a chart. Pipeline stages already exist on `apps/web/src/app/employer/(workspace)/pipeline/page.tsx`.

Mission control **replaces the quiet summary** as the dashboard, for one selected job, with a switcher if the company has several roles. Jobs, pipeline, and team stay where they are. Taurus AI stays the place to dictate a role on the web. The demo route is not that dashboard. Do not link the demo from the public nav, the sitemap, or the employer sidebar.

### Layout

Dark surface, the existing ink / trust-blue tokens, mono for every count and timer, one primary blue. Glass panels, a faint grid, motion only on opacity and transform. `prefers-reduced-motion` stops the motion and still updates the facts.

1. **Phase rail.** Nine stages, in order: Job raised, Sourcing (including the company’s own files), AI calls, L1, L2, Human round, Pre-BGV, Offer, Joining. Each stage shows a live count and the people in it (initials, not photos). The stage the bot is working is the one that glows.
2. **Agent cards.** One card per bot: screening, calls, interview, scheduler, BGV, engagement. Status is idle, working, or waiting for HR. A live call shows the person’s sample-style name, a timer, a short waveform, and the latest transcript line.
3. **Activity feed.** One line at a time, newest first. Examples: “Screening bot: matched 142 CVs, 18 above 80%.” “Call bot: speaking with Sample Rahul (0:42), interested, notice 30 days.”
4. **WhatsApp mirror.** The same thread HR has on their phone, on the side of the desk, so a person at a laptop and a person on WhatsApp are looking at one conversation.
5. **Approval queue.** When autonomous mode is off, the next yes/no sits here and on WhatsApp. When it is on, earlier steps do not wait, and the toggle says so. The offer stays in the queue either way, labelled so a person can see it needs their approval. A person always releases the offer letter. The toggle is per job (section 2).
6. **Candidate drawer.** Click a person: timeline of stages, match score and source, call transcript, L1/L2 dials, BGV outcome, engagement chat, dropout risk if it has fired. Phone numbers are masked (`+91 98••• ••21`). No document images in the drawer.

On a 1280-wide screen the rail is one row and the cards sit beside the feed and the WhatsApp mirror. At 390 the rail scrolls sideways, the cards stack, and the drawer is a full-height sheet. Nothing should require horizontal zoom to read a sentence.

### Real time

There is no websocket stack in the API today. Do not pretend the dashboard polls “feels live” by refreshing the whole page.

Recommended first transport: **server-sent events** from the Laravel API (`text/event-stream`), one stream per company, filtered to the job on screen. It fits the current VPS deploy (one app, Redis already there) and only needs to push. Add **Laravel Reverb** later if we need the browser to push as well, or if the number of open desks makes SSE connections a problem.

Every bot action already wants a queued job. When that job changes a fact, it also emits one event. Suggested payload:

- `company_id`, `job_id`, `candidate_id` (optional)
- `phase` (the nine stages)
- `bot` (screening, call, interview, scheduler, bgv, engagement, whatsapp)
- `kind` (stage_entered, call_started, transcript_line, score_ready, approval_needed, approval_given, alert)
- `text` (one short line, safe to show)
- `at` (ISO time)

The feed, the rail, and the agent card all read this same event. The WhatsApp mirror reads the message log we already store. If the stream drops, the desk refetches the current snapshot and says “reconnected”, rather than freezing on a lie.

### Performance and privacy

- First paint of the desk under 2 seconds on a normal laptop. Events patch one card. They do not reload the page.
- A company with 200 people in sourcing shows counts plus the top few avatars, not 200 faces.
- The stream is authenticated as the member, company-scoped, and tenant-scoped. Another company never receives the events.
- Mask phone and email in the rail, the feed, and the drawer. Reveal the full number only on the drawer, for a recruiter or owner, and write an audit row when they do.
- Call audio is not played on the desk in v1. Transcript text is enough. A waveform can be a level meter, not a recording.
- Sample and demo routes stay labelled and stay out of search engines. The live desk never uses the demo’s fake people.

### What ships when

| When | What the desk shows for real |
|---|---|
| Phase 1 | Job raised, sourcing counts and sources, activity lines, WhatsApp mirror |
| Phase 1b | Client-file imports landing in the sourcing column, tagged with their source |
| Phase 2 | Call card, waveform, transcript snippet, approval queue |
| Phase 3 | L1 and L2 counts and score dials |
| Phase 4 | Scheduler card and the booked slot |
| Phase 5 | BGV ticks and the offer line |
| Phase 6 | Engagement chat and the dropout alert |

Until a phase is real, that card stays idle and says so. It does not invent a call.

---

## 5. WhatsApp conversations

One bot number for BrowseJobs employers. People are recognised by their WhatsApp id, stored against an employer member. Candidates are a **different** conversation on the same number (or a second number — see Open questions). The bot must not mix them: an employer message never receives the candidate script.

Messages that start a new topic use an approved WhatsApp template. Replies inside an open 24-hour window can be normal text. The hub for this is `Messenger` (`apps/api/app/Support/Messaging/Messenger.php`). New templates go in `config/whatsapp_templates.php` and through Meta approval before launch. The banned-phrase check in that hub stays on.

Quiet hours for **calls** are 9:00–19:00 IST, matching the contact hours already on the site. WhatsApp updates may go until 21:00 IST; marketing-style nudges keep the existing 21:00–09:00 block.

### 5.1 Employer: connect

QR encodes a WhatsApp link with a one-time company code, for example a prefilled “JOIN ABC123”.

- Unknown number + valid code → “You’re joining {company} as {role}. Reply YES to confirm.” On YES, attach the number. The code can be single-use (first owner) or multi-use (colleagues), set when the code is printed.
- Unknown number + no code → “Ask your admin for the company QR.” Do not create a company from a cold message in v1.
- Known number → “You’re in {company}. Send a role, or say status.”

The website can also show the QR after the company exists, so the first owner can still be created by ops (the admin onboard screen already does this) and then move to WhatsApp.

### 5.2 Employer: raise a job

Accept text or a voice note.

Example in: “I need tech engineers, Bangalore, salary 12 LPA, two people.”

Bot:

1. Immediately: “Got it. I’m reading that and searching CVs.”
2. Parse title, skills, city, salary, openings, remote. If salary or city is missing, ask one question, not five.
3. Show the reading back: “Role: Software engineer. Location: Bengaluru. Salary: ₹12 LPA. Openings: 2. Reply YES to search, or correct me.”
4. On YES, score the pool and send the top matches (speed target in section 11.1).

Each match, in one bubble or a short list:

- Name
- Match %
- **Source** (BrowseJobs, your Excel, WhatsApp file, email, Drive, ATS, or a board export)
- One line why (skills that hit, skills that missed)
- Years and city if we have them
- A link to the CV the employer is allowed to see

Then: “Shall I start reaching out to the top {n}?”

Voice notes: download the media from Meta, transcribe with the same speech-to-text the hiring console already uses (ElevenLabs, `TranscribeController`), then run the same parser as typed text (`ReadHiringIntent`). If transcription fails, say so and ask for a text.

Do not wait for the JD mock generator before sending CVs. That job can run in the background after the job is saved.

### 5.3 Employer: outreach result

After calls finish, or at a cutoff (for example two hours, or sooner if everyone has answered):

“I spoke to 10. 5 are interested and free in the next two weeks. 3 said no. 2 did not pick up. I’ll try those 2 once more tomorrow unless you say stop.

Interested:
1. Asha — 86% — can join in 30 days — notice 30 days
2. …”

“Shall I set up L1 for these 5?”

### 5.4 Employer: L1 and L2

“L1 closed. 5 were invited, 4 attended, 3 cleared the bar (70). Send L2 to those 3?”

After L2:

“2 cleared L1 and L2. Reports:
1. Asha — L1 78, L2 81 — strong on APIs, thin on system design. Summary: …
2. …”

The bar (default 70) is per job and editable (“set L1 bar to 75”).

### 5.5 Employer: human round

“Set up a human round for these 2?”

On yes, message each interviewer assigned to that round:

“{Company} needs you for {role}. {Candidate}. 45 min. Reply with a slot: 1) Tue 11:00 2) Tue 16:00 3) Wed 11:00. First reply books it.”

When one slot is taken, tell the others it is gone. Then create the meeting and send:

- Candidate: time, link, interviewer name, “this is a video interview with a person”
- Interviewer: time, link, CV link, the L1/L2 summary
- HR: the same, plus who booked it

### 5.6 Employer: pre-BGV and offer

“Run pre-BGV on Asha? This checks employment history (EPFO) and documents (DigiLocker). She will be asked to consent. We do not start without that.”

Later:

“Pre-BGV for Asha: identity matched, 2 employers on PF over 4 years, education document matched. Nothing failed. This is a check, not a character reference. Shall we offer?”

On yes from a person (autonomous mode does not skip this): generate the letter and email it. WhatsApp HR: “Offer emailed to Asha from your template. I will tell you when she accepts.”

If the template is missing: do not invent one. Say “Upload your offer template on the website, then say offer again.”

### 5.7 Candidate: before and during the process

- First contact is a template that says who is calling (BrowseJobs, for {company}), that the next call may be an AI, that it can be recorded, and that they can reply STOP.
- No call until that consent is on file, except where the candidate has already given this exact consent in the product.
- L1/L2 arrive as a link, with a deadline. The interview itself runs on the existing AI interview (section 9), not as an endless WhatsApp quiz.
- STOP, or “don’t call me”, ends the candidate on that job and is audited.

### 5.8 Candidate: from offer to joining

Tone: short, warm, direct. Like a competent HR person. Not a cheerleader.

Examples of what it may ask, on a slow cadence (not daily nagging):

- “Did the offer land? Any line you want HR to explain?”
- “How are you feeling about the move?”
- “Joining is Monday 4 Nov, 10:00, at {office}. Bring a photo ID and the signed letter. Reply if the date is wrong.”

It does not pressure. It does not predict a salary. If the candidate says they are unsure or have another offer, the bot thanks them, records it, and alerts HR. It does not argue them into joining.

After joining day, one check-in the next week (“How was day one?”) and then the bot goes quiet unless HR asks it to continue.

---

## 6. Data we need to add

New tables, all with `tenant_id`, and foreign keys to company, job, and candidate where those apply. Do not edit old migrations; add new ones.

| Table | Holds |
|---|---|
| `employer_channel_links` | WhatsApp id ↔ member, company, verified at, status |
| `employer_join_codes` | The code inside the QR, role it grants, uses left, expiry |
| `employer_bot_sessions` | Current state of a chat (employer or candidate), last question we asked, job in focus |
| `employer_job_intakes` | Raw text or voice-note file id, transcript, parsed fields, who sent it |
| `employer_jobs` additions | `autonomous` (bool), `human_round_enabled` (bool), `pre_bgv_enabled` (bool), `salary_min_paise`, `salary_max_paise`, `source` (`web` or `whatsapp`) |
| `candidate_consents` | Person, purpose (`whatsapp_hiring`, `ai_voice_call`, `call_recording`, `bgv_epfo`, `bgv_digilocker`), version of the text they saw, granted at, withdrawn at |
| `outreach_calls` | Job, candidate, vendor id, status, started at, duration, interest, availability, notice, transcript pointer, cost in paise |
| `employer_interviews` additions | Link to the call that fed the questions; report text sent to HR; channel the invite went on |
| `human_round_slots` | Round, interviewer, option, state (offered, taken, expired), meeting url |
| `bgv_checks` | Already have verification rows. Add the employer job and the “HR asked on WhatsApp” actor. Do not duplicate the gateway. |
| `offer_templates` | Company, file, placeholders, uploaded by |
| `offers` | Application, template, pdf path, state (draft, sent, accepted, declined, expired), sent at, email id |
| `joining_plans` | Offer, date, location, what to bring, last reminder sent |
| `dropout_signals` | Candidate, job, score 0–100, reasons (json), computed at |
| `bot_alerts` | Who was told, about which signal, channel, sent at |
| `company_candidates` | Private person for one company: name, email, phone, city, skills, years, source, consent flags. Not a `users` row unless they later join BrowseJobs |
| `candidate_imports` | One file, mail, or sync run: who sent it, counts, status |
| `import_mappings` | Saved column map for that company, so the next spreadsheet does not ask again |
| `connector_accounts` | Drive, mailbox, or ATS link: provider, encrypted token, folder, last sync, status |

The private candidate is the source of truth for client data. Do not write those rows into `cv_profiles`. A match row points at either a BrowseJobs user, a `company_candidates` id, or both when they are the same person.

Money stays in paise. Match percentages stay integers.

The talent-pool match does not need a new score formula in phase 1. Store the list we sent (`match_pct`, matched skills, missing skills) so the WhatsApp message and the website show the same numbers.

---

## 7. API

Keep the existing `/api/v1/employer/...` website API. Add the following. All of them check tenant and company membership, same as today (`ResolvesMembership`).

**Onboarding**

- `POST /api/v1/employer/workspaces/{id}/join-codes` — owner creates a QR payload
- `GET /api/v1/employer/workspaces/{id}/join-codes/{code}/qr` — PNG of the WhatsApp link
- `POST /api/v1/employer/whatsapp/attach` — used by the webhook flow after YES (not a public signup)

**Jobs from the bot** (also useful for tests and the website)

- `POST /api/v1/employer/workspaces/{id}/intakes` — text or audio, returns the parsed reading
- `POST /api/v1/employer/workspaces/{id}/intakes/{id}/confirm` — writes the `employer_jobs` row and starts matching
- `GET /api/v1/employer/workspaces/{id}/jobs/{job}/matches` — the same list the bot sends, each row tagged with its source

**Client files (phase 1b)**

- `POST /api/v1/employer/workspaces/{id}/imports` — website upload of csv, xlsx, pdf, docx, or zip
- `POST /api/v1/employer/workspaces/{id}/imports/{id}/mapping` — confirm or correct the column map
- `GET /api/v1/employer/workspaces/{id}/candidates` — the private pool, this company only
- Inbound email webhook (signed) for the per-company address

Drive, mailbox OAuth, and ATS connect links are phase 7. They are magic links to the vendor’s consent page, then a callback that stores `connector_accounts`. They are not phase 1b.

**Pipeline controls**

- `POST /api/v1/employer/workspaces/{id}/jobs/{job}/approvals` — body: step + yes/no. This is what a WhatsApp YES calls.
- `PATCH /api/v1/employer/workspaces/{id}/jobs/{job}/mode` — autonomous, human round, pre-BGV, score bars

**Calls, rounds, meetings, offers**

- `POST .../outreach/start` and `POST .../outreach/stop`
- `POST .../rounds/{round}/dispatch` — already have send-round; extend it to notify by WhatsApp
- `POST .../human-rounds/{id}/slots/{slot}/claim` — interviewer claim, idempotent
- `POST .../applications/{id}/bgv` — start pre-BGV after consent
- `POST .../applications/{id}/offer` — render and email
- `GET .../applications/{id}/dropout` — latest score and reasons

**Webhooks (signed, reject unsigned)**

- Existing `POST /api/webhooks/whatsapp` — extend so an employer or candidate session is handled **after** the current lead/ticket logging. Do not break CRM.
- Existing voice webhook — keep for browser mocks. Add a separate path for outbound call results, or a distinct event type, so a student mock ending cannot move a hiring pipeline.
- Vendor webhooks for BGV, same rule: signature first, then queue.

Candidate interview endpoints stay under `/api/v1/me/employer-interviews`. Phase 3 adds the website page that those endpoints already expect.

---

## 8. Background jobs

Every send, call, AI grade, PDF, and vendor check is a queued job. The request that receives a WhatsApp webhook only stores the message and returns 200. Meta retries if we are slow.

| Job | When | Notes |
|---|---|---|
| `IngestEmployerWhatsApp` | Inbound text or voice | Idempotent on the Meta message id |
| `TranscribeEmployerVoiceNote` | Voice note | Then parse |
| `ParseHiringIntake` | Text ready | Reuse `ReadHiringIntent` |
| `RankJobMatches` | Intake confirmed, or a new import landed | Score the BrowseJobs pool and this company’s private pool. High priority queue. |
| `IngestCandidateFile` | Website upload, WhatsApp document, or inbound email | Unzip if needed. Idempotent on file hash. |
| `SuggestColumnMapping` | Spreadsheet ingested | AI suggestion, then wait for YES. |
| `ParseImportedCv` | PDF or docx in a batch | Reuse `cv_parse`. One job per file. |
| `SyncConnector` | Phase 7 schedule | Drive, mailbox, or ATS. Adds only new files or rows. |
| `SendMatchList` | Ranking done | WhatsApp to the requester |
| `PlaceOutreachCall` | Approval or autonomous | One job per candidate, capped |
| `SummariseOutreach` | Calls settled or cutoff | The “I spoke to 10” message |
| `InviteEmployerRound` | L1/L2 approved | Extend `SendInterviewRound` + WhatsApp/email |
| `GradeEmployerInterview` | Already exists | After grade, if the round is complete for the batch, send the clearance note |
| `OfferHumanSlots` | Human round approved | Message interviewers |
| `CreateHumanMeeting` | Slot claimed | Zoom, then three invites |
| `StartPreBgv` | HR yes, and candidate consent on file | `VerificationGateway` |
| `RenderOfferLetter` | HR yes, template present | PDF, then email |
| `RunEngagementTurn` | Inbound candidate message, and scheduled check-ins | AI reply inside an approved script |
| `ScoreDropoutRisk` | After each engagement turn, and nightly | Alert only when the score crosses the line |
| `SendJoiningReminder` | Scheduled up to the joining date | Date, place, what to bring |

Retries must be safe: sending the match list twice is a bug, placing the same call twice is a worse bug. Use the provider id and a unique key per (job, candidate, step).

Suggested queues: `whatsapp-in` (fast), `matching` (fast), `calls` (slow, rate limited), `ai`, `bgv`, `mail`. Horizon is already the worker.

---

## 9. Reuse the AI interview and the proctoring code

**Use this for L1 and L2. Do not build a second interviewer.**

| Already in the repo | Use it for |
|---|---|
| JD mock generation on publish (`PublishEmployerJob`, `JdMock`) | The question bank for that role |
| `EmployerInterview` + `SendInterviewRound` + `GradeEmployerInterview` | The actual L1/L2 record, answers, scores, summary |
| Prompt `resources/prompts/employer_interview_grade.v1.md` | The written grade. Keep “no score if the model fails”. |
| Student mock room `apps/web/src/app/(portal)/student-ai-mock/[id]/room/page.tsx` | The screen the candidate opens from the WhatsApp link, if we want a spoken interview. It already talks (ElevenLabs) and listens. |
| Vapi **web** call (`HttpVapiClient`) | Optional, only if we want a live voice interviewer in the browser. It is not a phone call. |
| Pre-apply mock (`StartEmployerJobMock`) | Stays the practice gate for people who apply from the website. The bot’s L1 is the employer round, not this practice mock. |

**What we still have to build around it**

- A candidate page that loads `me/employer-interviews/{id}` (the API exists; the page does not).
- A WhatsApp (and email) invite when the round is sent. The event `EmployerInterviewInvited` is fired today and nobody listens.
- Feed the screening-call notes into the question set (“they said they led a team of four — ask about that”). That is a prompt change, not a new product.
- A short report rendered from `dimension_scores` and `grading_summary` for WhatsApp. The data is already on the interview row after grading.

**Proctoring**

The room page can notice a tab switch and abandon the attempt. It does not save a proctoring record, and the employer profile hard-codes `proctoring_captured: false`. For this journey:

- Phase 3 may keep that behaviour and **say so** in the report (“no camera record on this round”).
- A later phase can persist tab-switch and face-match on the interview, and only then may the report mention them.
- Do not tell HR there is a proctoring record until those fields are real. The employers FAQ is the standard.

Phone-call L1 (another outbound call, deeper than the screen) is **not** the plan. It costs more, it is harder to grade against a rubric, and it throws away the interview code. The screening call is the phone. L1 and L2 are the structured interview.

---

## 10. Dropout risk

**Do not use `risk_dropout` on `student_scores`.** That number is for a student in a batch: low activity, stalled lessons, blocked fees (`ScoreCalculator`). A candidate who might skip joining is a different question. Mixing them would alert the wrong people for the wrong reasons.

**New score, 0–100, higher means more likely to drop.**

Start with rules a human can audit. Add a model only after we have real outcomes.

| Signal | Suggested weight | Notes |
|---|---|---|
| Says they are unsure, or mentions another offer | High | From the engagement bot, stored as a reason the HR alert can show |
| Stops replying for 3 days after the offer | Medium | One nudge first, then the score moves |
| Misses L1, L2, or the human round | Medium | Already a pipeline fact |
| Delays consent or documents for pre-BGV | Medium | |
| Asks to move the joining date more than once | Low–medium | |
| Replies quickly and confirms the date | Reduces the score | |

Suggested alert: score reaches 70, or jumps by 25 in a day. Alert goes to HR on WhatsApp and email, once per jump, not on a loop. The message includes the rank among people with open offers (“highest risk on the Android role”) and the reasons in words.

HR can mark “we spoke, they’re fine”, which resets the alert but keeps the history.

The engagement bot’s own replies come from a versioned prompt in `resources/prompts/`, through `AiGateway`, with the banned-phrase linter. It may not invent policy, salary, or joining facts. Those come from `joining_plans` and the offer row.

---

## 11. Non-functional requirements

### 11.1 The 4–5 second CV reply

This target is for **text**, on a warm pool, after the employer has confirmed the reading of the role.

| Step | Budget |
|---|---|
| Webhook acknowledges Meta | Under 1 second. No matching inside the webhook. |
| Parse a short text with the small model | About 1–2 seconds |
| Score and take the top 5 | Under 1 second for the current student pool |
| Send the WhatsApp list | About 1 second |

That is tight but honest **if** matching is a query, not “load every student into PHP”, which is what `LmsTalentMatcher` does now. Phase 1 includes making that query selective (skills and role first, then the weighted score on the short list).

**Voice notes are not in the 4–5 second budget.** Download plus transcription usually takes longer than the whole text path. The bot still answers at once (“Got the voice note”), then sends the matches when they are ready. Aim for under 15 seconds, and measure it. Do not promise 4–5 seconds on voice until the numbers say so.

**Importing a file is not in that budget either.** Parsing a spreadsheet or a zip of CVs happens once, when the file arrives, and can take a minute. After that, those people are already in the company’s private pool, and the next search includes them inside the same one-second score as the BrowseJobs pool.

Never block the CV list on mock generation, grading, or a vendor.

If the pool is empty because almost nobody has finished the readiness interview, say that in the chat. Do not backfill with sample candidates. Sample data stays behind the existing `?sample=1` demo flag.

### 11.2 Reliability

- Webhook idempotent on provider message id.
- Call placement idempotent on (job, candidate).
- If the AI parser fails, ask the employer to rephrase. Do not create a job titled with the whole sentence.
- If WhatsApp send fails, retry, then show the failure in the admin message log (already built).
- If the call vendor is down, tell HR “calls are paused”, do not mark candidates as uninterested.
- BGV vendor down → pending, never “failed”. The existing providers already do this. Keep it.
- Grades: if the model fails, no fabricated score. `GradeEmployerInterview` already does this.

### 11.3 Cost

- Per job, cap outreach calls (default 15 completions, configurable by the owner).
- Per candidate, max 2 call attempts.
- Daily AI token budget stays in `AiGateway`. Add a **per company** monthly cap for calls and BGV, because those are cash, not tokens.
- Record cost in paise on the call row and in `ai_events` for model calls.
- Autonomous mode must not be a blank cheque. The cap applies there too. When the cap is hit, the bot stops and tells the owner.

### 11.4 Security

- Verify WhatsApp, voice, Zoom, and BGV signatures before any work. Existing middleware pattern.
- Join codes are single-purpose, expiring, stored hashed if they grant ownership.
- CV links are short-lived signed URLs, tied to the member who asked.
- Offer templates and generated letters are private files.
- Interviewers see only candidates on rounds assigned to them.
- Cross-tenant and cross-company tests are part of done, as on every employer feature today.
- No secrets in code or in this document. Keys live in admin settings or `.env`, names only in `.env.example`.

### 11.5 Consent, DPDP, and calls

India. Treat this as a gate, not a footer.

- A candidate is not searched into an employer’s WhatsApp list unless their profile is in the pool they already joined as a student **and** the purpose covers “employers may see my CV”. If that sentence is not in the consent they already gave, phase 1 only returns people who have it, or we add the sentence and ask them.
- A candidate is not **called** until `candidate_consents` has `ai_voice_call` and `call_recording` for the current wording. The first WhatsApp asks for that. No means no.
- The call’s first sentence says it is an AI calling for {company} via BrowseJobs, and that the call is recorded.
- STOP on WhatsApp withdraws `whatsapp_hiring` and suppresses further templates.
- Pre-BGV does not run on a silent assumption. EPFO and DigiLocker need the candidate’s own consent through the vendor’s flow.
- Access and deletion requests (ADR 0047) must include employer applications, calls, offers, and bot transcripts. Today’s exporter does not.
- Marketing opt-in is a different flag. Do not treat “daily brief” opt-in as permission to be interviewed by a bot.
- Outbound commercial calls also need the company’s and BrowseJobs’ telecom compliance (DLT registration and an approved caller ID). That is a vendor and legal task, not only a code task.
- A client’s imported people are that company’s data. Delete them when the company asks. Do not use them to train a shared model or to fill another company’s search. The owner’s upload confirmation is not the candidate’s consent to be called (section 3).

---

## 12. Third parties

Rough public prices, in INR, for planning. They move. Get a written quote before phase 2 and phase 5. Paise in the ledger; these notes are ranges only. Spreadsheet, Drive, ATS, Naukri, and LinkedIn options, including Merge, Kombo, and Apideck price bands, are in section 3.

### WhatsApp

| Option | Recommendation | Cost shape |
|---|---|---|
| **Meta WhatsApp Cloud API, direct** | **Use this.** The client is already `HttpWhatsAppClient`. Admin settings already store the token. | Meta’s India rate card per message. Utility messages are the cheap ones (often well under ₹1). Marketing messages cost more. |
| BSP: Gupshup, Interakt, Wati, AiSensy | Use only if Meta’s app review or template ops become the blocker. | BSP fee on top of Meta. |

Need: a display number, a WhatsApp Business account, a Meta app, webhook URL on the VPS, and approved templates for invites, consent, slot picks, and offer notices.

### Speech-to-text

| Option | Recommendation | Cost shape |
|---|---|---|
| **ElevenLabs Scribe** | **Use this for phase 1.** `TranscribeController` already calls it. | On the order of ₹0.5–2 per minute of audio. A 20-second note is a few paise to well under a rupee. |
| Whisper-compatible API, Sarvam (strong on Indian languages), Deepgram | Switch if Hindi or Kannada notes are poor, or if we want a cheaper bulk price. | Similar or lower per minute. |

The interview-bank transcriber in the API is a **null** client (`NullTranscriptionClient`). Do not point the employer bot at it.

### Language model

**Use the existing gateway** (`AiGateway`, `config/ai.php`: Anthropic, OpenAI, Kimi, DeepSeek, Grok, or any OpenAI-compatible endpoint).

- Parsing a job and writing a short report: the fast/cheap model.
- Grading an interview: the model already used for `employer_interview_grade`.
- Log every call to `ai_events`.

No new vendor for text AI.

### AI voice calling (phase 2)

| Option | Recommendation | Cost shape |
|---|---|---|
| **Vapi outbound + an Indian number (Exotel or Plivo)** | **First choice.** We already run Vapi for in-browser mocks, including webhooks and cost logging. Outbound is a new mode, not a new relationship. | Often about ₹4–12 per minute all-in (US voice platform + India telephony + the model). A 3-minute screen is tens of rupees, not paise. |
| Bolna | Strong Indian alternative if Vapi’s India numbers or latency are poor. | Similar per-minute band. |
| Retell | Named in `.env.example` and unused. Only consider it if we are already leaving Vapi. | Similar. |
| Exotel / Knowlarity alone | Human call-centre stacks. Wrong tool for an AI caller, right tool for the phone number and DLT. | Number rental plus per-minute. |

15 calls × 3 minutes × ₹8 is on the order of ₹360 for one role’s first screen. Caps in section 11.3 exist so autonomous mode cannot do this all day.

### Video meetings (phase 4)

| Option | Recommendation | Cost shape |
|---|---|---|
| **Zoom, existing Server-to-Server app and license pool** | **Use this.** `CreateZoomMeeting` and the license pool are in production code. | The licenses the company already pays for. A human round should take a pool license the same way a class does, and release it afterwards. |
| Google Meet | Only if a customer refuses Zoom. Not in the repo. | Needs a Workspace account and Calendar API. |

“A simple link” means the Zoom join URL, emailed and WhatsApped. No custom video product.

### EPFO / PF and DigiLocker (phase 5)

The code has `EpfoProvider` and `DigiLockerProvider`, but they call a made-up `/v1/verify` shape and default to **manual review**. Treat them as the slot to plug a vendor into, not as a live integration. The employers page is right to say BGV is not running.

| Check | First choice | Alternative | Cost shape |
|---|---|---|---|
| Employment history (EPFO/PF) | **Perfios** (or Karza, now in that group) if their current employment API matches the consent flow | Surepass, IDfy, AuthBridge, OnGrid | Often ₹30–200 per employment check. Confirm. |
| Identity and education documents | **DigiLocker via Setu or Digio** (real issuer documents, candidate consent) | Surepass, IDfy | Often ₹10–80 per document pull. Confirm. |
| PAN, if we add it later | Same vendors | — | Low per check. Not required for the first pre-BGV summary. |

The candidate must pass through the vendor’s consent. We store the outcome and a short evidence line (counts, span, matched or not), not the passbook and not the PDF of their degree. That matches what `EpfoProvider` already refuses to store.

Ops queue stays for “pending” and for disagreements.

### Email

**Use the current Laravel mail** (`SendEmailMessage`, SMTP) for offers. That path is outbound only. Phase 1b adds inbound mail (Postmark, Mailgun, or SES) so a company can forward CVs to its own address. Details are in section 3. Connecting a whole Gmail or Outlook mailbox waits until phase 7.

### E-sign (optional, not in the first offer phase)

| Option | When | Cost shape |
|---|---|---|
| Leegality or Digio (Aadhaar eSign, common in India) | When a customer requires a signed letter, not a “reply YES, I accept” | Often ₹10–40 per signature. |
| DocuSign / Zoho Sign | If a customer already uses them | Seat or per-envelope pricing. |

Phase 5 acceptance is: candidate replies ACCEPT on WhatsApp or clicks the link in the email. That is an acknowledgment, not an e-sign. Say that in the letter.

---

## 13. Phased delivery

Each phase is demoable on its own. Later phases do not start by rewriting the earlier ones. Tests: Pest for the API (happy path, auth, cross-tenant, cross-company) and one Playwright path for the website bits. WhatsApp itself is tested with the fake client already used in `FakeWhatsAppClient`.

### Phase 1 — MVP: QR, job by voice or text, CVs back

**HR can:** scan QR, join a company, send a text or voice note, confirm the reading, receive the top matches on WhatsApp, open a CV link.

**Build:** join codes and QR, webhook branch for employer sessions, voice-note download, call `ReadHiringIntent` and ElevenLabs, persist the job, ranked matches, WhatsApp list, YES/NO stored. Tighten the matcher so it does not load the entire student table. Empty-pool message. On the dashboard, the mission-control frame for this phase: the phase rail through sourcing, the activity feed, and the WhatsApp mirror, fed by server-sent events (section 4).

**Reuse:** workspaces, members, `ReadHiringIntent`, `TranscribeController`’s vendor, `LmsTalentMatcher`, `Messenger`.

**Not in this phase:** calls, L1, offers.

**Size:** Medium. The risky part is the conversation state and the speed of matching, not the job form.

### Phase 1b — The company’s own files

**HR can:** upload Excel or CSV on the website, send that file or a zip of CVs in WhatsApp, or forward mail to their company address. They confirm the column mapping. The next search ranks those people beside BrowseJobs CVs, with the source on each line.

**Build:** private `company_candidates` pool, file ingest, AI column map plus YES, batch CV parse reusing `cv_parse`, de-dupe on email and phone, WhatsApp document handling, inbound email address, re-rank when a file lands on an open job. Attestation checkbox before the first import.

**Not in this phase:** Gmail/Outlook OAuth, Drive, SharePoint, live ATS, Naukri or LinkedIn APIs. A Naukri or LinkedIn **export file** is just another spreadsheet, so it works here without a partnership.

**Size:** Medium. The parsers exist. The privacy boundary and the two new doors (WhatsApp file, inbound mail) are the work.

### Phase 2 — Outreach calls

**HR can:** say yes to outreach, get “I spoke to 10, 5 are interested” with the answers.

**Build:** consent ask, `PlaceOutreachCall`, vendor webhook, transcript, interest and availability fields, summary message, attempt cap, quiet hours, STOP.

**Depends on:** a funded voice vendor, an Indian caller ID, DLT, and the consent text signed off.

**Size:** Large. More vendor and compliance than code.

### Phase 3 — L1 and L2

**HR can:** approve L1, then L2, and read who cleared plus a short report per person.

**Build:** candidate interview page on the existing API, WhatsApp invite, listener on `EmployerInterviewInvited`, batch report when the window closes, pass/fail bar, screening notes added to the prompt.

**Reuse:** `EmployerInterview`, grading job, mock room for the spoken sitting.

**Size:** Medium.

### Phase 4 — Human round

**HR can:** ask for a human round. Interviewers get slots on WhatsApp. One acceptance creates a Zoom link to all three.

**Build:** interviewer role (or a scoped assignment if we refuse a new role — see Open questions), slot offer, claim, `CreateHumanMeeting` using the Zoom pool, three notifications.

**Size:** Medium.

### Phase 5 — Pre-BGV and offer

**HR can:** request pre-BGV, read the summary, say yes, and have the template emailed.

**Build:** real vendor behind `VerificationGateway`, candidate consent hand-off, summary message, template upload on the website, PDF, email, accept/decline.

**Depends on:** vendor contracts and a lawyer’s look at the consent copy.

**Size:** Large.

**Do not build autonomous offers until Open question 2 is answered.** The approval-gated offer can ship either way.

### Phase 6 — Engagement and dropout alerts

**The candidate gets** the HR-style bot and joining reminders. **HR gets** an alert when the dropout score crosses the line.

**Build:** engagement prompt, cadence, `joining_plans`, `ScoreDropoutRisk`, alert, “we spoke” reset. Extend the DPDP export.

**Size:** Medium-large. The bot is easy to make annoying; the work is the cadence and the score, not the chat UI.

### Phase 7 — Folders and ATS

**HR can:** connect one Google Drive or OneDrive/SharePoint folder, or connect an ATS, or drop a nightly CSV on SFTP. New files and new ATS rows join the private pool without another upload.

**Build:** per-company OAuth (magic link from WhatsApp), `SyncConnector`, encrypted tokens, “stop syncing”. ATS through Merge, Kombo, or Apideck once we know which systems the first customers use. SFTP/CSV drop as the fallback. Read-only database access only if a client refuses both.

**Depends on:** Google and Microsoft OAuth apps, and a quote from one unified-API vendor. Do not start this before phase 1b has been used for real.

**Size:** Large.

### Phase 8 — Naukri and LinkedIn, live

**HR can:** keep a Naukri or LinkedIn source updated without exporting by hand.

**Build only after** a Naukri partner agreement or LinkedIn Recruiter System Connect approval. Until that paper exists, phase 1b’s “send me the export” is the product. Do not point the existing Apify job-ad scrapers at a resume database.

**Size:** The contract is the long part. The import itself is the phase 1b path plus a scheduled pull.

### Mission control, across the phases

The look is previewed at `/employers/mission-control-demo` (sample data, no API, noindex). The real screen is the employer dashboard, filled in as each phase above starts emitting events. See section 4. Do not wait until phase 6 to show an empty room: phase 1 already shows the job, the sourcing column, and the HR thread.

### What we deliberately leave on the website

Pipeline board, team page, automation rules already shipped, admin BGV queue, message log. The bot writes the same tables the board reads.

---

## 14. Open questions

These need a decision from the founder (and, where marked, a vendor or a lawyer). Engineering should not guess.

1. **BrowseJobs pool, still.** Client files are now in scope (section 3). The open part is the shared pool: only students who finished the readiness interview, or a wider BrowseJobs set? Client rows never enter that shared pool.
2. **Can autonomous mode send the offer?** **Decided, 7 October 2026.** No. A person must always release the offer letter, even in autonomous mode. Autonomous mode may walk the earlier steps. It stops at the offer and waits for a human yes. This matches the employer FAQ and `docs/employer-module-requirements.md`.
3. **WhatsApp number.** One BrowseJobs employer bot, or a number per company? One number is simpler and matches “the BrowseJobs bot”. Per company is a different Meta setup and a different cost.
4. **Same number for candidates and employers, or two?** Two numbers stop a tired HR message from being read as a candidate reply. Slightly more ops.
5. **Consent copy.** Please approve the exact sentences for: showing my CV to an employer, AI call, recording, WhatsApp hiring updates, EPFO, DigiLocker. Legal should own the wording. We will store the version.
6. **Languages.** Phase 1 in English only, or Hindi voice notes from day one? That chooses the speech vendor.
7. **Salary in the voice note.** We will store it as the company’s budget. Confirm we never repeat that figure to the candidate as “your salary” until the offer letter.
8. **L1 bar.** Default 70 out of 100 unless the company says otherwise. Confirm.
9. **Interviewer.** New role, or “any member we assign to the round”? A new role is cleaner for “they only see their candidates”.
10. **Meeting length and who proposes slots.** Recommend: the bot offers the next three free slots from a weekly window the interviewer set once on WhatsApp (“I’m free Tue–Thu, 11–17”). Confirm.
11. **Zoom or Meet.** Recommend Zoom, because it is built. Say if a customer contract forces Meet.
12. **BGV vendor.** Perfios (employment) and Setu or Digio (DigiLocker) are the recommendation. Confirm who signs the contract and who pays per check — BrowseJobs, or the employer.
13. **Who pays for the AI calls.** Employer cap, or BrowseJobs during the free months already promised on the site (first six months)? The site promise and the call cost need to match.
14. **Empty pool.** If no CV clears the bar, the bot says so. Confirm we do not fall back to unrelated students. The matcher was tightened in August 2026 for that reason.
15. **After they join.** One check-in the following week, then silence. Confirm if you want a longer relationship.
16. **Proctoring claims.** Confirm phase 3 ships with “no camera record” in the report, until we actually store the signals.
17. **Right to upload.** Confirm the sentence the owner must accept before the first import: they have a lawful reason to share these people, and nobody is called until that person opts in.
18. **Same person, two pools.** Recommend one card, both sources, one call. Say if you would rather keep the client’s copy completely separate even when the email matches a student.
19. **Naukri and LinkedIn.** Recommend phase 1b accepts their Excel export, and we do not apply to Resdex or LinkedIn RSC until a named customer needs a live link. Confirm.
20. **Which ATS the first clients actually use.** Keka, Darwinbox, Zoho Recruit, Greenhouse, or “we live in Excel”? This picks Merge vs Kombo vs Apideck vs “just SFTP”. Do not buy a unified API before that answer.
21. **Scanned CVs.** Phase 1b will reject photo-only PDFs and ask for a text PDF or a spreadsheet. Confirm we are not promising OCR on day one.
22. **How long we keep a private pool** after the company stops paying or asks us to delete it. Recommend: delete on request within the DPDP window, and say so in the attestation.

### Accounts and credentials to have ready

Nothing here is a secret value. These are the accounts to open. Names already in `apps/api/.env.example` are marked.

| When | Account | Already named in env / admin |
|---|---|---|
| Phase 1 | Meta WhatsApp Business: phone number id, business account id, token, app secret, verify token, webhook on the API domain | `WHATSAPP_*` and Admin → Settings |
| Phase 1 | ElevenLabs key (voice notes) | `ELEVENLABS_API_KEY` |
| Phase 1 | An AI provider key if production does not have one | `AI_PROVIDER` and the matching key |
| Phase 1 | A join-code signing secret (new) | Add when we build it |
| Phase 1b | Inbound email on a per-company address (Postmark, Mailgun, or SES) | New. `MAIL_*` today is outbound only |
| Phase 7 | Google OAuth app with Drive file scope, and a Microsoft app with Graph file scope | `GOOGLE_CLIENT_ID` is login only. Drive today is a service account for review images |
| Phase 7 | Merge, Kombo, or Apideck account — after question 20 | None |
| Phase 8 | Naukri partner paperwork, or LinkedIn Recruiter System Connect | None. Apify in admin settings pulls job ads, not resumes |
| Phase 2 | Vapi (or Bolna) outbound, webhook secret | `VAPI_*` exists for web calls; outbound may need extra vendor settings |
| Phase 2 | Indian number + DLT (Exotel or Plivo) | New |
| Phase 4 | Zoom server-to-server, and at least one license in the pool | `ZOOM_*`, admin license screen |
| Phase 5 | Perfios (or the chosen employment vendor) API | `EPFO_*` is only a placeholder URL and key |
| Phase 5 | DigiLocker partner (Setu or Digio) | `DIGILOCKER_*` is only a placeholder |
| Phase 5 | SMTP that can send attachments from the offer address | `MAIL_*` |
| Later | E-sign vendor | None |

Also: the production webhook URLs must be reachable from Meta, Vapi, Zoom, and the BGV vendor. The deploy path is already GitHub Actions to the VPS; the new webhooks are routes on that API, not a new server.

---

## 15. Done, for each phase

The repo’s definition of done still applies: migration, action, API, screen where a screen is needed, queued jobs, Pest tests including cross-tenant denial, seed data, no secrets in git.

For this journey, add:

- A cross-company denial test (two companies, one tenant).
- A test that a candidate who said STOP is not called.
- A test that autonomous mode cannot send the offer. A person always releases the letter.
- A test that company A’s imported candidates are invisible to company B, and are not written into the shared student CV table.
- A test that an unsigned webhook is rejected.
- Public copy (`employers.ts`, `answers.ts`) updated in the same phase that makes a claim true, and not before.
