# ADR 0038 — Navigation grouping (admin & student portals)

- **Status:** Accepted
- **Date:** 2026-07-18
- **PRD:** §6.23 (navigation: max two taps, never dead ends)
- **Relates to:** every admin/student feature that added a nav item

## Context

Both navigations had grown to flat lists that were no longer scannable — the admin sidebar
reached ~32 items and the student portal ~18. The founder asked for the menus to be grouped
by category. Two concrete faults beyond length:

- The **admin mobile** header rendered every item in a single horizontal flex row — visually
  broken well before 32 items.
- The **student mobile** bottom bar used `grid-cols-4` but mapped *all* items, stacking ~18
  tabs into a wall across the bottom of the screen.

There is no separate trainer/tutor portal — staff use the admin panel — so only two
navigations needed restructuring.

## Decision

1. **Group both navs by domain, one source of truth.** Admin →
   Teaching / Students / Careers / Sales & CRM / Finance / Retention & Support / Platform.
   Student → Learn / Progress / Career / You. Each nav is now `navGroups`; the flat
   `navItems` the ⌘K palette searches is *derived* from the groups (`flatMap`), so an item
   can never be in the menu but missing from search, or vice-versa.

2. **Group headers use the mono uppercase kicker** already in the design system, in a muted
   tone, so they read as quiet section labels rather than clickable items.

3. **Mobile admin: a Menu toggle → grouped two-column sheet**, replacing the flat row.

4. **Mobile student: four primary tabs + a More tab.** Primary destinations (Dashboard,
   Classes, Practice, Mock) are flagged on the nav items themselves; "More" opens a grouped
   bottom sheet with everything. The bottom bar is now a fixed `grid-cols-5`, so it can never
   overflow regardless of how many sections exist. Primary tabs carry an optional `short`
   label so "Mock Interviews" shows as "Mock" in the bar.

## Consequences

- **Positive:** Both navs stay scannable as features keep landing — a new page joins a group
  rather than lengthening one flat list, and the mobile surfaces no longer break with scale.
  Adding a nav item is a one-line change in the right group.
- **Trade-offs:** The group taxonomy is a judgement call and will need occasional
  re-balancing as the product shifts (e.g. Points sits under Finance today; it could move to
  a future Gamification group). The desktop admin sidebar is now taller than one screen and
  scrolls — acceptable, and expected at this feature count.
- **No behaviour change:** every existing route, label, active-state rule, and the ⌘K palette
  are preserved; this is presentation only.

## Verification

Pure frontend IA change, no API surface — `npm run typecheck`, `lint`, and `build` all pass.
The desktop sidebars still render every destination as a labelled link (so the existing admin
Playwright flows that click nav links by name are unaffected). New admin pages from this batch
of work (Candidates, plus P4.6a's Care desk) slot into their groups.

**Note:** a Playwright assertion for the grouped mobile menus is deferred — the local e2e
environment is currently contended by a second dev server (see ADR 0036's process note).
