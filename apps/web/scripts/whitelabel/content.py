#!/usr/bin/env python3
"""Content for the whitelabel partner document set.

Everything the partner pack asserts lives here, so a claim is changed in one
place and every document follows. Three rules govern what may be added:

1. **Shipped means shipped.** Anything in ``MODULES`` exists in the codebase
   today. Anything not yet built goes in ``ROADMAP`` and is rendered in a
   visually distinct band, exactly as the public /employers page does it.
2. **No unmodelled commercials.** Every price here is a list price traced to
   ``pricing_model.py`` — the workbook that builds cost bottom-up from
   published vendor rates and solves margin from it. If a number changes here
   it must change there first, or the margin it implies is fiction. Anything
   genuinely open still uses the ``PRICE_TBD`` sentinel, which renders as a
   filled marker so an unpriced line cannot be mistaken for a free one.
3. **Brand voice applies.** The rules in CLAUDE.md — no hype adjectives, no
   guarantees, every stat carrying its disclaimer — hold for B2B collateral
   exactly as they do for student-facing pages.
"""

from __future__ import annotations

# Rendered as a marker, never as a number: a placeholder that looks like a
# price is how an unagreed figure reaches a customer.
PRICE_TBD = "[ SET ]"

# List prices, ex-GST, monthly on an annual commitment. These are the figures
# solved in 08_..._Cost_and_Pricing_Model.xlsx; the gross margin each one earns
# is stated there and should be re-checked before any of them is edited.
CURRENCY_NOTE = "All prices ex-GST. Monthly rate on an annual commitment; add 20% for month-to-month."

COMPANY = {
    "entity": "IBrowseJobs Technologies Pvt Ltd",
    "product": "BrowseJobs Platform",
    "phone": "+91 86185 19825",
    "email": "hello@browsejobs.ai",
    "site": "browsejobs.ai",
    "address": "Fl 3, Channasandra Main Rd, Ambedkar Nagar, Whitefield, Bengaluru, Karnataka 560066",
    "hours": "Mon–Sat, 9:00 AM – 7:00 PM IST",
    "edition": "2026 Edition",
}

CONFIDENTIAL = (
    "Commercial in confidence. Prepared for evaluation by the named recipient and not for "
    "onward distribution."
)

# ----------------------------------------------------------------------------
# the offer
# ----------------------------------------------------------------------------

POSITIONING = {
    "headline": ["Your institute.", "Your brand.", "Our engine."],
    "audience": "A whitelabel LMS, CRM and placement platform for training institutes.",
    "subline": (
        "The platform BrowseJobs runs its own institute on, delivered under your name, on your "
        "domain, in your colours. Not a demo build — the same code, the same database, a "
        "different tenant."
    ),
    "proof": (
        "BrowseJobs is tenant 1 on this platform. Everything a partner gets is something we "
        "depend on ourselves, every working day."
    ),
}

# What "whitelabel" concretely means here, and what it does not. Being explicit
# about the boundary is what stops a scope dispute in month two.
BRANDING_SCOPE = {
    "included": [
        ("Brand name", "Replaces BrowseJobs across the portal, emails and documents."),
        ("Logo", "Your mark in the portal, on certificates and on generated PDFs."),
        (
            "Full colour palette",
            "Ten brand tokens — primary, deep accent, ink, highlight, page, success, error, "
            "review, border and muted — applied live from the admin panel.",
        ),
        ("Your own domain", "Students reach the portal on your domain, not a shared one."),
        ("Batch numbering", "Your own batch code pattern rather than ours."),
        ("Feature set", "Modules switched on or off per tenant, so you ship only what you sell."),
    ],
    "not_included": [
        (
            "A public marketing website",
            "The platform provides the student, trainer, admin and employer portals. Your "
            "marketing site stays yours — scoped separately if you want us to build it.",
        ),
        (
            "Your course content",
            "The syllabus engine, projects and question banks are BrowseJobs teaching material. "
            "Licensing them is a separate line, not part of the platform fee.",
        ),
        (
            "Your integrations' costs",
            "Zoom licences, payment-gateway fees, WhatsApp conversation charges and AI usage are "
            "billed on your own accounts or metered — see the pricing pack.",
        ),
    ],
    "how": (
        "Branding is stored per tenant and served live at GET /api/v1/branding. A colour changed "
        "in the admin panel re-skins the portal on the next load — there is no rebuild, no "
        "redeploy and no engineering ticket."
    ),
}

