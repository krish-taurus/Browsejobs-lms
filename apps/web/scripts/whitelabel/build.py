#!/usr/bin/env python3
"""Render the whitelabel partner document set.

Six PDFs a partner receives across the sales cycle, all from ``content.py`` and
all sharing the brochure design system — same page masters, same charts, same
mark on every page. The set is deliberately split by sales stage rather than
bundled: an overview goes to anyone who asks, a term sheet does not.

Named ``build.py``, not ``generate.py``: the brochure renderer it imports from
is itself called ``generate``, and a same-named sibling on ``sys.path`` shadows
it into a circular import.

Usage::

    python3 scripts/whitelabel/build.py

Wrapped by ``npm run partner-docs`` in apps/web. Requires WeasyPrint.
"""

from __future__ import annotations

import sys
from pathlib import Path

from weasyprint import CSS, HTML

HERE = Path(__file__).resolve().parent
BROCHURE = HERE.parent / "brochure"
sys.path.insert(0, str(BROCHURE))
sys.path.insert(0, str(HERE))

import charts  # noqa: E402  (path set above)
import content as C  # noqa: E402
from generate import MARK_SVG, e, join  # noqa: E402

STYLESHEET = BROCHURE / "brochure.css"
OUT_DIR = HERE.parents[3] / "docs" / "partner"


# ----------------------------------------------------------------------------
# scaffolding
# ----------------------------------------------------------------------------


def ghost(n: str) -> str:
    return f'<div class="ghost" aria-hidden="true">{e(n)}</div>'


def spread_mark() -> str:
    return f'<div class="spread-mark" aria-hidden="true">{MARK_SVG}</div>'


def spread_foot(n: str, title: str) -> str:
    return (
        f'<div class="spread-foot"><span>{e(title)}</span>'
        f'<span class="no">{e(n)}</span></div>'
    )


def cover(doc_no: str, title: str, subtitle: str, kicker: str) -> str:
    """Every document in the set opens on the same ink spread, so the pack
    reads as one family however the pieces arrive."""
    return f"""
<section class="page bleed cover-spread partner-cover">
  <div class="cover-mark">{MARK_SVG}<span>BrowseJobs<i>.ai</i></span></div>
  <p class="platform-line">{e(C.COMPANY['product'])} · Whitelabel partner pack</p>
  <p class="doc-no mono">{e(doc_no)}</p>
  <h1 class="doc-title">{e(title)}</h1>
  <p class="doc-sub">{e(subtitle)}</p>
  {charts.breakout_motif()}
  <div class="cover-base">
    <p class="kicker free">{e(kicker)}</p>
    <p class="meta">{e(C.COMPANY['entity'])} · {e(C.COMPANY['edition'])}</p>
    <p class="confidential">{e(C.CONFIDENTIAL)}</p>
  </div>
</section>
"""


def page(inner: str, numeral: str = "") -> str:
    return f'<section class="page">{ghost(numeral) if numeral else ""}{inner}</section>'


def cards(rows, cls: str = "three") -> str:
    return (
        f'<div class="grid {cls}">'
        + join(
            f'<div class="card"><p class="f-name">{e(t)}</p>'
            f'<p class="f-short mt-2">{e(b)}</p></div>'
            for t, b in rows
        )
        + "</div>"
    )


def table(rows, headers=None, *, key_width: str = "") -> str:
    head = (
        "<tr>" + join(f"<th>{e(h)}</th>" for h in headers) + "</tr>" if headers else ""
    )
    body = join(
        "<tr>"
        + join(
            f'<td class="{"key" if i == 0 else ""}">{e(c)}</td>'
            for i, c in enumerate(r)
        )
        + "</tr>"
        for r in rows
    )
    return f'<table class="mt-2{" fixed" if key_width else ""}">{head}{body}</table>'


def document(title: str, pages: str) -> str:
    return f"""<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>{e(title)}</title></head>
<body class="master partner">
  <div class="mark" aria-hidden="true">{MARK_SVG}</div>
  {pages}
</body>
</html>
"""


def render(filename: str, title: str, pages: str) -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    target = OUT_DIR / filename
    HTML(string=document(title, pages), base_url=str(BROCHURE)).write_pdf(
        target, stylesheets=[CSS(filename=str(STYLESHEET))]
    )
    print(f"  {target.name}  ({target.stat().st_size / 1024:.0f} KB)")


# ----------------------------------------------------------------------------
# 01 · overview
# ----------------------------------------------------------------------------


