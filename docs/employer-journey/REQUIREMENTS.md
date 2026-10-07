# Employer WhatsApp hiring journey — requirements

**Status:** Draft for Dr Krish Bharggav
**Date:** 7 October 2026
**Evidence:** `GAP-ANALYSIS.md` in this folder (what the repo does today)
**Does not replace** `browsejobs-lms-requirements.md` (the LMS) or `docs/employer-module-requirements.md` (the website hiring desk). Where this document disagrees with the employer desk — mainly “a person always releases the offer” — the disagreement is listed under Open questions. Do not build the conflicting part until that decision is made.

---

## One-page summary

BrowseJobs already has a website where a company logs in, posts a job, and works a pipeline. This project adds a second front door: **WhatsApp**. The website stays. It is the record. WhatsApp is how HR runs the job without living in that screen.

**What the employer feels**

1. Scan a QR. They are on the company bot. Colleagues scan the same company and land in the same account.
2. Send a voice note or a text: “I need tech engineers, Bangalore, salary X.” The bot writes the job, searches the CV pool, and sends the best matches back on WhatsApp.
3. It asks “Shall I start reaching out?” On yes, it phones matched candidates, checks interest, and reports who said yes.
4. On yes, those people sit an L1 AI interview built from their CV and what they said on the call. HR gets “who attended, who cleared.”
5. Same for L2. HR then gets a short written report on each person who cleared both.
6. A switch on the job can skip the yes/no questions and run the steps on its own. Default is **ask first**.
7. Optional: “Set up a human round?” The bot asks the interviewers on WhatsApp for a slot, then sends one meeting link to the candidate, the interviewer, and HR.
8. Optional: “Run pre-BGV?” The bot calls an employment-history check (EPFO/PF) and a document check (DigiLocker) and shows HR a short summary.
9. On yes, it fills the company’s offer template and emails it to the candidate.
10. From offer until after joining, a WhatsApp bot talks to the candidate the way a careful HR person would. If the replies suggest they may not join, HR is alerted, with a rank. Joining date, place, and what to bring go out before the day.

**What we will not claim**

Nobody is guaranteed a job. The bot must not say “guaranteed”, “100% placement”, or a fixed salary outcome. Salary in the job is what the company offered to pay, not a promise to the candidate about their future. The line already used on the site still holds: hiring depends on the market and the person’s performance.

**What “done” is not**

The public employers page already refuses to claim an outbound dialler, live BGV, or proctoring. Those sentences stay true until the phase that builds them has shipped and been tried on a real number.

**How we ship it**

Six phases. The first one is the only one that must exist before anything else is worth demoing: QR, voice or text job, CVs back on WhatsApp.

| Phase | What HR can do at the end of it | Size |
|---|---|---|
| 1 — MVP | Connect by QR. Send a role. Get ranked CVs on WhatsApp. | Medium. Mostly new conversation code on top of the job and talent-pool code. |
| 2 — Calls | Approve outreach. Bot phones candidates and reports interest. | Large. New phone vendor, new consent, new cost control. |
| 3 — L1 and L2 | Approve interviews. Candidates sit them. HR gets the clearance note and the write-up. | Medium. The interview and grading code exist; delivery and the candidate screen do not. |
| 4 — Human round | Interviewers pick a slot on WhatsApp. A Zoom link goes to all three parties. | Medium. Zoom exists. Slot booking for employers does not. |
| 5 — Pre-BGV and offer | HR sees a pre-BGV summary and can send an offer email from their template. | Large. Vendor contracts and a letter generator. |
| 6 — Engagement | The candidate has an HR-style bot through joining, and HR hears dropout risk. | Medium-large. New bot and a new score. Do not reuse the student dropout formula. |

Size means how much new product this is, next to the employer desk already in the repo. It is not a calendar promise.

**Decisions needed before phase 1 starts**

1. Whose CVs are we allowed to send — only BrowseJobs students who finished the readiness interview (that is the pool today), or a wider set?
2. May the bot send an offer with no human click, or does autonomous mode stop before the offer?
3. Which WhatsApp number, and who owns the Meta Business account?
4. Written consent for calls and for this bot, separate from student marketing opt-in.

The full list is at the end.

---

## 1. Who uses it

