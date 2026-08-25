#!/usr/bin/env python3
"""Render the BrowseJobs course brochures from the website's own content.

The 2026 brochures were one-off design files, so the PDFs and the site drifted
apart the moment a module changed. This renders them instead from
``src/content/courses.ts`` + ``src/content/brochure.ts`` + ``src/content/landing.ts``
(exported to JSON by ``export-content.mts``), so a syllabus edit reaches the
brochure on the next build and cannot silently disagree with the course page.

Usage::

    node scripts/brochure/export-content.mts > scripts/brochure/.content.json
    python3 scripts/brochure/generate.py

Both steps are wrapped by ``npm run brochures`` in apps/web.

Requires WeasyPrint (``pip install weasyprint``); the 2026 editions were also
rendered with WeasyPrint, so the output matches the established look.
"""

from __future__ import annotations

import html
import json
import sys
from pathlib import Path

try:
    from weasyprint import CSS, HTML
except ImportError:  # pragma: no cover - dependency guard
    sys.exit("WeasyPrint is required: pip install weasyprint")

HERE = Path(__file__).resolve().parent
CONTENT = HERE / ".content.json"
STYLESHEET = HERE / "brochure.css"
OUT_DIR = HERE.parents[3] / "docs" / "brochures"

EDITION = "2026 Edition"
PLATFORM_LINE = "The world's first AI-driven skilling & placement platform"


# ----------------------------------------------------------------------------
# helpers
# ----------------------------------------------------------------------------


def e(value: object) -> str:
    """Escape text for HTML.

    The 2026 brochures printed literal ``&amp;`` in module titles ("Pandas
    &amp; Data Wrangling") because content was escaped twice. Escaping happens
    here and only here.
    """
    return html.escape(str(value), quote=False)


def tag(name: str, inner: str, cls: str = "") -> str:
    attr = f' class="{cls}"' if cls else ""
    return f"<{name}{attr}>{inner}</{name}>"


def join(parts: list[str]) -> str:
    return "".join(parts)


def stat_band(stats: list[dict], disclaimer: str) -> str:
    """A 4-up mono stat band. The brand rules require the disclaimer directly
    beneath it, so the two are emitted together and never separately."""
    cells = join(
        f'<div><div class="value">{e(fmt_stat(s))}</div>'
        f'<div class="label">{e(s["label"])}</div></div>'
        for s in stats
    )
    return (
        f'<div class="stat-band">{cells}</div>'
        f'<p class="disclaimer">*{e(disclaimer)}</p>'
    )


def fmt_stat(stat: dict) -> str:
    """Format one stat-band figure.

    Thousands are grouped only when the stat carries a suffix, because a bare
    four-digit figure in this band is a year ("2013", the year the company was
    founded) and "2,013" reads as a count.
    """
    value = stat["value"]
    if isinstance(value, float) and value.is_integer():
        value = int(value)
    decimals = stat.get("decimals")
    suffix = stat.get("suffix", "")
    if decimals:
        value = f"{float(stat['value']):.{decimals}f}"
    elif isinstance(value, int) and value >= 1000 and suffix:
        value = f"{value:,}"
    return f"{stat.get('prefix', '')}{value}{suffix}"


def rupees(amount: int) -> str:
    """Indian digit grouping — ₹3,00,000, not ₹300,000."""
    digits = str(amount)
    if len(digits) <= 3:
        return f"₹{digits}"
    head, tail = digits[:-3], digits[-3:]
    groups = []
    while len(head) > 2:
        groups.insert(0, head[-2:])
        head = head[:-2]
    if head:
        groups.insert(0, head)
    return "₹" + ",".join(groups + [tail])


# ----------------------------------------------------------------------------
# pages
# ----------------------------------------------------------------------------


def cover(course: dict, shared: dict) -> str:
    rail = f"{course['name']} · Course brochure"
    stats = join(
        f'<div><div class="value">{e(fmt_stat(s))}</div>'
        f'<div class="label">{e(s["label"])}</div></div>'
        for s in shared["stats"]
    )
    meta = " · ".join([course["duration"], "Live online", "Bengaluru", EDITION])
    return f"""
<section class="cover">
  <div class="rail-mark"><span>{e(rail)}</span></div>
  <div class="wordmark">BrowseJobs<span class="dot-ai">.ai</span></div>
  <div class="platform-line">{e(PLATFORM_LINE)}</div>
  <div class="spacer"></div>
  <div class="program-kicker">Career program · {e(course['name'])}</div>
  <h1>{e(course['name'])}</h1>
  <p class="hero">{e(course['hero'])}</p>
  <div class="spacer"></div>
  <div class="stat-band">{stats}</div>
  <div class="meta">{e(meta)}</div>
  <div class="promise">{e(shared['footerLine'])}</div>
</section>
"""