def doc_overview() -> None:
    p = C.POSITIONING
    models = join(
        f'<div class="card"><p class="kicker">{e(t)}</p>'
        f'<p class="f-short mt-2">{e(b)}</p></div>'
        for t, b in C.COMMERCIAL_MODELS
    )
    tiers = join(
        f'<div class="tier"><p class="tier-name">{e(t["name"])}</p>'
        f'<p class="tier-seats">{e(t["seats"])}</p>'
        f'<p class="tier-for">{e(t["for"])}</p>'
        f'<p class="tier-price mono">{e(t["price_inr"])}</p>'
        f'<p class="tier-limits">{e(t["price_usd"])} international</p></div>'
        for t in C.TIERS
    )
    set_rows = [(n, t, s, w) for n, t, s, w in C.DOCUMENT_SET]

    pages = cover(
        "01",
        "Whitelabel\nOverview",
        p["audience"],
        "Start here",
    ) + page(
        f"""
  <p class="kicker">The offer</p>
  <h2 class="section-title">{e(" ".join(p["headline"]))}</h2>
  <p class="lede">{e(p["subline"])}</p>

  <div class="rule-block mt-8">
    <span class="rule-label">Why this is not a demo build</span>
    {e(p["proof"])}
  </div>

  <p class="label mt-10">What your students and staff actually see</p>
  <div class="grid two mt-4">
    <div class="card"><p class="f-name">Your brand, applied live</p>
      <p class="f-short mt-2">{e(C.BRANDING_SCOPE["how"])}</p></div>
    <div class="card"><p class="f-name">Your own domain</p>
      <p class="f-short mt-2">Students reach the portal on your domain. Tenant resolution is by
      domain for public pages and by the signed-in user for portals.</p></div>
  </div>

  <p class="label mt-8">Included in a whitelabel tenant</p>
  {table([(t, b) for t, b in C.BRANDING_SCOPE["included"]])}

""",
        "01",
    ) + page(
        f"""
  <p class="label">Explicitly not included</p>
  {table([(t, b) for t, b in C.BRANDING_SCOPE["not_included"]])}

  <p class="kicker mt-10">Packages</p>
  <h2 class="section-title">Three tiers.
Three ways to pay.</h2>

  <div class="tier-row mt-8">{tiers}</div>
  <p class="disclaimer">{e(C.CURRENCY_NOTE)} AI Assist and voice minutes are metered above the
  included allowance — document 03 carries the full price list. Pricing is confirmed in writing on
  the order form before anything is charged.</p>

  <p class="label mt-8">Commercial models</p>
  <div class="grid three mt-4">{models}</div>

""",
        "02",
    ) + page(
        f"""
  <p class="kicker">The pack</p>
  <h2 class="section-title">Seven documents,
by stage.</h2>
  <p class="lede">You have document 01. The rest arrive as the conversation moves — a term sheet
  is not a first-touch document.</p>
  {table(set_rows, ["Doc", "Title", "Stage", "What it answers"])}

  <div class="closing mt-10">
    <h3>Next step: a live walkthrough.</h3>
    <p>Forty minutes, your questions, and a tenant re-skinned in your colours while you watch.</p>
    <div class="cta">{e(C.COMPANY['email'])} · {e(C.COMPANY['phone'])}</div>
  </div>
""",
        "03",
    )
    render("01_BrowseJobs_Whitelabel_Overview.pdf", "Whitelabel Overview", pages)


# ----------------------------------------------------------------------------
# 02 · capabilities
# ----------------------------------------------------------------------------


