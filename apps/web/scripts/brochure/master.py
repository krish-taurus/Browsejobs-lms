#!/usr/bin/env python3
"""Render the BrowseJobs master brochure — the one you print and hand to anyone.

Visual-first by design: the argument is carried by meters, bars, a staircase, a
pipeline flow and a timeline, with prose cut back to captions. Every figure is
drawn from published BrowseJobs data via ``export-content.mts``, so this
document cannot disagree with the website or the course brochures.

Charts live in ``charts.py`` and follow the house spec — a single-hue ordinal
ramp for magnitude, green/amber/red left to their brand meanings, and every
value directly labelled because print has no hover layer.

Usage::

    node scripts/brochure/export-content.mts > scripts/brochure/.content.json
    python3 scripts/brochure/master.py

Both steps are wrapped by ``npm run brochures`` in apps/web.
"""

from __future__ import annotations

import json
import sys

from weasyprint import CSS, HTML

import charts
from generate import (
    CONTENT,
    EDITION,
    HERE,
    OUT_DIR,
    PLATFORM_LINE,
    STYLESHEET,
    e,
    fmt_stat,
    join,
    rupees,
)

TARGET = "BrowseJobs_Brochure_2026.pdf"


# ----------------------------------------------------------------------------
# small builders
# ----------------------------------------------------------------------------


MARK_SVG = (
    '<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">'
    '<rect width="64" height="64" rx="14" fill="#0A1220"/>'
    '<rect x="13.5" y="36" width="5.5" height="13" rx="2.75" fill="#1B6DF0" opacity="0.55"/>'
    '<rect x="22.5" y="30" width="5.5" height="19" rx="2.75" fill="#1B6DF0" opacity="0.75"/>'
    '<rect x="31.5" y="24" width="5.5" height="25" rx="2.75" fill="#1B6DF0"/>'
    '<rect x="41.75" y="20" width="5.5" height="29" rx="2.75" fill="#FFFFFF"/>'
    '<path d="M38.5 20.5 L44.5 13.5 L50.5 20.5" stroke="#FFFFFF" stroke-width="5.5" '
    'stroke-linecap="round" stroke-linejoin="round"/>'
    "</svg>"
)


def figure(svg: str, caption: str = "", *, label: str = "") -> str:
    """A chart with its label above and its caption below — the figure contract."""
    head = f'<p class="fig-label">{e(label)}</p>' if label else ""
    foot = f'<p class="fig-caption">{e(caption)}</p>' if caption else ""
    return f'<figure class="fig">{head}{svg}{foot}</figure>'


def kpi(value: str, label: str) -> str:
    return (
        f'<div class="kpi"><div class="kpi-value mono">{e(value)}</div>'
        f'<div class="kpi-label">{e(label)}</div></div>'
    )


def spread_mark() -> str:
    """The mark, placed inside a bleed spread.

    The document's `position: fixed` mark is laid out against the paper page's
    content box; a bleed page has no margins, so that copy falls off the sheet.
    A spread therefore prints its own, and the logo appears on every page.
    """
    return f'<div class="spread-mark" aria-hidden="true">{MARK_SVG}</div>'


def ghost(numeral: str) -> str:
    """The oversized, near-transparent section numeral."""
    return f'<div class="ghost" aria-hidden="true">{e(numeral)}</div>'


def spread_foot(numeral: str, title: str) -> str:
    """A bleed spread carries no running footer, so it prints its own."""
    return (
        f'<div class="spread-foot"><span>{e(title)}</span>'
        f'<span class="no">{e(numeral)}</span></div>'
    )


# ----------------------------------------------------------------------------
# pages
# ----------------------------------------------------------------------------