def career_panel(panel: dict | None) -> str:
    if not panel:
        return ""
    head = (
        f'<h3 class="lg">{e(panel["heading"])}</h3>'
        f'<p class="lede">{e(panel["body"])}</p>'
    )

    if panel["kind"] == "stats":
        cells = join(
            f'<div><div class="value">{e(s["value"])}</div>'
            f'<div class="label">{e(s["label"])}</div></div>'
            for s in panel["stats"]
        )
        body = f'<div class="stat-band mt-6">{cells}</div>'
    elif panel["kind"] == "ladder":
        rows = join(
            f'<tr><td class="key">{e(r["role"])}</td>'
            f'<td class="amount">{e(r["range"])}</td></tr>'
            for r in panel["rungs"]
        )
        body = (
            f'<p class="label mt-6">{e(panel["label"])}</p>'
            f'<table class="mt-2">{rows}</table>'
        )
    else:  # flow
        rows = join(
            f'<tr><td class="key">{e(s["role"])}</td><td>{e(s["what"])}</td></tr>'
            for s in panel["steps"]
        )
        body = (
            f'<p class="label mt-6">{e(panel["label"])}</p>'
            f'<table class="mt-2">{rows}</table>'
        )

    note = panel.get("note")
    footnote = f'<p class="disclaimer">{e(note)}</p>' if note else ""
    return f'<div class="mt-10">{head}{body}{footnote}</div>'


def engine_page(course: dict, shared: dict) -> str:
    engine = shared["syllabusEngine"]
    steps = join(
        f'<div><div class="step">{e(s["step"])}</div><p>{e(s["body"])}</p></div>'
        for s in engine["steps"]
    )
    return f"""
<section class="page">
  <h2 class="section-title">{e(engine['title'])}</h2>
  <p class="lede">{e(engine['problem'])}</p>

  <div class="panel ink mt-8">
    <p class="kicker on-ink">{e(engine['kicker'])}</p>
    <h3 class="mt-2 xl">{e(engine['headline'])}</h3>
    <div class="engine-steps">{steps}</div>
  </div>

  <div class="mt-8">{stat_band(shared['stats'], shared['disclaimer'])}</div>

  {career_panel(course.get('careerPanel'))}
</section>
"""


def situation_page(course: dict, shared: dict) -> str:
    cards = join(
        f'<div class="card"><p class="kicker">{e(c["kicker"])}</p>'
        f'<p class="body mt-2">{e(c["body"])}</p></div>'
        for c in shared["situationCards"]
    )
    outcomes = join(f"<li>{e(o)}</li>" for o in course["outcomes"])
    roles = join(f'<span class="pill solid">{e(r)}</span>' for r in course["roles"])
    fq = shared["founderQuote"]
    return f"""
<section class="page">
  <h2 class="section-title">Built for your situation.
Judged on your outcomes.</h2>
  <div class="grid three mt-8">{cards}</div>

  <div class="grid two mt-10">
    <div>
      <p class="label">What you'll walk out able to do</p>
      <ul class="check mt-4">{outcomes}</ul>
    </div>
    <div>
      <p class="label">Roles this prepares you for</p>
      <div class="pills mt-4">{roles}</div>

      <p class="label mt-8">Program format</p>
      <table class="mt-2">
        <tr><td class="key">Duration</td><td class="amount">{e(course['duration'])}</td></tr>
        <tr><td class="key">Format</td><td class="amount">{e(course['format'])}</td></tr>
        <tr><td class="key">Access</td><td class="amount">{e(course['access'])}</td></tr>
        <tr><td class="key">Projects</td><td class="amount">{e(course['projectsLabel'])}</td></tr>
      </table>
    </div>
  </div>

  <div class="pull-quote mt-10">
    “{e(fq['quote'])}”
    <span class="attribution">{e(fq['attribution'])}</span>
  </div>
</section>
"""


def syllabus_page(course: dict) -> str:
    modules = course["modules"]

    def module_html(index: int, module: dict) -> str:
        topics = join(f"<li>{e(t)}</li>" for t in module["topics"])
        # Applied-AI modules carry the sky fill so the newest material is
        # findable at a glance without introducing a second accent colour.
        ai = " ai" if module.get("ai") else ""
        return (
            f'<div class="module{ai}">'
            f'<div class="no">Module {index:02d}</div>'
            f"<h3>{e(module['title'])}</h3>"
            f'<p class="hook">{e(module["hook"])}</p>'
            f"<ul>{topics}</ul>"
            f"</div>"
        )

    cards = join(module_html(i, m) for i, m in enumerate(modules, start=1))
    return f"""
<section class="page">
  <h2 class="section-title">The live syllabus.
Rebuilt monthly from real interviews.</h2>
  <p class="lede">{len(modules)} modules. Zero filler. Every topic below appeared in a monitored
  interview for this role — that is the only reason it is here.</p>
  <div class="modules">{cards}</div>
</section>
"""