def doc_capabilities() -> None:
    counts = [
        ("48", "Admin modules in the LMS"),
        ("25", "Student portal sections"),
        ("~70", "CRM operational modules"),
        ("51", "Architecture decision records"),
    ]
    kpis = join(
        f'<div class="kpi"><div class="kpi-value mono">{e(v)}</div>'
        f'<div class="kpi-label">{e(l)}</div></div>'
        for v, l in counts
    )

    def group_block(group: dict, first: bool) -> str:
        return (
            f'<div class="group-head{"" if first else " mt-10"}">'
            f'<p class="kicker">{e(group["group"])}</p>'
            f'<h2 class="section-title">{e(group["group"])}</h2>'
            f'<p class="lede">{e(group["note"])}</p></div>'
            + table([(t, b) for t, b in group["items"]], ["Module", "What it does"])
        )

    # The groups run from 6 to 14 rows, so any fixed page-per-group or pairing
    # left one page nearly blank and another half full. They flow instead,
    # continuing straight on from the intro, and the tables break where they
    # land — .group-head keeps a heading with its first rows.

    pages = (
        cover(
            "02",
            "Platform\nCapabilities",
            "Every module that ships today, the integrations behind them, and what is still roadmap.",
            "For evaluation",
        )
        + page(
            f"""
  <p class="kicker">The surface</p>
  <h2 class="section-title">One platform,
five product areas.</h2>
  <p class="lede">Counts below are modules present in the codebase today. Anything not yet built
  is listed separately at the end of this document and is never described here as available.</p>

  <div class="kpi-row mt-8">{kpis}</div>

  <p class="label mt-8">How to read this document</p>
  <p class="body mt-2">Each of the next five pages covers one product area, module by module.
  The integrations table names whose account each third-party service runs on, because that
  determines who is billed for it.</p>

  <div class="rule-block mt-8">
    <span class="rule-label">Shipped means shipped</span>
    Everything in the module tables exists and is in daily use on tenant 1. We would rather lose a
    deal than describe a roadmap item as available.
  </div>

  """
            + join(group_block(g, False) for g in C.MODULES)
            + """
""",
            "01",
        )
        + page(
            f"""
  <p class="kicker">Integrations</p>
  <h2 class="section-title">What it connects to —
and who is billed.</h2>
  {table(list(C.INTEGRATIONS), ["Service", "Used for", "Account"])}
  <p class="disclaimer">“Your account” means the service runs on credentials you own and is billed
  to you directly; we never mark those up. “Metered” means usage is measured per tenant on our
  account and billed on at the rate in your order form.</p>

  <div class="rule-block coach mt-8">
    <span class="rule-label">On the roadmap — not available yet</span>
    <ul class="tight mt-2">{join(f"<li><strong>{e(t)}</strong> — {e(b)}</li>" for t, b in C.ROADMAP)}</ul>
  </div>
""",
            "07",
        )
    )
    render("02_BrowseJobs_Whitelabel_Platform_Capabilities.pdf", "Platform Capabilities", pages)


# ----------------------------------------------------------------------------
# 03 · pricing
# ----------------------------------------------------------------------------