# ----------------------------------------------------------------------------
# the platform, module by module — all shipped
# ----------------------------------------------------------------------------

MODULES = [
    {
        "group": "Learning management",
        "note": "The student-facing product: 25 portal sections, all live.",
        "items": [
            ("Curriculum & syllabus", "Programs, courses, modules, topics and a day-by-day builder."),
            ("Live classes & recordings", "Scheduling, Zoom licence pooling, attendance, searchable recordings."),
            ("Assignments & grading", "Submission, rubric grading, trainer feedback, grade book."),
            ("Quizzes, MCQ & flashcards", "Per-lesson assessment with spaced repetition."),
            ("Coding Lab", "Browser-based labs on the real stack."),
            ("Content Hub & notes", "Per-lesson notes, slides and reference material."),
            ("AI Tutor", "Context-aware doubt resolution against the student's own syllabus."),
            ("Student pulse & check-in", "Engagement signals, risk flags, daily next best action."),
            ("Certificates", "Generated, QR-verifiable, publicly checkable."),
        ],
    },
    {
        "group": "Placement engine",
        "note": "What turns a course into an outcome.",
        "items": [
            ("Mock interviews", "Technical and HR mocks, AI-analysed, scored per dimension."),
            ("Voice mock lab", "Outbound voice AI interviews with quotas per student."),
            ("Interview question bank", "Live bank built from monitored interviews, by role and round."),
            ("CV generator & vault", "ATS-ready CV building, sharing and print."),
            ("Job feed & Jobs for You", "Live openings matched to the student's skill graph."),
            ("Apply assist", "Guided applications against matched roles."),
            ("Placement pipeline", "Candidate tracking from ready to offer accepted."),
            ("Mentoring", "Mentor profiles, availability, session booking and notes."),
        ],
    },
    {
        "group": "Employer workspace",
        "note": "The hiring side of the marketplace (ADR 0051).",
        "items": [
            ("JD builder", "AI-drafted structured roles with skill extraction."),
            ("Graded shortlist", "Ranked applicants with written match rationale."),
            ("AI screening calls", "Auto-dialled first-round screening with transcript and outcome."),
            ("Async proctored rounds", "L1, L2 and custom rounds with face-match and window flags."),
            ("Pipeline & automation", "Kanban ATS with a rules engine and dry-run simulation."),
            ("Evidence & graded report", "Replays, transcripts, per-rubric scores, proctoring record."),
        ],
    },
    {
        "group": "Institute CRM",
        "note": "The operations product: around 70 controllers across the admin surface.",
        "items": [
            ("Lead management", "Capture, assignment, status history, AI call analysis, batch funnel."),
            ("Campaigns", "Email and WhatsApp campaigns with recipient tracking."),
            ("Admissions & enrolment", "Enquiry to enrolled, with masterclass and bootcamp tracking."),
            ("Fees & finance", "Fee plans, instalments, collections, dunning, revenue dashboard."),
            ("Payments", "Razorpay orders, webhooks, receipts and invoice numbering."),
            ("Expenses & payroll", "Expense capture, analytics, salary and incentive records."),
            ("HR & office", "Leave types, balances, requests, holidays, tasks and attendance."),
            ("Users, roles & permissions", "Role-permission matrix with a menu-driven access model."),
            ("Support desk", "Ticketing with threaded messages and escalation."),
            ("Messaging hub", "Internal chat, email inbox, notifications and push."),
            ("Social analytics", "Instagram, LinkedIn and YouTube stats, posts and alerts."),
            ("Website CMS", "Page copy, SEO and course-page content edited without a deploy."),
            ("File manager", "Google Drive-backed document store."),
            ("Meeting intelligence", "Transcript analysis and meeting reports."),
        ],
    },
    {
        "group": "Platform & governance",
        "note": "What makes it safe to run several institutes on one system.",
        "items": [
            ("Multi-tenancy", "Every domain model tenant-scoped by a global scope, tested for cross-tenant denial."),
            ("Whitelabel manager", "Per-tenant name, logo, ten colour tokens and domain."),
            ("Feature flags & plans", "Per-tenant module gating stored on the tenant record."),
            ("Audit logging", "Grade changes, fee waivers, access blocks, role and roster changes."),
            ("AI gateway & telemetry", "Provider-agnostic LLM layer logging purpose, tokens, cost and latency per call."),
            ("Data requests (DPDP)", "Subject access and erasure workflow."),
            ("Vouchers & monetisation", "Discounts, boosters and store products."),
        ],
    },
]

