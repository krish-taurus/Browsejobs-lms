#!/usr/bin/env python3
"""Render the BrowseJobs master brochure — the one you print and hand to anyone.

The per-course brochures go deep on one syllabus. This one covers the whole
platform in a single document: what a student gets, every track, what an
employer gets, the hiring process, the fees and the feedback. It shares its
content pipeline, brand stylesheet and helpers with ``generate.py``, so the
two can never disagree about a module count or a fee.

Usage::

    node scripts/brochure/export-content.mts > scripts/brochure/.content.json
    python3 scripts/brochure/master.py

Both steps are wrapped by ``npm run brochures`` in apps/web.
"""

from __future__ import annotations

import json
import sys

from weasyprint import CSS, HTML

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
    pills,
    rupees,
    stat_band,
)

TARGET = "BrowseJobs_Brochure_2026.pdf"


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
<section class="cover master">
  <div class="rail-mark"><span>BrowseJobs · {e(EDITION)}</span></div>
  <div class="wordmark">BrowseJobs<span class="dot-ai">.ai</span></div>
  <div class="platform-line">{e(PLATFORM_LINE)}</div>
  <div class="spacer"></div>
  <h1 class="stack">{headline}</h1>
  <p class="audience">{e(master['cover']['audienceLine'])}</p>
  <p class="hero">{e(master['cover']['subline'])}</p>
  <div class="spacer"></div>
  <div class="stat-band">{stats}</div>
  <div class="meta">{e(master['cover']['meta'])}</div>
  <div class="promise">{e(shared['footerLine'])}</div>
</section>
"""


def manifesto_page(master: dict, shared: dict) -> str:
    m = master["manifesto"]
    verbs = join(
        f'<div class="card"><h3 class="sm">{e(v["title"])}</h3>'
        f'<p class="body mt-2">{e(v["body"])}</p></div>'
        for v in m["verbs"]
    )
    engine = shared["syllabusEngine"]
    steps = join(
        f'<div><div class="step">{e(s["step"])}</div><p>{e(s["body"])}</p></div>'
        for s in engine["steps"]
    )
    return f"""
<section class="page">
  <h2 class="section-title">{e(m['line1'])}
{e(m['line2'])}</h2>
  <p class="statement mt-4">{e(m['line3'])}</p>
  <div class="grid four mt-8">{verbs}</div>

  <div class="panel ink mt-10">
    <p class="kicker on-ink">{e(engine['kicker'])}</p>
    <h3 class="mt-2 xl">{e(engine['headline'])}</h3>
    <div class="engine-steps">{steps}</div>
  </div>

  <div class="mt-8">{stat_band(shared['stats'], shared['disclaimer'])}</div>
</section>
"""


def student_path_page(master: dict, shared: dict) -> str:
    banner = master["studentBanner"]
    ladder = join(
        f'<div class="{"free" if r["free"] else "paid"}">'
        f'<div class="rung-label">{"Free · " if r["free"] else "Step "}{e(r["step"])}</div>'
        f"<h3>{e(r['title'])}</h3><p>{e(r['body'])}</p></div>"
        for r in shared["freeLadder"]
    )
    situations = join(
        f'<div class="card"><p class="kicker">{e(c["kicker"])}</p>'
        f'<p class="body mt-2">{e(c["body"])}</p></div>'
        for c in shared["situationCards"]
    )
    return f"""
<section class="page">
  <p class="kicker">{e(banner['kicker'])}</p>
  <h2 class="section-title">{e(banner['headline'])}</h2>

  <p class="kicker free mt-8">Your path — everything is free until step 04</p>
  <div class="ladder mt-4">{ladder}</div>

  <p class="label mt-10">Whatever you're starting from</p>
  <div class="grid three mt-4">{situations}</div>

  <div class="rule-block mt-8">
    <span class="rule-label">Readiness is decided on data</span>
    We send you to interviews when your mock scores say you're ready — not when a sales target
    says so. That is precisely why our attend-to-place rate is what it is.
  </div>
</section>
"""


def features_page(shared: dict) -> str:
    groups = join(
        f'<div><p class="label">{e(g["group"])}</p>'
        + join(
            f'<div class="feature"><h3 class="sm">{e(i["name"])}</h3>'
            f'<p class="body mt-2">{e(i["body"])}</p></div>'
            for i in g["items"]
        )
        + "</div>"
        for g in shared["platformFeatures"]
    )
    return f"""
<section class="page">
  <h2 class="section-title">One fee.
The whole machine.</h2>
  <p class="lede">No add-on pricing for the things that get you hired. Every tool below ships
  with every track.</p>
  <div class="grid three mt-8">{groups}</div>