def cover(master: dict, shared: dict) -> str:
    headline = join(f"<span>{e(line)}</span>" for line in master["cover"]["headline"])
    stats = join(
        f'<div><div class="value">{e(fmt_stat(s))}</div>'
        f'<div class="label">{e(s["label"])}</div></div>'
        for s in shared["stats"]
    )
    return f"""
<section class="page bleed cover-spread">
  <div class="cover-mark">{MARK_SVG}<span>BrowseJobs<i>.ai</i></span></div>
  <p class="platform-line">{e(PLATFORM_LINE)}</p>
  <h1 class="stack">{headline}</h1>
  <p class="audience">{e(master['cover']['audienceLine'])}</p>
  {charts.breakout_motif()}
  <div class="cover-base">
    <div class="stat-band">{stats}</div>
    <p class="meta">{e(master['cover']['meta'])}</p>
    <p class="promise">{e(shared['footerLine'])}</p>
  </div>
</section>
"""


def receipts_page(master: dict, shared: dict) -> str:
    """A dark spread: two meters, two headline figures, the engine, the verbs."""
    engine = shared["syllabusEngine"]
    manifesto = master["manifesto"]
    verbs = join(
        f'<div class="verb"><p class="verb-title">{e(v["title"])}</p>'
        f'<p class="verb-body">{e(v["body"])}</p></div>'
        for v in manifesto["verbs"]
    )
    steps = join(
        f'<div class="engine-step"><span class="n mono">{i}</span>'
        f'<div><p class="s-title">{e(s["step"].split("—")[-1].strip())}</p>'
        f'<p class="s-body">{e(s["body"])}</p></div></div>'
        for i, s in enumerate(engine["steps"], start=1)
    )
    rings = charts.meter_ring(
        0.98, "98%", "of candidates who attend interviews get placed", theme=charts.DARK
    ) + charts.meter_ring(
        0.90, "~90%", "of interview questions come from our material", theme=charts.DARK
    )
    return f"""
<section class="page bleed spread">
  {spread_mark()}
  {ghost("01")}
  <p class="kicker">The receipts</p>
  <h2 class="section-title jumbo">We can show you
the working.</h2>

  <div class="ring-row mt-8">{rings}
    <div class="ring-side">
      {kpi("50/day", "Real interviews monitored by our AI")}
      {kpi("1 year", "Unlimited live batches & recordings")}
    </div>
  </div>
  <p class="disclaimer">*{e(shared['disclaimer'])}</p>

  <p class="statement mt-8">{e(manifesto['line1'])} {e(manifesto['line2'])}
  <span>{e(manifesto['line3'])}</span></p>
  <div class="verb-row mt-6">{verbs}</div>

  <div class="engine-panel mt-8">
    <p class="kicker">{e(engine['kicker'])}</p>
    <h3 class="mt-2 xl">{e(engine['headline'])}</h3>
    <div class="engine-strip">{steps}</div>
  </div>

  {spread_foot("01", "The receipts")}
</section>
"""


def path_page(master: dict, shared: dict) -> str:
    """The funnel spine, the six months, and how a profile reaches employers."""
    channels = join(
        f'<div class="card"><p class="kicker">{e(c["kicker"])}</p>'
        f'<h3 class="sm mt-2">{e(c["title"])}</h3>'
        f'<p class="body mt-2">{e(c["body"])}</p></div>'
        for c in shared["placementChannels"]
    )
    short = {
        "01": ("Counselling", "+ written report"),
        "02": ("Masterclass", "live, 45 min"),
        "03": ("Bootcamp", "7 hours of Python"),
        "04": ("Registration", "₹30,000 · EMI 3×"),
    }
    rungs = [
        {
            "step": r["step"],
            "free": r["free"],
            "short": short[r["step"]][0],
            "note": short[r["step"]][1],
        }
        for r in shared["freeLadder"]
    ]
    journey = [dict(p) for p in master["journey"]]
    phase_row = (
        '<div class="phase-row">'
        + join(
            f'<div class="phase"><p class="phase-name">{e(p["label"])}</p>'
            f'<p class="phase-detail">{e(p["detail"])}</p></div>'
            for p in journey
        )
        + "</div>"
    )
    return f"""
<section class="page">
  {ghost("02")}
  <p class="kicker free">Three free steps before you pay a rupee</p>
  <h2 class="section-title">{e(master['studentBanner']['headline'])}</h2>

  {figure(charts.free_ladder(rungs),
          "You judge the teaching before you pay for it. The 30-day money-back guarantee "
          "starts at step 04 — any reason, full refund, written into your agreement.",
          label="The path")}

  {figure(charts.journey_timeline(journey) + phase_row,
          "Access runs a full year — longer than the programme itself, so you can sit any "
          "later batch again at no cost.",
          label="Six months, month by month")}

  <p class="label mt-10">And then you are presented — three channels, by our team</p>
  <div class="grid three mt-4">{channels}</div>

  <div class="rule-block mt-6">
    <span class="rule-label">Readiness is decided on data</span>
    We send you to interviews when your mock scores say you are ready — not when a sales target
    says so.
  </div>
</section>
"""