# Not built. Rendered in its own labelled band so nothing here can be read as
# available — the same discipline the public employers page uses.
ROADMAP = [
    ("Public employer API & webhooks", "HMAC-signed webhooks and CSV/ATS import for employer pipelines."),
    ("Background verification", "A Trust Score from DigiLocker, PAN, education and EPFO records."),
    ("Semantic candidate search", "Natural-language candidate search with interpretable filters."),
    ("Partner-facing marketing site builder", "A themeable public site per tenant, beyond the portals."),
]

INTEGRATIONS = [
    ("Zoom", "Live classes, licence pooling, recordings", "Your account"),
    ("Razorpay", "Fees, instalments, receipts, webhooks", "Your account"),
    ("WhatsApp Cloud API", "Campaigns, nudges, notifications", "Your account"),
    ("Google OAuth & Calendar", "Sign-in, scheduling, Drive file store", "Your account"),
    ("Voice AI (Vapi / Retell)", "Voice mock interviews, AI screening calls", "Metered"),
    ("LLM providers", "Tutor, grading, syllabus, analysis — provider-switchable", "Metered"),
    ("Job feed (JSearch)", "Live openings for the job radar", "Shared or your key"),
    ("Meta lead ads", "Lead capture webhooks into the CRM", "Your account"),
    ("Email (SMTP/IMAP)", "Transactional mail and the CRM inbox", "Your account"),
    ("AWS S3", "Document, recording and render storage", "Ours or yours"),
]

# ----------------------------------------------------------------------------
# architecture & security
# ----------------------------------------------------------------------------

ARCHITECTURE = {
    "summary": (
        "A Laravel 11 API and a Next.js 15 front end over MySQL and Redis, deployed per "
        "environment. Tenancy is enforced in the data layer rather than the UI, so isolation "
        "does not depend on a screen being hidden."
    ),
    "layers": [
        ("Front end", "Next.js 15, App Router, server components; student, trainer, admin and employer portals."),
        ("API", "Laravel 11 (PHP 8.3), versioned /api/v1, thin controllers over single-purpose Action classes."),
        ("Data", "MySQL 8 with a tenant_id on every domain table, foreign keys and indexes."),
        ("Async", "Redis-backed queues for every side effect — AI calls, messaging, PDF renders, Zoom calls."),
        ("AI", "A single provider-switchable gateway; every call logged to ai_events with cost and latency."),
        ("Storage", "S3-compatible object storage for documents, recordings and generated PDFs."),
    ],
    "isolation": [
        (
            "Global scope, not a WHERE clause",
            "Every domain model uses the BelongsToTenant trait, so tenant scoping is applied by "
            "the ORM rather than remembered by each query.",
        ),
        (
            "Tested, not asserted",
            "The engineering standard requires a cross-tenant denial test for every feature; "
            "isolation tests span the API test suite.",
        ),
        (
            "Two resolution paths",
            "Public pages resolve the tenant by domain; portals resolve it by the authenticated "
            "user. A request cannot silently fall through to another tenant.",
        ),
        (
            "Audit trail",
            "Grade changes, fee waivers, access blocks, roster and role changes are written to an "
            "audit log rather than mutating silently.",
        ),
    ],
    "security": [
        ("Authentication", "Cookie-based SPA session auth with role and permission checks per route."),
        ("Magic links", "Signed, single-use, short-expiry, scoped to one action and consumed atomically."),
        ("Webhooks", "Razorpay, Zoom and WhatsApp signatures verified before processing; unsigned rejected."),
        ("Rate limiting", "Auth, OTP and AI endpoints rate limited; per-student daily AI budget enforced."),
        ("Secrets", "Environment-held, never in code, seeds, tests or documentation."),
        ("Money", "All currency handled in integer paise — never floating point."),
    ],
    # Stated as the questions a buyer must settle, not as commitments we have
    # not verified for their environment.
    "to_agree": [
        "Hosting region and data residency",
        "Shared or dedicated database instance",
        "Backup frequency, retention, and tested restore window (RPO / RTO)",
        "Penetration-test cadence and who commissions it",
        "Sub-processor list and change notification period",
    ],
}