</section>
"""


def tracks_page(master: dict) -> str:
    live = [t for t in master["tracks"] if t["live"]]
    soon = [t for t in master["tracks"] if not t["live"]]

    def card(track: dict) -> str:
        meta = " · ".join(
            filter(
                None,
                [
                    track["duration"],
                    f"{track['moduleCount']} modules" if track["moduleCount"] else None,
                    track["projectsLabel"],
                ],
            )
        )
        ai = (
            f'<p class="ai-flag">Includes “{e(track["aiModule"])}”</p>'
            if track["aiModule"]
            else ""
        )
        return (
            f'<div class="track-card">'
            f'<div class="track-code">{e(track["code"])}</div>'
            f'<h3>{e(track["name"])}</h3>'
            f'<p class="body mt-2">{e(track["tagline"])}</p>'
            f'<p class="track-meta mono">{e(meta)}</p>'
            f'<div class="pills mt-4">{pills(track["tools"])}</div>'
            f"{ai}"
            f"</div>"
        )

    # The five live cards leave one empty cell in the 2-up grid; the waitlist
    # panel fills it, which also stops its heading orphaning at the page foot.
    waitlist = (
        '<div class="track-card waitlist"><p class="kicker">Opening next</p>'
        + join(
            f'<div class="waitlist-row"><span class="name">{e(t["name"])}</span>'
            f'<span class="tag">Waitlist</span></div>'
            for t in soon
        )
        + '<p class="ai-flag">Register your interest and we will tell you when a batch opens.</p>'
        + "</div>"
    )

    return f"""
<section class="page">
  <p class="kicker">The programs</p>
  <h2 class="section-title">Five live tracks.
Every one rebuilt monthly.</h2>
  <p class="lede">Only five — because demand decides what we teach. We run tracks only where the
  market is actively hiring, and every one now carries an applied agentic-AI module.</p>

  <p class="spine-line mt-4"><span>Every track, the same spine —</span> live instructor-led classes
  with recordings · 1 year unlimited access · CV-ready projects you deploy · weekly AI-analysed
  mocks · a question bank rebuilt monthly · placement support until you accept an offer.</p>

  <div class="grid two mt-6">{join(card(t) for t in live)}{waitlist}</div>
</section>
"""


def fees_page(shared: dict) -> str:
    fees = shared["fees"]
    emi = fees["registrationEmi"]
    placement = fees["placement"]
    example_lpa = 12
    three_months = example_lpa * 100_000 // 4
    balance = three_months - placement["adjusted"]
    return f"""
<section class="page">
  <p class="kicker">The fee structure</p>
  <h2 class="section-title">Two fees. Both in writing.
One only after you're hired.</h2>

  <table class="mt-8">
    <tr>
      <td class="key">Registration (payable only after the free masterclass + bootcamp)</td>
      <td class="amount">{e(rupees(fees['registration']))}</td>
    </tr>
    <tr>
      <td class="key">EMI option for registration</td>
      <td class="amount">{emi['months']} × {e(rupees(emi['amount']))}</td>
    </tr>
    <tr>
      <td class="key">Placement fee — due only after you accept an offer</td>
      <td class="amount">First {placement['monthsOfCtc']} months' CTC</td>
    </tr>
    <tr>
      <td class="key">Paid as</td>
      <td class="amount">{placement['emis']} monthly EMIs from your new salary</td>
    </tr>
    <tr>
      <td class="key">Your {e(rupees(placement['adjusted']))} registration</td>
      <td class="amount">Adjusted inside the placement fee</td>
    </tr>
  </table>

  <div class="rule-block mt-6">
    <span class="rule-label">Worked example · ₹{example_lpa} LPA offer</span>
    3 months' CTC = {e(rupees(three_months))} − {e(rupees(placement['adjusted']))} already paid =
    {e(rupees(balance))} in {placement['emis']} EMIs — paid from a salary you didn't have before.
    We only earn properly when you do.
  </div>

  <div class="grid two mt-8">
    <div class="promise-card kept">
      <h3>What we promise — in writing</h3>
      <ul class="check">{join(f"<li>{e(p)}</li>" for p in shared["promisesKept"])}</ul>
    </div>
    <div class="promise-card never">
      <h3>What we will never tell you</h3>
      <ul class="cross">{join(f"<li>{e(p)}</li>" for p in shared["promisesNever"])}</ul>
    </div>
  </div>
</section>
"""


def stage_card(stage: dict) -> str:
    return (
        f'<div class="stage">'
        f'<div class="eyebrow">Step {e(stage["step"])} · {e(stage["kicker"])}</div>'
        f'<h3 class="sm mt-2">{e(stage["title"])}</h3>'
        f'<p class="body mt-2">{e(stage["body"])}</p>'
        f'<ul class="tight mt-2">{join(f"<li>{e(pt)}</li>" for pt in stage["points"][:3])}</ul>'
        f"</div>"
    )


def employer_pipeline_pages(master: dict) -> str:
    """The seven hiring stages, split 4 + 3 across two pages.

    A .grid cannot fragment, so anything that does not fit the first page jumps
    whole and leaves a gap. Splitting the list deliberately keeps both pages
    full, and the second carries the before/after comparison with it.
    """
    banner = master["employerBanner"]
    employer = master["employer"]
    pipeline = employer["pipeline"]
    before = join(f"<li>{e(x)}</li>" for x in employer["before"])
    after = join(f"<li>{e(x)}</li>" for x in employer["after"])

    return f"""