def features_page(shared: dict) -> str:
    """Thirteen tools, one line each — a grid, not paragraphs."""
    situations = join(
        f'<div class="card"><p class="kicker">{e(c["kicker"])}</p>'
        f'<p class="body mt-2">{e(c["body"])}</p></div>'
        for c in shared["situationCards"]
    )
    groups = join(
        f'<div class="f-group"><p class="f-group-label">{e(g["group"])}</p>'
        + join(
            f'<div class="f-item"><p class="f-name">{e(i["name"])}</p>'
            f'<p class="f-short">{e(i.get("short", ""))}</p></div>'
            for i in g["items"]
        )
        + "</div>"
        for g in shared["platformFeatures"]
    )
    return f"""
<section class="page">
  {ghost("03")}
  <p class="kicker">Everything included</p>
  <h2 class="section-title">One fee. The whole machine.</h2>
  <p class="lede">No add-on pricing for the things that get you hired.</p>
  <div class="grid three mt-8">{groups}</div>

  <p class="label mt-10">Whatever you're starting from</p>
  <div class="grid three mt-4">{situations}</div>
</section>
"""


def tracks_page(master: dict) -> str:
    live = [t for t in master["tracks"] if t["live"]]
    soon = [t for t in master["tracks"] if not t["live"]]

    rows = [
        {
            "name": t["name"],
            "modules": t["moduleCount"],
            "projects": int(t["projectsLabel"].split()[0]),
        }
        for t in live
    ]
    series = [
        {"key": "modules", "label": "Modules", "colour": charts.RAMP[3]},
        {"key": "projects", "label": "CV-ready projects", "colour": charts.RAMP[0]},
    ]

    cards = join(
        f'<div class="track-chip">'
        f'<span class="tc-code mono">{e(t["code"])}</span>'
        f'<span class="tc-name">{e(t["name"])}</span>'
        f'<span class="tc-tools">{e(" · ".join(t["tools"][:4]))}</span>'
        f"</div>"
        for t in live
    )
    waitlist = " · ".join(t["name"] for t in soon)

    return f"""
<section class="page">
  {ghost("04")}
  <p class="kicker">The programs</p>
  <h2 class="section-title">Five live tracks.
Every one rebuilt monthly.</h2>

  {figure(charts.grouped_bars(rows, series),
          "Every track now carries an applied agentic-AI module and an AI project you can walk "
          "an interviewer through.",
          label="What each track contains")}

  <div class="track-chips mt-6">{cards}</div>

  <p class="spine-line mt-6"><span>Every track, the same spine —</span> live instructor-led classes
  with recordings · 1 year unlimited access · CV-ready projects you deploy · weekly AI-analysed
  mocks · a question bank rebuilt monthly · placement support until you accept an offer.</p>

  <p class="waitlist-line mt-4"><span>Opening next</span> {e(waitlist)} — register to be told
  when a batch opens.</p>
</section>
"""