| Person | How they enter | What they may do |
|---|---|---|
| **Owner** | First QR, or the existing website signup | Connect the company, invite others, upload the offer template, turn autonomous mode on, approve offers if the company requires a person |
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

- **Autonomous** — off by default. When off, the bot stops at every question in the list below. When on, it walks the same states and only stops for a hard failure (no consent, vendor down, nobody free for a human round, offer template missing).
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

### Candidate states (one person on one job)

```
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

**Autonomous mode** still sends the same updates (“I spoke to 10, 5 are interested, L1 is booked”) so HR is never surprised. It does not wait.

---

## 3. WhatsApp conversations

One bot number for BrowseJobs employers. People are recognised by their WhatsApp id, stored against an employer member. Candidates are a **different** conversation on the same number (or a second number — see Open questions). The bot must not mix them: an employer message never receives the candidate script.

Messages that start a new topic use an approved WhatsApp template. Replies inside an open 24-hour window can be normal text. The hub for this is `Messenger` (`apps/api/app/Support/Messaging/Messenger.php`). New templates go in `config/whatsapp_templates.php` and through Meta approval before launch. The banned-phrase check in that hub stays on.

Quiet hours for **calls** are 9:00–19:00 IST, matching the contact hours already on the site. WhatsApp updates may go until 21:00 IST; marketing-style nudges keep the existing 21:00–09:00 block.

### 3.1 Employer: connect

QR encodes a WhatsApp link with a one-time company code, for example a prefilled “JOIN ABC123”.

- Unknown number + valid code → “You’re joining {company} as {role}. Reply YES to confirm.” On YES, attach the number. The code can be single-use (first owner) or multi-use (colleagues), set when the code is printed.
- Unknown number + no code → “Ask your admin for the company QR.” Do not create a company from a cold message in v1.
- Known number → “You’re in {company}. Send a role, or say status.”

The website can also show the QR after the company exists, so the first owner can still be created by ops (the admin onboard screen already does this) and then move to WhatsApp.

### 3.2 Employer: raise a job

Accept text or a voice note.

Example in: “I need tech engineers, Bangalore, salary 12 LPA, two people.”

Bot:

1. Immediately: “Got it. I’m reading that and searching CVs.”
2. Parse title, skills, city, salary, openings, remote. If salary or city is missing, ask one question, not five.
3. Show the reading back: “Role: Software engineer. Location: Bengaluru. Salary: ₹12 LPA. Openings: 2. Reply YES to search, or correct me.”
4. On YES, score the pool and send the top matches (target in section 8).

Each match, in one bubble or a short list:

- Name
- Match %
- One line why (skills that hit, skills that missed)
- Years and city if we have them
- A link to the CV the employer is allowed to see

Then: “Shall I start reaching out to the top {n}?”

Voice notes: download the media from Meta, transcribe with the same speech-to-text the hiring console already uses (ElevenLabs, `TranscribeController`), then run the same parser as typed text (`ReadHiringIntent`). If transcription fails, say so and ask for a text.

Do not wait for the JD mock generator before sending CVs. That job can run in the background after the job is saved.

### 3.3 Employer: outreach result

After calls finish, or at a cutoff (for example two hours, or sooner if everyone has answered):

“I spoke to 10. 5 are interested and free in the next two weeks. 3 said no. 2 did not pick up. I’ll try those 2 once more tomorrow unless you say stop.

Interested:
1. Asha — 86% — can join in 30 days — notice 30 days
2. …”

“Shall I set up L1 for these 5?”

### 3.4 Employer: L1 and L2

“L1 closed. 5 were invited, 4 attended, 3 cleared the bar (70). Send L2 to those 3?”

After L2:

“2 cleared L1 and L2. Reports:
1. Asha — L1 78, L2 81 — strong on APIs, thin on system design. Summary: …
2. …”

The bar (default 70) is per job and editable (“set L1 bar to 75”).

### 3.5 Employer: human round

“Set up a human round for these 2?”

On yes, message each interviewer assigned to that round:

“{Company} needs you for {role}. {Candidate}. 45 min. Reply with a slot: 1) Tue 11:00 2) Tue 16:00 3) Wed 11:00. First reply books it.”

When one slot is taken, tell the others it is gone. Then create the meeting and send:

- Candidate: time, link, interviewer name, “this is a video interview with a person”
- Interviewer: time, link, CV link, the L1/L2 summary
- HR: the same, plus who booked it

### 3.6 Employer: pre-BGV and offer

“Run pre-BGV on Asha? This checks employment history (EPFO) and documents (DigiLocker). She will be asked to consent. We do not start without that.”

Later:

“Pre-BGV for Asha: identity matched, 2 employers on PF over 4 years, education document matched. Nothing failed. This is a check, not a character reference. Shall we offer?”

On yes (and if autonomous is off, this yes is a person): generate the letter and email it. WhatsApp HR: “Offer emailed to Asha from your template. I will tell you when she accepts.”

If the template is missing: do not invent one. Say “Upload your offer template on the website, then say offer again.”

### 3.7 Candidate: before and during the process

- First contact is a template that says who is calling (BrowseJobs, for {company}), that the next call may be an AI, that it can be recorded, and that they can reply STOP.
- No call until that consent is on file, except where the candidate has already given this exact consent in the product.
- L1/L2 arrive as a link, with a deadline. The interview itself runs on the existing AI interview (section 6), not as an endless WhatsApp quiz.
- STOP, or “don’t call me”, ends the candidate on that job and is audited.

### 3.8 Candidate: from offer to joining

Tone: short, warm, direct. Like a competent HR person. Not a cheerleader.

Examples of what it may ask, on a slow cadence (not daily nagging):

- “Did the offer land? Any line you want HR to explain?”
- “How are you feeling about the move?”
- “Joining is Monday 4 Nov, 10:00, at {office}. Bring a photo ID and the signed letter. Reply if the date is wrong.”

It does not pressure. It does not predict a salary. If the candidate says they are unsure or have another offer, the bot thanks them, records it, and alerts HR. It does not argue them into joining.

After joining day, one check-in the next week (“How was day one?”) and then the bot goes quiet unless HR asks it to continue.

---

## 4. Data we need to add

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

Money stays in paise. Match percentages stay integers.

The talent-pool match does not need a new score formula in phase 1. Store the list we sent (`match_pct`, matched skills, missing skills) so the WhatsApp message and the website show the same numbers.

---

## 5. API

Keep the existing `/api/v1/employer/...` website API. Add the following. All of them check tenant and company membership, same as today (`ResolvesMembership`).

**Onboarding**

- `POST /api/v1/employer/workspaces/{id}/join-codes` — owner creates a QR payload
- `GET /api/v1/employer/workspaces/{id}/join-codes/{code}/qr` — PNG of the WhatsApp link
- `POST /api/v1/employer/whatsapp/attach` — used by the webhook flow after YES (not a public signup)

**Jobs from the bot** (also useful for tests and the website)

- `POST /api/v1/employer/workspaces/{id}/intakes` — text or audio, returns the parsed reading
- `POST /api/v1/employer/workspaces/{id}/intakes/{id}/confirm` — writes the `employer_jobs` row and starts matching
- `GET /api/v1/employer/workspaces/{id}/jobs/{job}/matches` — the same list the bot sends

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

## 6. Background jobs

Every send, call, AI grade, PDF, and vendor check is a queued job. The request that receives a WhatsApp webhook only stores the message and returns 200. Meta retries if we are slow.

| Job | When | Notes |
|---|---|---|
| `IngestEmployerWhatsApp` | Inbound text or voice | Idempotent on the Meta message id |
| `TranscribeEmployerVoiceNote` | Voice note | Then parse |
| `ParseHiringIntake` | Text ready | Reuse `ReadHiringIntent` |
| `RankJobMatches` | Intake confirmed | Reuse `LmsTalentMatcher`. High priority queue. |
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

## 7. Reuse the AI interview and the proctoring code

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

## 8. Dropout risk

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

## 9. Non-functional requirements

### 9.1 The 4–5 second CV reply

This target is for **text**, on a warm pool, after the employer has confirmed the reading of the role.

| Step | Budget |
|---|---|
| Webhook acknowledges Meta | Under 1 second. No matching inside the webhook. |
| Parse a short text with the small model | About 1–2 seconds |
| Score and take the top 5 | Under 1 second for the current student pool |
| Send the WhatsApp list | About 1 second |

That is tight but honest **if** matching is a query, not “load every student into PHP”, which is what `LmsTalentMatcher` does now. Phase 1 includes making that query selective (skills and role first, then the weighted score on the short list).

**Voice notes are not in the 4–5 second budget.** Download plus transcription usually takes longer than the whole text path. The bot still answers at once (“Got the voice note”), then sends the matches when they are ready. Aim for under 15 seconds, and measure it. Do not promise 4–5 seconds on voice until the numbers say so.

Never block the CV list on mock generation, grading, or a vendor.

If the pool is empty because almost nobody has finished the readiness interview, say that in the chat. Do not backfill with sample candidates. Sample data stays behind the existing `?sample=1` demo flag.

### 9.2 Reliability

- Webhook idempotent on provider message id.
- Call placement idempotent on (job, candidate).
- If the AI parser fails, ask the employer to rephrase. Do not create a job titled with the whole sentence.
- If WhatsApp send fails, retry, then show the failure in the admin message log (already built).
- If the call vendor is down, tell HR “calls are paused”, do not mark candidates as uninterested.
- BGV vendor down → pending, never “failed”. The existing providers already do this. Keep it.
- Grades: if the model fails, no fabricated score. `GradeEmployerInterview` already does this.

### 9.3 Cost

- Per job, cap outreach calls (default 15 completions, configurable by the owner).
- Per candidate, max 2 call attempts.
- Daily AI token budget stays in `AiGateway`. Add a **per company** monthly cap for calls and BGV, because those are cash, not tokens.
- Record cost in paise on the call row and in `ai_events` for model calls.
- Autonomous mode must not be a blank cheque. The cap applies there too. When the cap is hit, the bot stops and tells the owner.

### 9.4 Security

- Verify WhatsApp, voice, Zoom, and BGV signatures before any work. Existing middleware pattern.
- Join codes are single-purpose, expiring, stored hashed if they grant ownership.
- CV links are short-lived signed URLs, tied to the member who asked.
- Offer templates and generated letters are private files.
- Interviewers see only candidates on rounds assigned to them.
- Cross-tenant and cross-company tests are part of done, as on every employer feature today.
- No secrets in code or in this document. Keys live in admin settings or `.env`, names only in `.env.example`.

### 9.5 Consent, DPDP, and calls

India. Treat this as a gate, not a footer.

- A candidate is not searched into an employer’s WhatsApp list unless their profile is in the pool they already joined as a student **and** the purpose covers “employers may see my CV”. If that sentence is not in the consent they already gave, phase 1 only returns people who have it, or we add the sentence and ask them.
- A candidate is not **called** until `candidate_consents` has `ai_voice_call` and `call_recording` for the current wording. The first WhatsApp asks for that. No means no.
- The call’s first sentence says it is an AI calling for {company} via BrowseJobs, and that the call is recorded.
- STOP on WhatsApp withdraws `whatsapp_hiring` and suppresses further templates.
- Pre-BGV does not run on a silent assumption. EPFO and DigiLocker need the candidate’s own consent through the vendor’s flow.
- Access and deletion requests (ADR 0047) must include employer applications, calls, offers, and bot transcripts. Today’s exporter does not.
- Marketing opt-in is a different flag. Do not treat “daily brief” opt-in as permission to be interviewed by a bot.
- Outbound commercial calls also need the company’s and BrowseJobs’ telecom compliance (DLT registration and an approved caller ID). That is a vendor and legal task, not only a code task.

---

## 10. Third parties

Rough public prices, in INR, for planning. They move. Get a written quote before phase 2 and phase 5. Paise in the ledger; these notes are ranges only.

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

15 calls × 3 minutes × ₹8 is on the order of ₹360 for one role’s first screen. Caps in section 9.3 exist so autonomous mode cannot do this all day.

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

**Use the current Laravel mail** (`SendEmailMessage`, SMTP). The offer is a normal message with a PDF attached, from the company template. No new email vendor in v1.

### E-sign (optional, not in the first offer phase)

| Option | When | Cost shape |
|---|---|---|
| Leegality or Digio (Aadhaar eSign, common in India) | When a customer requires a signed letter, not a “reply YES, I accept” | Often ₹10–40 per signature. |
| DocuSign / Zoho Sign | If a customer already uses them | Seat or per-envelope pricing. |

Phase 5 acceptance is: candidate replies ACCEPT on WhatsApp or clicks the link in the email. That is an acknowledgment, not an e-sign. Say that in the letter.

---

## 11. Phased delivery

Each phase is demoable on its own. Later phases do not start by rewriting the earlier ones. Tests: Pest for the API (happy path, auth, cross-tenant, cross-company) and one Playwright path for the website bits. WhatsApp itself is tested with the fake client already used in `FakeWhatsAppClient`.

### Phase 1 — MVP: QR, job by voice or text, CVs back

**HR can:** scan QR, join a company, send a text or voice note, confirm the reading, receive the top matches on WhatsApp, open a CV link.

**Build:** join codes and QR, webhook branch for employer sessions, voice-note download, call `ReadHiringIntent` and ElevenLabs, persist the job, ranked matches, WhatsApp list, YES/NO stored. Tighten the matcher so it does not load the entire student table. Empty-pool message.

**Reuse:** workspaces, members, `ReadHiringIntent`, `TranscribeController`’s vendor, `LmsTalentMatcher`, `Messenger`.

**Not in this phase:** calls, L1, offers.

**Size:** Medium. The risky part is the conversation state and the speed of matching, not the job form.

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

### What we deliberately leave on the website

Pipeline board, team page, automation rules already shipped, admin BGV queue, message log. The bot writes the same tables the board reads.

---

## 12. Open questions

These need a decision from the founder (and, where marked, a vendor or a lawyer). Engineering should not guess.

1. **Whose CVs?** Today the pool is BrowseJobs students who finished the AI Readiness Interview, with a role overlap. Is that the database you mean, or every CV ever uploaded, or an outside source? A wider pool is a product change, not a switch.
2. **Can autonomous mode send the offer?** The live employer FAQ and `docs/employer-module-requirements.md` say a person always releases the offer. Your step 6 says the whole pipeline can run without yes/no. Recommend: autonomous through L2 and the human round; offer stays a human yes unless you explicitly want otherwise.
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

### Accounts and credentials to have ready

Nothing here is a secret value. These are the accounts to open. Names already in `apps/api/.env.example` are marked.

| When | Account | Already named in env / admin |
|---|---|---|
| Phase 1 | Meta WhatsApp Business: phone number id, business account id, token, app secret, verify token, webhook on the API domain | `WHATSAPP_*` and Admin → Settings |
| Phase 1 | ElevenLabs key (voice notes) | `ELEVENLABS_API_KEY` |
| Phase 1 | An AI provider key if production does not have one | `AI_PROVIDER` and the matching key |
| Phase 1 | A join-code signing secret (new) | Add when we build it |
| Phase 2 | Vapi (or Bolna) outbound, webhook secret | `VAPI_*` exists for web calls; outbound may need extra vendor settings |
| Phase 2 | Indian number + DLT (Exotel or Plivo) | New |
| Phase 4 | Zoom server-to-server, and at least one license in the pool | `ZOOM_*`, admin license screen |
| Phase 5 | Perfios (or the chosen employment vendor) API | `EPFO_*` is only a placeholder URL and key |
| Phase 5 | DigiLocker partner (Setu or Digio) | `DIGILOCKER_*` is only a placeholder |
| Phase 5 | SMTP that can send attachments from the offer address | `MAIL_*` |
| Later | E-sign vendor | None |

Also: the production webhook URLs must be reachable from Meta, Vapi, Zoom, and the BGV vendor. The deploy path is already GitHub Actions to the VPS; the new webhooks are routes on that API, not a new server.

---

## 13. Done, for each phase

The repo’s definition of done still applies: migration, action, API, screen where a screen is needed, queued jobs, Pest tests including cross-tenant denial, seed data, no secrets in git.

For this journey, add:

- A cross-company denial test (two companies, one tenant).
- A test that a candidate who said STOP is not called.
- A test that autonomous mode cannot pass the offer step until question 2 is decided and the code is changed on purpose.
- A test that an unsigned webhook is rejected.
- Public copy (`employers.ts`, `answers.ts`) updated in the same phase that makes a claim true, and not before.
