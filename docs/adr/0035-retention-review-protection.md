# ADR 0035 — P4.6a Review protection & retention

- **Status:** Accepted
- **Date:** 2026-07-18
- **Context:** PRD §6.20, first half of P4.6. The boosters half (LinkedIn
  optimizer, GitHub portfolio, prep packs, tracker tailoring, Career+
  gating) follows as P4.6b.

## Decisions

**NPS pulses ride journey milestones, not calendars.** The daily
`care:dispatch` sweep opens one pulse per student per milestone —
week-1 (enrolled ≥7 days), mid (≥50% topic completion), pre-placement
(≥80%) — unique-keyed so re-runs are no-ops. Routing happens at submit:
**promoters (≥9) get an UNCONDITIONED Google-review invitation** (link
shown only after a top score, never tied to any reward — policy-safe);
**detractors (≤6) fire instant counselor rescue** notifications; passives
are simply thanked. The care desk shows live NPS.

**Rage signals are deterministic and humble.** `care:signals` (daily)
opens an intervention alert on churn precursors it can PROVE: ≥2 failed
payments in 30 days, ≥2 low-CSAT tickets in 90 days, or an engagement
cliff (active in the prior window, silent 7 days). One alert per student
per 14-day cooldown; counselors are paged with the signal list and close
alerts with a resolution note. Sentiment analysis over inbound messages is
a deliberate later addition — a wrong "angry" label is worse than none.

**Pause/defer freezes the seat, not the relationship.** Students request a
pause with a reason (one open request at a time); counselors approve with
an end date capped by `retention.pause.max_days` (90) or reject — both
audited. Approval flips the batch member to the new
`BatchMemberStatus::Paused` (NOT in `occupying()`, so the seat frees for
capacity) and tells the student their seat is safe. Rejoining a later
batch uses the existing roster tooling.

**Week-1 is white-glove by checklist.** Fresh enrolments (≤14 days) get an
`onboarding_checklists` row exactly once; counselors are nudged for the
welcome call and tick off call → tour → week-1 check-in on the care desk.
The checklist stays visible until the check-in is done.

**One care desk, counselor-gated.** Alerts, detractor responses, pause
decisions and onboarding all live at /admin/care behind `can:manage-leads`
— retention is counselor work, and the student-side surface is one calm
/checkin page (pulse + pause) rather than popups.

## Consequences

- 12 Pest tests in `tests/Feature/Care/`: dispatch idempotence + milestone
  escalation, promoter/passive/detractor routing (including the
  answer-once rule), pause request/approve/reject with the max-window
  guard and seat freeze, rage signals with cooldown, alert handling,
  care-desk gating, onboarding step tracking, and the student check-in
  payload (suite: 640).
- Founder input: `GOOGLE_REVIEW_URL` env sets the review target; empty
  disables promoter routing entirely.
- Scheduled: `care:signals` 06:15, `care:dispatch` 09:00 daily.