def market_page(panels: dict) -> str:
    """Where the market is, in one chart and four figures."""
    ladder = panels["devops-cloud"]
    de = panels["data-engineering"]
    rows = [
        {"role": r["role"], "low": r["low"], "high": r["high"], "label": r["range"]}
        for r in ladder["rungs"]
    ]
    figures = join(kpi(s["value"], s["label"]) for s in de["stats"])
    da = panels["data-analytics"]
    flow_steps = [
        {"short": s["role"].replace("Fresher → ", "")} for s in da["steps"]
    ]
    flow_row = (
        '<div class="flow-row">'
        + join(f'<div class="flow-cell">{e(s["what"])}</div>' for s in da["steps"])
        + "</div>"
    )
    return f"""
<section class="page bleed spread">
  {spread_mark()}
  {ghost("05")}
  <p class="kicker">The market</p>
  <h2 class="section-title">We only run tracks
where hiring is real.</h2>

  {figure(charts.range_bars(rows, theme=charts.DARK), ladder["note"], label=ladder["label"])}

  <p class="label mt-8">Why Data Engineering leads the demand</p>
  <div class="kpi-row mt-4">{figures}</div>
  <p class="disclaimer">{e(de["note"])}</p>

  {figure(charts.career_flow(flow_steps, theme=charts.DARK) + flow_row,
          "The ladder runs long — analysts move into strategy and leadership rather than "
          "topping out.",
          label=f"{da['label']} · Data Analytics")}

  {spread_foot("05", "The market")}
</section>
"""


def fees_page(shared: dict) -> str:
    fees = shared["fees"]
    placement = fees["placement"]
    example_lpa = 12
    three_months = example_lpa * 100_000 // 4
    balance = three_months - placement["adjusted"]
    segments = [
        {
            "value": placement["adjusted"],
            "label": "Before you're hired",
            "amount": rupees(placement["adjusted"]) + " registration",
            "colour": charts.RAMP[3],
        },
        {
            "value": balance,
            "label": "Only after you accept an offer",
            "amount": rupees(balance) + " · 6 EMIs from your new salary",
            "colour": charts.RAMP[0],
        },
    ]
    split_key = (
        '<div class="split-key">'
        + join(
            f'<div class="sk"><span class="sk-dot" style="background:{s["colour"]}"></span>'
            f'<span class="sk-label">{e(s["label"])}</span>'
            f'<span class="sk-amount mono">{e(s["amount"])}</span></div>'
            for s in segments
        )
        + "</div>"
    )
    kept = join(f"<li>{e(p)}</li>" for p in shared["promisesKept"])
    never = join(f"<li>{e(p)}</li>" for p in shared["promisesNever"])
    return f"""
<section class="page">
  {ghost("06")}
  <p class="kicker">The fee structure</p>
  <h2 class="section-title">Two fees. Both in writing.
One only after you're hired.</h2>

  {figure(charts.split_bar(segments) + split_key,
          f"Worked example on a ₹{example_lpa} LPA offer. The placement fee is your first "
          f"{placement['monthsOfCtc']} months' CTC with the registration adjusted inside it, so "
          "most of what you pay comes out of a salary you did not have before.",
          label="What you pay, and when")}

  <div class="grid two mt-8">
    <div class="promise-card kept">
      <h3>Promised in writing</h3>
      <ul class="check">{kept}</ul>
    </div>
    <div class="promise-card never">
      <h3>Never said here</h3>
      <ul class="cross">{never}</ul>
    </div>
  </div>

  <div class="guarantee mt-6">
    <div class="g-badge mono">{fees['guaranteeDays']}</div>
    <div>
      <h3 class="sm">Day money-back guarantee</h3>
      <p class="body mt-2">Any reason, or no reason — a full refund in your first
      {fees['guaranteeDays']} days of the programme. No forms designed to make you give up.
      Written into your agreement.</p>
    </div>
  </div>
</section>
"""