<section class="page">
  <p class="kicker">{e(banner['kicker'])}</p>
  <h2 class="section-title">{e(banner['headline'])}</h2>
  <p class="lede">{e(master['employerIntro'])}</p>

  <p class="label mt-8">The hiring process, end to end</p>
  <div class="grid two mt-4">{join(stage_card(s) for s in pipeline[:4])}</div>
</section>

<section class="page">
  <div class="grid two">{join(stage_card(s) for s in pipeline[4:])}</div>

  <h2 class="section-title mt-8">How hiring changes.</h2>
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
</section>
"""


def employer_models_page(master: dict) -> str:
    employer = master["employer"]
    models = join(
        f'<div class="card"><p class="kicker">{e(m["label"])}</p>'
        f'<h3 class="sm mt-2">{e(m["title"])}</h3>'
        f'<p class="body mt-2">{e(m["body"])}</p>'
        f'<ul class="tight mt-2">{join(f"<li>{e(pt)}</li>" for pt in m["points"])}</ul></div>'
        for m in employer["deliveryModels"]
    )
    pricing = employer["pricing"]
    options = join(
        f'<tr><td class="key">{e(o["title"])}</td>'
        f'<td class="amount">{e(o["headline"])}</td></tr>'
        for o in pricing["paid"]["options"]
    )
    roadmap = join(
        f'<li><strong>{e(r["title"])}</strong> — {e(r["body"])}</li>'
        for r in employer["roadmap"]
    )
    return f"""
<section class="page">
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
  <p class="disclaimer">Nothing is charged without a written agreement first. Rates are confirmed
  in writing before any engagement starts.</p>

  <div class="rule-block coach mt-6">
    <span class="rule-label">On the roadmap — not available yet</span>
    <ul class="tight mt-2">{roadmap}</ul>
  </div>
</section>
"""


def feedback_page(master: dict, shared: dict) -> str:
    testimonials = join(
        f'<div class="card testimonial"><span class="stars">★★★★★</span>'
        f'<span class="track">{e(t["track"])}</span>'
        f"<p>“{e(t['body'])}”</p>"
        f'<div class="author">{e(t["author"])}</div></div>'
        for t in shared["testimonials"]
    )
    aggregates = join(
        f'<div><div class="value">{e(fmt_stat(a))}</div>'
        f'<div class="label">{e(a["label"])}</div></div>'
        for a in shared["reviewAggregates"]
    )
    checks = join(
        f'<div class="card"><p class="kicker">Check {i:02d} · {e(c["time"])}</p>'
        f'<h3 class="mt-2 sm">{e(c["title"])}</h3>'
        f'<p class="body mt-2">{e(c["body"])}</p></div>'
        for i, c in enumerate(shared["verifyChecks"], start=1)
    )
    return f"""
<section class="page">
  <p class="kicker">In their words</p>
  <h2 class="section-title">The people who did it,
on the record.</h2>
  <div class="grid two mt-8">{testimonials}</div>

  <div class="stat-band mt-8">{aggregates}</div>
  <p class="disclaimer">*{e(shared['disclaimer'])}</p>

  <h2 class="section-title mt-10">Don't trust us. Verify us.</h2>
  <p class="lede">Fifteen minutes on Naukri is enough to check everything we claim. Here's how.</p>
  <div class="grid three mt-6">{checks}</div>
</section>
"""


def closing_page(master: dict, shared: dict) -> str:
    contact = shared["contact"]
    closing = master["closing"]
    return f"""
<section class="page">
  <h2 class="section-title">Start here.</h2>

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
</section>
"""


# ----------------------------------------------------------------------------
# document
# ----------------------------------------------------------------------------


def document(master: dict, shared: dict) -> str:
    pages = join(
        [
            cover(master, shared),
            manifesto_page(master, shared),
            student_path_page(master, shared),
            features_page(shared),
            tracks_page(master),
            fees_page(shared),
            employer_pipeline_pages(master),
            employer_models_page(master),
            feedback_page(master, shared),
            closing_page(master, shared),
        ]
    )
    return f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>BrowseJobs</title>
</head>
<body>
  <div class="rail"><span>BrowseJobs · Learn it. Prove it. Get hired. · {e(EDITION)}</span></div>
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
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    target = OUT_DIR / TARGET

    HTML(
        string=document(payload["master"], payload["shared"]), base_url=str(HERE)
    ).write_pdf(target, stylesheets=[CSS(filename=str(STYLESHEET))])

    print(f"  {target.name}  ({target.stat().st_size / 1024:.0f} KB)")


if __name__ == "__main__":
    main()