def pills(labels: list[str], cls: str = "pill") -> str:
    return join(f'<span class="{cls}">{e(label)}</span>' for label in labels)


def tools_and_projects_page(course: dict, shared: dict) -> str:
    tools = pills(course["tools"])
    projects = join(
        f'<div class="card"><div class="eyebrow">Project {i:02d}</div>'
        f'<h3 class="mt-2">{e(p["title"])}</h3>'
        f'<p class="hook">{e(p["body"])}</p>'
        f'<div class="pills mt-4">{pills(p["points"])}</div>'
        f"</div>"
        for i, p in enumerate(course["projects"], start=1)
    )

    explore = ""
    if course.get("exploreLater"):
        items = join(f"<li>{e(i)}</li>" for i in course["exploreLater"]["items"])
        explore = f"""
  <div class="rule-block coach mt-8">
    <span class="rule-label">Coach note · explore later</span>
    {e(course['exploreLater']['note'])}
    <ul class="plain-list">{items}</ul>
  </div>"""

    return f"""
<section class="page">
  <h2 class="section-title">Real projects you'll build.
These go on your CV.</h2>
  <p class="lede">Not toy exercises — industry-grade work you deliver, deploy and can walk an
  interviewer through line by line. Fully genuine, so every candidate clears background
  verification.</p>
  <div class="grid two mt-8">{projects}</div>

  <p class="label mt-10">The stack you'll have used</p>
  <div class="pills mt-4">{tools}</div>
  {explore}
</section>
"""


def deployment_page(shared: dict) -> str:
    story = shared["deploymentStory"]
    steps = join(
        f'<div class="card"><div class="eyebrow">{e(s["step"])}</div>'
        f'<h3 class="mt-2 sm">{e(s["title"])}</h3>'
        f'<p class="body mt-2">{e(s["body"])}</p>'
        f"</div>"
        for s in story["steps"]
    )
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
  <p class="kicker">{e(story['kicker'])}</p>
  <h2 class="section-title">{e(story['headline'])}</h2>
  <p class="lede">{e(story['body'])}</p>
  <div class="grid two mt-8">{steps}</div>
</section>

<section class="page">
  <h2 class="section-title">Everything included.
The whole machine, from day one.</h2>
  <p class="lede">No add-on pricing for the things that get you hired. Every tool below ships
  with this track.</p>
  <div class="grid three">{groups}</div>
</section>
"""


def placement_page(course: dict, shared: dict) -> str:
    channels = join(
        f'<div class="card"><p class="kicker">{e(c["kicker"])}</p>'
        f'<h3 class="mt-2 sm">{e(c["title"])}</h3>'
        f'<p class="body mt-2">{e(c["body"])}</p></div>'
        for c in shared["placementChannels"]
    )
    services = join(
        f'<tr><td class="key">{e(s["service"])}</td><td>{e(s["what"])}</td></tr>'
        for s in shared["careerServices"]
    )
    return f"""
<section class="page">
  <h2 class="section-title">The placement engine.
You don't apply into a black hole.</h2>
  <p class="lede">You get presented — through three channels, by our team, with your profile
  optimised first.</p>
  <div class="grid three mt-8">{channels}</div>

  <p class="label mt-10">Career services included</p>
  <table class="mt-2">
    <tr><th>Service</th><th>What you get</th></tr>
    {services}
  </table>

  <div class="rule-block mt-8">
    <span class="rule-label">Readiness is decided on data</span>
    We send you to interviews when your mock scores say you're ready — not when a sales target
    says so. That is precisely why our attend-to-place rate is what it is.
  </div>
</section>
"""


def fees_page(shared: dict) -> str:
    fees = shared["fees"]
    ladder = join(
        f'<div class="{"free" if r["free"] else "paid"}">'
        f'<div class="rung-label">{"Free · " if r["free"] else "Step "}{e(r["step"])}</div>'
        f"<h3>{e(r['title'])}</h3><p>{e(r['body'])}</p></div>"
        for r in shared["freeLadder"]
    )
    emi = fees["registrationEmi"]
    placement = fees["placement"]
    example_lpa = 12
    example_ctc = example_lpa * 100_000
    three_months = example_ctc // 4
    balance = three_months - placement["adjusted"]
    return f"""
<section class="page">
  <h2 class="section-title">Fees that bet on you.