def doc_pricing() -> None:
    """The price list, and the cost reasoning that produced it.

    Two independent price lists sit side by side rather than one converted at
    spot: the cost base is dollar-denominated and identical in both markets,
    but the anchor price is not, and rendering INR as a conversion of USD is
    how a partner argues you down to the cheaper of the two.
    """
    replaces = table(
        [(n, f"{ours} / mo", stack, why) for n, ours, stack, why in C.REPLACES],
        ["Tier", "Our licence", "Stack today", "That stack, itemised"],
    )
    tier_cards = join(
        f'<div class="tier-card"><p class="tier-name">{e(t["name"])}</p>'
        f'<p class="tier-seats">{e(t["seats"])}</p>'
        f'<p class="tier-for">{e(t["for"])}</p>'
        f'<div class="tier-prices">'
        f'<div class="price-pair"><span class="price-cur">India</span>'
        f'<span class="price-val">{e(t["price_inr"])}<i>/mo</i></span></div>'
        f'<div class="price-pair"><span class="price-cur">Intl</span>'
        f'<span class="price-val">{e(t["price_usd"])}<i>/mo</i></span></div></div>'
        f'<div class="tier-allow"><b>AI included every month</b>{e(t["allowance"])}</div>'
        f'<ul class="check mt-4">{join(f"<li>{e(x)}</li>" for x in t["includes"])}</ul>'
        f'<p class="tier-limits">{e(t["limits"])}</p></div>'
        for t in C.TIERS
    )
    metered = table(
        [(f"{n} — {basis}", why, inr, usd) for n, basis, why, inr, usd in C.METERED],
        ["Meter", "What it covers", "India", "Intl"],
    ).replace('<table class="mt-2"', '<table class="mt-2 prices"')
    blocks = table(list(C.VOICE_BLOCKS),
                   ["Block", "Minutes", "Price (India)", "Effective rate"])
    one_time = table([(n, why, inr, usd) for n, why, inr, usd in C.ONE_TIME],
                     ["One-time fee", "What it covers", "India", "Intl"]
                     ).replace('<table class="mt-2"', '<table class="mt-2 prices"')
    policy = table(list(C.DISCOUNT_POLICY), ["Concession", "What may be given"])
    concession = table(
        list(C.VOICE_CONCESSION),
        ["Included minutes / mo", "What that is", "Gross margin", "Verdict"],
    )
    cost_figure = charts.cost_scale([
        {"name": "Platform", "value": 0.2095, "label": "$0.21"},
        {"name": "AI Assist", "value": 0.52, "label": "$0.52"},
        {"name": "One voice mock", "value": 1.95, "label": "$1.95"},
        {"name": "Four voice mocks", "value": 7.80, "label": "$7.80"},
    ])

    pages = (
        cover(
            "03",
            "Pricing &\nPackages",
            "Three tiers, two price lists, and one meter that actually moves.",
            "Commercial",
        )
        + page(
            f"""
  <p class="kicker">Packages</p>
  <h2 class="section-title">Three tiers.</h2>
  <p class="lede">Tiers are enforced by per-tenant feature flags, so moving between them is a
  configuration change rather than a migration. Every tier is whitelabel — your name, your logo,
  your palette — from the first one.</p>

  <div class="tier-row full mt-8">{tier_cards}</div>
  <p class="disclaimer">{e(C.CURRENCY_NOTE)} Final terms are set on the order form; nothing is
  charged without a written agreement first.</p>

  <p class="label mt-8">What the licence replaces</p>
  {replaces}
  <p class="disclaimer">No vendor sells LMS, institute CRM, placement and AI mocks as one product,
  so the comparison is against the stack a partner already pays for — not counting the staff time
  that stitches it together. Published or third-party-reported list prices, August 2026:
  indicative, not quotes. Sources are listed in the cost model.</p>
""",
            "01",
        )
        + page(
            f"""
  <p class="kicker">The meters</p>
  <h2 class="section-title">One month of platform costs cents.
One voice mock costs dollars.</h2>
  <p class="lede">That single fact decides the whole commercial shape. Everything a student
  consumes in a month — video, storage, compute, messaging — is a fraction of what one
  fifteen-minute voice interview costs to run. So the platform is licensed, and voice is
  metered.</p>

  {metered}

  <p class="label mt-8">What one student costs us to serve, per month</p>
  {cost_figure}
  <p class="fig-key"><b>Platform</b> video delivery, recording storage, compute and WhatsApp for one
  active student · <b>AI Assist</b> a month of tutor questions, auto-grading and written mock
  analysis · <b>voice mock</b> one fifteen-minute call, platform and telephony · <b>four</b> the
  weekly-mock programme for one student for one month.</p>
  <p class="disclaimer">Modelled cost of service, not price. Built bottom-up from published vendor
  rates — CDN, object storage, model tokens, voice platform, telephony — in the cost model
  workbook. The axis is linear on purpose: the gap is the point.</p>

  <div class="rule-block mt-8">
    <span class="rule-label">What follows from it</span>
    Seats can be sold generously and minutes cannot. Doubling a partner's enrolled seats costs us
    cents per student; doubling their included voice minutes costs dollars per student. That is why
    the licence bands are wide, the voice allowance is narrow and stated in the order form, and the
    partner who wants unlimited voice is offered their own keys rather than a bigger bundle.
  </div>

""",
            "02",
        )
        + page(
            f"""
  <p class="kicker">Buying AI</p>
  <h2 class="section-title">Prepaid voice blocks.</h2>
  <p class="lede">Voice is the only line where our margin is genuinely thin — roughly half of what
  you pay is a third party's invoice. Blocks make the volume discount visible and bounded rather
  than negotiated deal by deal.</p>
  {blocks}
  <p class="disclaimer">India list. Blocks do not expire while the subscription is live. Usage
  beyond a block, or without one, is billed monthly in arrears at the standard rate.</p>

  <p class="label mt-10">One-time fees</p>
  {one_time}

  <div class="rule-block mt-8">
    <span class="rule-label">Why AI is metered and not bundled</span>
    Voice mock interviews, the AI tutor and AI screening calls carry a genuine per-call cost. The
    platform already records purpose, model, tokens, cost and latency for every AI call, per
    tenant. A flat unlimited fee would make the heaviest user the worst margin — the partner
    getting the most value would be the one we most want to lose — so each tier carries a stated
    allowance and everything beyond it is billed against that record.
  </div>

  <p class="label mt-8">Three ways to buy</p>
  {table([(t, b) for t, b in C.COMMERCIAL_MODELS], ["Model", "How it works"])}
""",
            "03",
        )
        + page(
            f"""
  <p class="kicker">Negotiating</p>
  <h2 class="section-title">What may be discounted.</h2>
  <p class="lede">A concession on a licence is recoverable. A concession on included voice minutes
  is not — it changes the cost of serving that partner every month for the life of the contract.</p>
  {policy}

  <p class="label mt-10">What a voice concession costs — Growth tier at ₹74,999</p>
  {concession}
  <p class="disclaimer">Gross margin on cost of service, from the Sensitivity sheet of the cost
  model. Excludes R&amp;D, sales and G&amp;A, so the contribution margin is materially lower than
  each figure shown.</p>
""",
            "04",
        )
        + page(
            f"""
  <p class="kicker">How it is priced</p>
  <h2 class="section-title">Five axes.</h2>
  {table(list(C.PRICING_AXES), ["Axis", "Why"])}

  <p class="label mt-8">Billing notes</p>
  <ul class="check mt-4">{join(f"<li>{e(n)}</li>" for n in C.BILLING_NOTES)}</ul>
""",
            "05",
        )
    )
    render("03_BrowseJobs_Whitelabel_Pricing_and_Packages.pdf",
           "Pricing & Packages", pages)