# ----------------------------------------------------------------------------
# packaging
# ----------------------------------------------------------------------------

TIERS = [
    {
        "name": "Starter",
        "for": "A single-location institute running its first cohorts.",
        "includes": [
            "Full LMS: curriculum, live classes, assignments, quizzes, certificates",
            "Student portal on a browsejobs.ai subdomain",
            "Whitelabel name, logo and colour palette",
            "Standard mock interviews (no voice AI)",
            "Email support, next business day",
        ],
        "limits": "Capped active students · single admin workspace · shared infrastructure",
        "seats": "Up to 100 enrolled",
        "price_inr": "₹24,999",
        "price_usd": "$499",
        "allowance": "AI Assist for 25 students · 150 voice minutes (≈10 mock interviews)",
    },
    {
        "name": "Growth",
        "for": "An institute running admissions and placement as a business.",
        "includes": [
            "Everything in Starter",
            "Full institute CRM: leads, campaigns, fees, finance, HR, support desk",
            "Your own domain",
            "Voice mock lab and AI tutor, with a monthly AI allowance",
            "Placement pipeline and job feed",
            "Priority support with a defined response SLA",
        ],
        "limits": "Higher student cap · AI usage beyond the allowance billed as overage",
        "seats": "Up to 500 enrolled",
        "price_inr": "₹74,999",
        "price_usd": "$1,499",
        "allowance": "AI Assist for 100 students · 400 voice minutes (≈27 mock interviews)",
    },
    {
        "name": "Enterprise",
        "for": "Multi-branch groups, or institutes with their own compliance bar.",
        "includes": [
            "Everything in Growth",
            "Employer workspace and hiring pipeline",
            "Dedicated database instance",
            "Custom integrations and data migration",
            "Named account manager and a contractual SLA",
            "Security review support and a signed DPA",
        ],
        "limits": "Scoped per engagement",
        "seats": "Up to 1,500 enrolled, then per seat",
        "price_inr": "₹1,99,999",
        "price_usd": "$3,499",
        "allowance": "AI Assist for 250 students · 800 voice minutes (≈53 mock interviews)",
    },
]

# The metered layer. Voice is roughly ten times the cost of everything else a
# student consumes in a month, which is the entire reason it is billed by the
# minute instead of bundled. Margins are stated in the cost model workbook.
METERED = [
    ("AI Assist", "Per AI-active student, per month",
     "Tutor, auto-grading and written mock-interview analysis.",
     "₹199", "$3.00"),
    ("AI Voice mock", "Per minute, on our voice keys",
     "A 15-minute mock interview costs about ₹375.",
     "₹25", "$0.40"),
    ("AI Voice mock", "Per minute, on your own Vapi/Twilio keys",
     "Orchestration only — you hold the vendor contract and the bill.",
     "₹6", "$0.10"),
    ("Extra enrolled seat", "Per seat, per month, beyond the tier band",
     "Charged on enrolment, not on activity.",
     "₹35", "$0.60"),
]