Most of it only after you're placed.</h2>

  <p class="kicker free mt-8">Your path — everything is free until step 04</p>
  <div class="ladder mt-4">{ladder}</div>

  <table class="mt-10">
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

  <div class="rule-block mt-8">
    <span class="rule-label">Worked example · ₹{example_lpa} LPA offer</span>
    3 months' CTC = {e(rupees(three_months))} − {e(rupees(placement['adjusted']))} already paid =
    {e(rupees(balance))} in {placement['emis']} EMIs — paid from a salary you didn't have before.
    We only earn properly when you do.
  </div>

  <div class="promise-card kept mt-8">
    <h3>{fees['guaranteeDays']}-day money-back guarantee</h3>
    <p class="body">Any reason, or no reason — full
    refund in your first {fees['guaranteeDays']} days of the program. No questions asked. Written
    into your agreement.</p>
  </div>
</section>
"""


def verify_page(course: dict, shared: dict) -> str:
    checks = join(
        f'<div class="card"><p class="kicker">Check {i:02d} · {e(c["time"])}</p>'
        f'<h3 class="mt-2 sm">{e(c["title"])}</h3>'
        f'<p class="body mt-2">{e(c["body"])}</p></div>'
        for i, c in enumerate(shared["verifyChecks"], start=1)
    )
    kept = join(f"<li>{e(p)}</li>" for p in shared["promisesKept"])
    never = join(f"<li>{e(p)}</li>" for p in shared["promisesNever"])
    return f"""
<section class="page">
  <h2 class="section-title">Don't trust us. Verify us.</h2>
  <p class="lede">Every institute will tell you their course is the best. So don't decide on
  anyone's words — including ours. Here is exactly how to check the market yourself, tonight,
  in fifteen minutes, for free.</p>
  <div class="grid three mt-8">{checks}</div>

  <div class="grid two mt-10">
    <div class="promise-card kept">
      <h3>What we promise — in writing</h3>
      <ul class="check">{kept}</ul>
    </div>
    <div class="promise-card never">
      <h3>What we will never tell you</h3>
      <ul class="cross">{never}</ul>
    </div>
  </div>

  <div class="rule-block mt-8">
    <span class="rule-label">Questions to ask ANY institute — including us</span>
    → Will you put every promise — fees, refund, placement process — in a written agreement?<br>
    → Is your success rate defined clearly? Ours: 98% of candidates who attend interviews, internal data.<br>
    → Do you guarantee a job? The only honest answer is no.<br>
    → Will anything on my CV be fabricated? With us: never — real projects and a real internship only.
  </div>
</section>
"""


def stories_page(shared: dict) -> str:
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
    contact = shared["contact"]
    return f"""
<section class="page">
  <h2 class="section-title">Real stories. Real people.
Real success.</h2>
  <div class="grid two mt-8">{testimonials}</div>

  <div class="stat-band mt-10">{aggregates}</div>
  <p class="disclaimer">*{e(shared['disclaimer'])}</p>

  <div class="closing mt-10">
    <h3>Start with everything free.</h3>
    <p>Counselling + a written career report · the live masterclass · a 7-hour Python bootcamp.
    Then — and only then — decide.</p>
    <div class="cta">Call or WhatsApp {e(contact['phone'])} · {e(contact['email'])} · browsejobs.ai</div>
  </div>

  <p class="colophon">{e(shared['entityLine'])}</p>
</section>
"""


# ----------------------------------------------------------------------------
# document
# ----------------------------------------------------------------------------


def document(course: dict, shared: dict) -> str:
    rail = f"{course['name']} · Course brochure · {EDITION}"
    pages = join(
        [
            cover(course, shared),
            engine_page(course, shared),
            situation_page(course, shared),
            syllabus_page(course),
            tools_and_projects_page(course, shared),
            deployment_page(shared),
            placement_page(course, shared),
            fees_page(shared),
            verify_page(course, shared),
            stories_page(shared),
        ]
    )
    return f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{e(course['name'])}</title>
</head>
<body>
  <div class="rail"><span>{e(rail)}</span></div>
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
    shared = payload["shared"]
    stylesheet = CSS(filename=str(STYLESHEET))
    OUT_DIR.mkdir(parents=True, exist_ok=True)

    for course in payload["courses"]:
        name = course["name"].replace(" & ", "_").replace(" ", "_")
        target = OUT_DIR / f"BrowseJobs_{name}_Brochure_2026.pdf"
        HTML(string=document(course, shared), base_url=str(HERE)).write_pdf(
            target, stylesheets=[stylesheet]
        )
        size_kb = target.stat().st_size / 1024
        print(f"  {target.name}  ({len(course['modules'])} modules, {size_kb:.0f} KB)")

    print(f"\n{len(payload['courses'])} brochure(s) written to {OUT_DIR}")


if __name__ == "__main__":
    main()