# ----------------------------------------------------------------------------
# 05 · technical & security
# ----------------------------------------------------------------------------


def doc_technical() -> None:
    a = C.ARCHITECTURE
    pages = (
        cover(
            "05",
            "Technical &\nSecurity",
            "Architecture, tenant isolation, security posture, and the questions your reviewer will ask.",
            "For your technical reviewer",
        )
        + page(
            f"""
  <p class="kicker">Architecture</p>
  <h2 class="section-title">How it is built.</h2>
  <p class="lede">{e(a["summary"])}</p>
  {table([(t, b) for t, b in a["layers"]], ["Layer", "What runs there"])}

  <p class="label mt-8">Security posture</p>
  {table([(t, b) for t, b in a["security"]], ["Control", "Implementation"])}
""",
            "01",
        )
        + page(
            f"""
  <p class="kicker">Tenant isolation</p>
  <h2 class="section-title">The question your reviewer
will actually ask.</h2>
  <p class="lede">Isolation is enforced in the data layer, not the interface. A screen being
  hidden is not a security control.</p>
  {table([(t, b) for t, b in a["isolation"]], ["Mechanism", "How it works"])}

  <div class="rule-block mt-8">
    <span class="rule-label">Evidence, on request</span>
    We will walk your reviewer through the tenant scoping trait, the two resolution middlewares
    and the cross-tenant denial tests in the API suite, on a screen share, before you sign.
  </div>

  <p class="label mt-10">To be agreed per engagement</p>
  <p class="body mt-2">These are deliberately not pre-answered. They depend on your compliance
  requirements and are settled in the order form and DPA rather than asserted in a brochure.</p>
  <ul class="check mt-4">{join(f"<li>{e(x)}</li>" for x in a["to_agree"])}</ul>
""",
            "02",
        )
    )
    render("05_BrowseJobs_Whitelabel_Technical_and_Security.pdf", "Technical & Security", pages)


# ----------------------------------------------------------------------------
# 06 · onboarding
# ----------------------------------------------------------------------------