# Prepaid voice blocks. The ladder exists so the volume discount is bounded and
# visible rather than negotiated line by line.
VOICE_BLOCKS = [
    ("Trial", "500 minutes", "₹12,499", "₹25.00 / min"),
    ("Standard", "1,000 minutes", "₹24,999", "₹25.00 / min"),
    ("Volume", "5,000 minutes", "₹1,14,999", "₹23.00 / min"),
    ("Institutional", "20,000 minutes", "₹4,19,999", "₹21.00 / min"),
]

ONE_TIME = [
    ("Onboarding & implementation",
     "Branding, data model, first cohort live. Six phases, described in document 06.",
     "₹1,25,000", "$2,500"),
    ("Data migration, per source system",
     "Extract, map, dry run, cutover. One fee per system you are migrating from.",
     "₹40,000", "$999"),
    ("Custom domain + branded app store listing",
     "Your domain on the platform, and your own listing under your developer account.",
     "₹49,999", "$999"),
]

# What a salesperson may concede without asking. The floor is a margin, not a
# price, because a discount that looks small on a licence can be fatal on a
# voice allowance.
DISCOUNT_POLICY = [
    ("Annual prepay", "Two months free — a 16.7% effective discount. The standard concession."),
    ("Two-year commitment", "20% off the licence, invoiced annually in advance."),
    ("Reference partner", "Onboarding fee discounted to cost, in exchange for a named case study "
     "and a reference call. Discount the onboarding fee, never the licence: the licence is what renews."),
    ("Voice allowance", "Not a concession. Included minutes are stated in the order form and are "
     "the single most expensive thing that can be given away — at 2,500 included minutes the Growth "
     "tier margin halves, and at 5,000 it is negative."),
    ("Floor", "No licence below 55% gross margin without founder sign-off."),
]

REPLACES = [
    ("Starter", "₹24,999", "≈ ₹22,500",
     "Classplus ₹15,000 + CRM ₹2,500 × 3 seats"),
    ("Growth", "₹74,999", "≈ ₹51,000",
     "Classplus ₹15,000 + CRM ₹4,500 × 8 seats"),
    ("Enterprise", "₹1,99,999", "≈ ₹2,15,000",
     "Teachmint ₹1,25,000 + CRM ₹4,500 × 20 seats"),
]

# What a voice concession actually costs, straight out of the Sensitivity sheet
# of the cost model. Growth tier, India list price. This exists so nobody has to
# take "minutes are expensive" on trust in a negotiation.
VOICE_CONCESSION = [
    ("0", "None — voice sold separately", "75.5%", "Healthy"),
    ("400", "The Growth allowance", "68.8%", "Target"),
    ("1,000", "A generous concession", "58.9%", "Above the floor"),
    ("2,500", "Half the margin gone", "34.0%", "Founder sign-off"),
    ("5,000", "Loss-making", "−7.4%", "Never"),
]

PRICING_AXES = [
    (
        "Active students per month",
        "The primary meter. It tracks the value a partner gets and scales with their success "
        "rather than their headcount.",
    ),
    (
        "AI usage",
        "The second meter, and the one that moves. A month of platform for one student costs us "
        "cents; a single 15-minute voice mock costs dollars. Each tier includes an allowance so the "
        "AI demonstrates itself; beyond it, usage is billed against the metered record. A partner "
        "who would rather hold the vendor relationship can bring their own voice keys and pay "
        "orchestration only.",
    ),
    (
        "Modules enabled",
        "Tiers are enforced by the per-tenant feature flags the platform already reads, so a "
        "package is a configuration rather than a different build.",
    ),
    (
        "Deployment",
        "Shared infrastructure by default; a dedicated database instance is an Enterprise option.",
    ),
    (
        "Support tier",
        "Response times and escalation path, set out in the SLA.",
    ),
]