def employer_page(master: dict) -> str:
    employer = master["employer"]
    stages = [
        {"step": s["step"], "kicker": s["kicker"], "title": s["title"].rstrip(".")}
        for s in employer["pipeline"]
    ]
    before = join(f"<li>{e(x)}</li>" for x in employer["before"])
    after = join(f"<li>{e(x)}</li>" for x in employer["after"])
    return f"""
<section class="page bleed spread">
  {spread_mark()}
  {ghost("07")}
  <p class="kicker">For employers</p>
  <h2 class="section-title jumbo">{e(master['employerBanner']['headline'])}</h2>

  {figure(charts.pipeline_flow(stages, theme=charts.DARK),
          "Automation can advance, unlock or nudge — it can never reject terminally, and it never "
          "releases an offer. Both take an explicit human action.",
          label="The hiring pipeline, end to end")}

  <div class="grid two mt-6">
    <div class="promise-card never">
      <h3>Before</h3>
      <ul class="cross">{before}</ul>
    </div>
    <div class="promise-card kept">
      <h3>With BrowseJobs</h3>
      <ul class="check">{after}</ul>
    </div>
  </div>
  <p class="disclaimer">{e(employer['disclaimer'])}</p>

  {spread_foot("07", "For employers")}
</section>
"""


def employer_models_page(master: dict) -> str:
    employer = master["employer"]
    pricing = employer["pricing"]
    models = join(
        f'<div class="card"><p class="kicker">{e(m["label"])}</p>'
        f'<h3 class="sm mt-2">{e(m["title"])}</h3>'
        f'<ul class="tight mt-2">{join(f"<li>{e(pt)}</li>" for pt in m["points"])}</ul></div>'
        for m in employer["deliveryModels"]
    )
    options = join(
        f'<tr><td class="key">{e(o["title"])}</td>'
        f'<td class="amount">{e(o["headline"])}</td></tr>'
        for o in pricing["paid"]["options"]
    )
    roadmap = join(
        f'<li><strong>{e(r["title"])}</strong> — {e(r["body"])}</li>'
        for r in employer["roadmap"]
    )
    cases = join(
        f'<div class="card"><p class="kicker">{e(c["title"])}</p>'
        f'<p class="body mt-2">{e(c["scenario"])}</p>'
        f'<p class="case-out mt-2">{e(c["outcome"])}</p></div>'
        for c in employer["useCases"]
    )
    return f"""
<section class="page">
  {ghost("08")}
  <p class="kicker">For employers</p>
  <h2 class="section-title">Two ways to work with us.</h2>

  <div class="grid two mt-8">{models}</div>

  <p class="label mt-8">What it costs</p>
  <table class="mt-2">
    <tr>
      <td class="key">{e(pricing['free']['label'])} — full pipeline, no card, no lock-in</td>
      <td class="amount verify">{e(pricing['free']['price'])}</td>
    </tr>
    {options}
  </table>
  <p class="disclaimer">Nothing is charged without a written agreement first.</p>

  <p class="label mt-8">What this looks like in practice</p>
  <div class="grid three mt-4">{cases}</div>

  <div class="rule-block coach mt-6">
    <span class="rule-label">On the roadmap — not available yet</span>
    <ul class="tight mt-2">{roadmap}</ul>
  </div>
</section>
"""