def doc_onboarding() -> None:
    phases = [
        {"label": p["phase"], "month": p["phase"].replace("Week ", "W"), "weight": 1, "detail": p["title"]}
        for p in C.ONBOARDING
    ]
    timeline = charts.journey_timeline(phases)
    phase_row = (
        '<div class="phase-row six">'
        + join(
            f'<div class="phase"><p class="phase-name">{e(p["title"])}</p>'
            f'<p class="phase-detail">{e(p["phase"])}</p></div>'
            for p in C.ONBOARDING
        )
        + "</div>"
    )
    def raci(p: dict) -> str:
        return (
            f'<div class="raci"><p class="raci-phase mono">{e(p["phase"])} · {e(p["title"])}</p>'
            f'<div class="raci-cols"><div><p class="raci-h">We do</p>'
            f'<ul class="tight">{join(f"<li>{e(x)}</li>" for x in p["ours"])}</ul></div>'
            f'<div><p class="raci-h">You provide</p>'
            f'<ul class="tight">{join(f"<li>{e(x)}</li>" for x in p["yours"])}</ul></div></div></div>'
        )

    rows_a = join(raci(p) for p in C.ONBOARDING[:3])
    rows_b = join(raci(p) for p in C.ONBOARDING[3:])
    demo = table(list(C.DEMO_SCRIPT), ["Time", "Beat", "What you show"])

    pages = (
        cover(
            "06",
            "Onboarding\nPlan",
            "Signature to go-live in six weeks, and exactly who owes what.",
            "Implementation",
        )
        + page(
            f"""
  <p class="kicker">The plan</p>
  <h2 class="section-title">Six weeks,
if you hit your dates.</h2>
  <figure class="fig">{timeline}{phase_row}
  <p class="fig-caption">The critical path runs through what you provide, not what we build.
  DNS access and payment-gateway credentials are the two items that most often slip a go-live.</p>
  </figure>

  {rows_a}
""",
            "01",
        )
        + page(
            f"""
  <p class="kicker">The plan · continued</p>
  <h2 class="section-title">Weeks 3 to 5.</h2>
  {rows_b}

  <div class="rule-block mt-8">
    <span class="rule-label">What most often slips</span>
    DNS access and payment-gateway credentials. Both sit on your side, both are quick, and both
    block go-live if they arrive in week five.
  </div>
""",
            "02",
        )
        + page(
            f"""
  <p class="kicker">Before you buy</p>
  <h2 class="section-title">The 35-minute
walkthrough.</h2>
  <p class="lede">This is the demo we run. It is here so you know what to expect, and so your
  team can decide who should be in the room.</p>
  {demo}

  <div class="rule-block mt-8">
    <span class="rule-label">The moment that matters</span>
    At the five-minute mark we change a brand colour in the admin panel and reload the student
    portal in your colours. Everything else in the demo is detail; that is the product.
  </div>

  <div class="closing mt-8">
    <h3>Book the walkthrough.</h3>
    <p>Bring whoever runs admissions and whoever runs delivery. Forty minutes.</p>
    <div class="cta">{e(C.COMPANY['email'])} · {e(C.COMPANY['phone'])}</div>
  </div>
""",
            "02",
        )
    )
    render("06_BrowseJobs_Whitelabel_Onboarding_Plan.pdf", "Onboarding Plan", pages)


# ----------------------------------------------------------------------------
# 07 · commercial framework
# ----------------------------------------------------------------------------


def doc_commercial() -> None:
    clauses = join(
        f'<div class="clause"><p class="clause-name">{e(c["clause"])}</p>'
        f'<p class="clause-pos">{e(c["position"])}</p>'
        f'<p class="clause-decide"><span>Decide</span> {e(c["decide"])}</p></div>'
        for c in C.TERM_SHEET
    )
    pages = (
        cover(
            "07",
            "Commercial\nFramework",
            "A term sheet for counsel to draft from — not an executable agreement.",
            "Internal & counsel only",
        )
        + page(
            f"""
  <div class="promise-card never">
    <h3>Read this first</h3>
    <p class="body">{e(C.LEGAL_WARNING)}</p>
  </div>

  <p class="kicker mt-8">Term sheet</p>
  <h2 class="section-title">Thirteen positions
to settle.</h2>
  <p class="lede">Each clause states the position we suggest taking, and the decision the
  business must make before a lawyer can draft it.</p>

  {clauses}
""",
            "01",
        )
        + page(
            f"""
  <p class="kicker">The contract set</p>
  <h2 class="section-title">What counsel
should produce.</h2>
  {table([
      ("Master Subscription Agreement", "The umbrella terms — licence, IP, liability, term, governing law."),
      ("Order Form", "The commercial schedule: tier, fees, allowances, term. The only document with numbers."),
      ("Data Processing Agreement", "Processor obligations, sub-processors, security measures, breach notification."),
      ("Service Level Agreement", "Uptime target, severity definitions, response times, credits."),
      ("Acceptable Use Policy", "What the partner may not do with platform access or learner data."),
      ("Exit & Portability Schedule", "Export format, delivery window, deletion timetable."),
      ("Trademark Licence", "Their marks to us for the term; attribution rights back to them."),
  ], ["Document", "What it governs"])}

  <div class="rule-block coach mt-8">
    <span class="rule-label">Sequence that saves money</span>
    Settle every DECIDE above internally first, then brief counsel once. Drafting against
    unresolved commercial positions is how a straightforward SaaS agreement becomes three rounds
    of billable redlines.
  </div>
""",
            "02",
        )
    )
    render("07_BrowseJobs_Whitelabel_Commercial_Framework.pdf", "Commercial Framework", pages)


def main() -> None:
    doc_overview()
    doc_capabilities()
    doc_pricing()
    doc_technical()
    doc_onboarding()
    doc_commercial()
    print(f"\nPartner pack written to {OUT_DIR}")


if __name__ == "__main__":
    main()