COMMERCIAL_MODELS = [
    (
        "Licence subscription",
        "A monthly or annual platform fee by tier, plus a one-time onboarding fee. Predictable "
        "for both sides, and the default.",
    ),
    (
        "Revenue share",
        "A lower platform fee against an agreed share of course revenue. Suits a partner starting "
        "from zero cohorts who would rather pay as they grow.",
    ),
    (
        "Per-seat enrolment fee",
        "A flat fee per enrolled student with no monthly minimum. Suits seasonal or "
        "cohort-driven institutes.",
    ),
]

BILLING_NOTES = [
    "Fees are quoted exclusive of GST. Indian invoices carry GST at 18%.",
    "Listed rates are the monthly price on an annual commitment. Month-to-month is 20% higher.",
    "AI usage above the tier allowance is billed monthly in arrears against the metered record in "
    "ai_events — purpose, model, tokens, cost and latency, per call, per tenant.",
    "Voice minutes may be prepaid in blocks, or billed in arrears at the standard rate. Prepaid "
    "blocks do not expire while the subscription is live.",
    "Third-party costs on your own accounts — Zoom, payment gateway, WhatsApp — are never marked up by us.",
    "Annual commitments are invoiced in advance; monthly plans in advance, per month.",
    "The onboarding fee is one-time and is due before implementation begins.",
    "International pricing is billed in USD. The INR and USD price lists are independent, not an FX "
    "conversion of one another.",
]

# ----------------------------------------------------------------------------
# onboarding
# ----------------------------------------------------------------------------

ONBOARDING = [
    {
        "phase": "Week 0",
        "title": "Agreement & kickoff",
        "ours": ["Countersigned order form", "Tenant provisioned", "Kickoff call and named contacts"],
        "yours": ["Signed agreement", "Onboarding fee", "Named project owner"],
    },
    {
        "phase": "Week 1",
        "title": "Brand & domain",
        "ours": ["Branding applied", "Domain mapped and TLS issued", "Email sender configured"],
        "yours": ["Logo files", "Colour palette or brand guide", "DNS access", "Sending domain"],
    },
    {
        "phase": "Week 2",
        "title": "Configuration",
        "ours": ["Roles and permissions", "Fee plans", "Batch numbering", "Feature flags per your tier"],
        "yours": ["Course and batch structure", "Fee model", "Staff list and roles"],
    },
    {
        "phase": "Week 3",
        "title": "Integrations",
        "ours": ["Payment gateway", "Zoom", "WhatsApp", "Email", "Webhook verification"],
        "yours": ["Gateway credentials", "Zoom licences", "WhatsApp business account", "Approvals"],
    },
    {
        "phase": "Week 4",
        "title": "Data & training",
        "ours": ["Migration of your existing students and leads", "Admin and trainer training", "UAT support"],
        "yours": ["Export of current data", "Staff availability for training", "UAT sign-off"],
    },
    {
        "phase": "Week 5",
        "title": "Go live",
        "ours": ["Production cutover", "Hypercare", "Handover to support"],
        "yours": ["Go-live approval", "Student communication"],
    },
]

DEMO_SCRIPT = [
    ("02 min", "The problem", "Their current stack: spreadsheets, WhatsApp groups, a rented LMS nobody logs into."),
    ("05 min", "Whitelabel reveal", "Open the admin panel, change a brand colour, reload the student portal in their colours. This is the moment that sells."),
    ("08 min", "Student journey", "Dashboard, live class, assignment, AI tutor, mock interview, certificate."),
    ("06 min", "Placement engine", "Mock scores, readiness, job feed, placement pipeline."),
    ("06 min", "Institute CRM", "Lead in, funnel, fee collection, revenue dashboard."),
    ("04 min", "Governance", "Feature flags, roles, audit log, tenant isolation."),
    ("04 min", "Commercials", "Tier that fits, what is metered, what happens next."),
]

# ----------------------------------------------------------------------------
# commercial framework — term sheet, not an executed contract
# ----------------------------------------------------------------------------

LEGAL_WARNING = (
    "This is a commercial term sheet prepared to structure a negotiation. It is not legal advice "
    "and it is not an executable agreement. Every section must be reviewed and redrafted by "
    "qualified counsel in the governing jurisdiction before it is put in front of a customer for "
    "signature. Headings marked DECIDE record a position the business must take before drafting."
)