def feedback_page(shared: dict) -> str:
    testimonials = join(
        f'<div class="card testimonial"><span class="stars">★★★★★</span>'
        f'<span class="track">{e(t["track"])}</span>'
        f"<p>“{e(t['body'])}”</p>"
        f'<div class="author">{e(t["author"])}</div></div>'
        for t in shared["testimonials"]
    )
    aggregates = join(kpi(fmt_stat(a), a["label"]) for a in shared["reviewAggregates"])
    checks = join(
        f'<div class="check-card"><p class="kicker">{i:02d} · {e(c["time"])}</p>'
        f'<p class="f-name mt-2">{e(c["title"])}</p></div>'
        for i, c in enumerate(shared["verifyChecks"], start=1)
    )
    return f"""
<section class="page">
  {ghost("09")}
  <p class="kicker">In their words</p>
  <h2 class="section-title">The people who did it,
on the record.</h2>

  <div class="kpi-row mt-8">{aggregates}</div>
  <p class="disclaimer">*{e(shared['disclaimer'])}</p>

  <div class="grid two mt-8">{testimonials}</div>

  <p class="label mt-8">Don't trust us. Verify us — fifteen minutes on Naukri</p>
  <div class="grid three mt-4">{checks}</div>
</section>
"""


def closing_page(master: dict, shared: dict) -> str:
    contact = shared["contact"]
    closing = master["closing"]
    return f"""
<section class="page bleed spread">
  {spread_mark()}
  {ghost("10")}
  <p class="kicker">Start here</p>
  <h2 class="section-title jumbo">Three free steps.
Then you decide.</h2>

  <div class="closing mt-8">
    <h3>{e(closing['student']['title'])}</h3>
    <p>{e(closing['student']['body'])}</p>
    <div class="cta">Call or WhatsApp {e(contact['phone'])}</div>
  </div>

  <div class="closing light mt-6">
    <h3>{e(closing['employer']['title'])}</h3>
    <p>{e(closing['employer']['body'])}</p>
    <div class="cta">{e(contact['email'])} · browsejobs.ai/employers</div>
  </div>

  <table class="mt-10">
    <tr><td class="key">Phone &amp; WhatsApp</td><td class="amount">{e(contact['phone'])}</td></tr>
    <tr><td class="key">Email</td><td class="amount">{e(contact['email'])}</td></tr>
    <tr><td class="key">Office</td><td class="amount">{e(contact['address'])}</td></tr>
    <tr><td class="key">Hours</td><td class="amount">{e(contact['hours'])}</td></tr>
  </table>

  <p class="promise-line mt-8">{e(shared['footerLine'])}</p>
  <p class="colophon">{e(shared['entityLine'])}</p>

  {charts.breakout_motif()}
  {spread_foot("10", "Start here")}
</section>
"""


# ----------------------------------------------------------------------------
# document
# ----------------------------------------------------------------------------


def document(master: dict, shared: dict, panels: dict) -> str:
    """Ten pages, alternating ink spreads and paper pages.

    The mark is one position:fixed element, so it repeats on every page — the
    ink tile reads on paper and dissolves into a spread, leaving the bars and
    the breakout arrow floating.
    """
    pages = join(
        [
            cover(master, shared),
            receipts_page(master, shared),
            path_page(master, shared),
            features_page(shared),
            tracks_page(master),
            market_page(panels),
            fees_page(shared),
            employer_page(master),
            employer_models_page(master),
            feedback_page(shared),
            closing_page(master, shared),
        ]
    )
    return f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>BrowseJobs</title>
</head>
<body class="master">
  <div class="mark" aria-hidden="true">{MARK_SVG}</div>
  {pages}
</body>
</html>
"""


def main() -> None:
    if not CONTENT.exists():
        sys.exit(
            f"{CONTENT.name} not found — run:\n"
            "  node scripts/brochure/export-content.mts > scripts/brochure/.content.json"
        )

    payload = json.loads(CONTENT.read_text(encoding="utf-8"))
    panels = {c["slug"]: c["careerPanel"] for c in payload["courses"] if c["careerPanel"]}
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    target = OUT_DIR / TARGET

    HTML(
        string=document(payload["master"], payload["shared"], panels), base_url=str(HERE)
    ).write_pdf(target, stylesheets=[CSS(filename=str(STYLESHEET))])

    print(f"  {target.name}  ({target.stat().st_size / 1024:.0f} KB)")


if __name__ == "__main__":
    main()