TERM_SHEET = [
    {
        "clause": "Grant of licence",
        "position": "A non-exclusive, non-transferable, revocable right to access and use the platform for the partner's own training business, for the subscription term, limited to the tier purchased.",
        "decide": "Whether territory is restricted, and whether any exclusivity is ever offered by city or segment.",
    },
    {
        "clause": "Ownership of IP",
        "position": "All platform software, and all BrowseJobs course content, remain ours. The partner's brand, learner data and their own uploaded content remain theirs.",
        "decide": "Whether teaching material is licensed at all, and if so on what separate terms.",
    },
    {
        "clause": "Trademark licence",
        "position": "The partner licenses us to apply their marks to the tenant for the term. We license no marks to them beyond an optional 'Powered by' attribution.",
        "decide": "Is 'Powered by BrowseJobs' required, optional, or a discount in exchange for it?",
    },
    {
        "clause": "Fees & increases",
        "position": "Fees per the order form. Annual increase capped at a stated percentage, notified in advance.",
        "decide": "The cap, and the notice period.",
    },
    {
        "clause": "AI usage & overage",
        "position": "Each tier includes a stated monthly AI allowance, metered per tenant. Overage is billed in arrears at a published rate.",
        "decide": "The allowance per tier and the overage rate. Do not offer unlimited AI on a flat fee.",
    },
    {
        "clause": "Data protection",
        "position": "The partner is the data fiduciary for learner data; we act as data processor under a separate DPA aligned to the DPDP Act. Sub-processors listed, with notice on change.",
        "decide": "Hosting region, retention periods, and the sub-processor change-notice period.",
    },
    {
        "clause": "Security & availability",
        "position": "A stated monthly uptime target with severity-based response times, measured excluding notified maintenance windows.",
        "decide": "The uptime figure, the maintenance window, and whether service credits apply.",
    },
    {
        "clause": "Support",
        "position": "Support by tier, through a named channel, during stated business hours.",
        "decide": "Hours, channels, and whether out-of-hours cover is sold separately.",
    },
    {
        "clause": "Acceptable use",
        "position": "No resale or sublicensing of platform access, no misuse of learner data, no attempts to circumvent tenant isolation or usage metering.",
        "decide": "Whether sub-institutes or franchisees of the partner are permitted, and how they are counted.",
    },
    {
        "clause": "Term, renewal & termination",
        "position": "Initial term with auto-renewal unless notice is given. Termination for material breach after a cure period; termination for convenience only at renewal.",
        "decide": "Initial term length, notice period, and cure period.",
    },
    {
        "clause": "Exit & data portability",
        "position": "On termination the partner receives a complete structured export of their tenant data within a stated window; we delete it after a stated retention period, on request.",
        "decide": "Export format, delivery window, and post-termination retention period.",
    },
    {
        "clause": "Liability",
        "position": "Liability capped at fees paid in the preceding twelve months; no indirect or consequential loss. Standard carve-outs.",
        "decide": "Whether any carve-out is uncapped, and confirm insurance cover matches the cap.",
    },
    {
        "clause": "Governing law",
        "position": "Governed by Indian law; courts of Bengaluru, Karnataka.",
        "decide": "Whether arbitration is preferred, and the seat.",
    },
]

DOCUMENT_SET = [
    ("01", "Whitelabel Overview", "First touch", "What it is, who it is for, how to buy."),
    ("02", "Platform Capabilities", "First touch", "Every shipped module, the integrations, the roadmap."),
    ("03", "Pricing & Packages", "First touch", "Tiers, what is metered, the commercial models."),
    ("04", "Feature Matrix", "Evaluation", "Module by tier — the contract annexure."),
    ("05", "Technical & Security", "Evaluation", "Architecture, tenant isolation, security posture."),
    ("06", "Onboarding Plan", "Evaluation", "Signature to go-live, and who owes what."),
    ("07", "Commercial Framework", "Contracting", "Term sheet for counsel to draft from."),
]
