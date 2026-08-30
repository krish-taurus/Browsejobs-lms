#!/usr/bin/env python3
"""Inline-SVG charts and diagrams for the print brochures.

Print has no hover layer, so every value a reader needs is directly labelled or
carried by an axis — nothing is gated behind interaction. Marks follow the
house spec: bars capped at 24px with a rounded data-end, hairline recessive
gridlines, a 2px surface gap between touching fills, and labels in ink tokens
rather than the series colour.

Two themes, because the brochure alternates light and dark spreads. Each has
its OWN validated ordinal ramp — a dark ramp is not an inverted light one.
Both are single-hue blue, monotone in lightness, with the light end clearing
its own surface. Green, amber and red never appear as series colours: the
brand reserves them for free/verified, review and refused-promise, and a chart
borrowing them would break that reading across the whole document.

Every figure is drawn from published BrowseJobs data. Nothing here invents a
number; where a figure is a worked example it is labelled as one.
"""

from __future__ import annotations

from html import escape

# Validated light-surface ordinal ramp — one hue, light → dark, trust blue
# mid-ramp so the brand's primary stays the colour a reader remembers.
RAMP = ["#8ab5f6", "#5591f3", "#1b6df0", "#124fc4", "#0a2f7a"]

# Validated dark-surface ramp. Stepped against the dark ground in its own
# right rather than flipped, so the light end still clears the surface.
RAMP_DARK = ["#e4eefe", "#b6d1fa", "#7fadf7", "#4d8ef7", "#2b6fe0"]

INK = "#0a1220"
INK2 = "#1b2a44"
MUTED = "#5a6b85"
LINE = "#dce6f5"
SKY = "#e7f1fe"
TRUST = "#1b6df0"
TRUST_LIFT = "#6ea6ff"
VERIFY = "#0ba860"
VERIFY_LIFT = "#2fcf8d"
PAPER = "#f6f9fe"
SURFACE = "#ffffff"

# On-ink text tokens. #ffffff for primary, a cool grey for secondary — both
# clear AA on the ink ground.
ON_INK = "#f4f7fb"
ON_INK_MUTED = "#93a3ba"
ON_INK_LINE = "#1d2735"

MONO = "'IBM Plex Mono', monospace"
SANS = "'Inter', sans-serif"
DISPLAY = "'Sora', sans-serif"


class Theme:
    """Colour tokens for one surface. Charts read every colour from here."""

    def __init__(self, dark: bool = False) -> None:
        self.dark = dark
        self.ramp = RAMP_DARK if dark else RAMP
        self.ink = ON_INK if dark else INK
        self.ink2 = ON_INK if dark else INK2
        self.muted = ON_INK_MUTED if dark else MUTED
        self.line = ON_INK_LINE if dark else LINE
        self.accent = TRUST_LIFT if dark else TRUST
        self.verify = VERIFY_LIFT if dark else VERIFY
        self.surface = INK if dark else SURFACE

    def on(self, step: int) -> str:
        """Text colour that clears contrast on ramp step ``step``.

        The ramps run in opposite directions, so the rule differs: on the light
        ramp the first two steps are pale enough to need ink; on the dark ramp
        it is the first three.
        """
        pale = step < 3 if self.dark else step < 2
        return INK if pale else "#ffffff"


LIGHT = Theme(False)
DARK = Theme(True)


def _t(
    x: float,
    y: float,
    text: str,
    *,
    size: float = 9,
    fill: str = INK,
    weight: str = "400",
    family: str = SANS,
    anchor: str = "start",
) -> str:
    return (
        f'<text x="{x:.1f}" y="{y:.1f}" font-family="{family}" font-size="{size}" '
        f'font-weight="{weight}" fill="{fill}" text-anchor="{anchor}">{escape(text)}</text>'
    )


def _svg(width: float, height: float, body: str, cls: str = "chart") -> str:
    return (
        f'<svg class="{cls}" viewBox="0 0 {width:.0f} {height:.0f}" '
        f'width="100%" xmlns="http://www.w3.org/2000/svg" '
        f'role="img">{body}</svg>'
    )


# ----------------------------------------------------------------------------
# figures — where the form is a number, not a chart
# ----------------------------------------------------------------------------


def meter_ring(
    pct: float, value: str, label: str, *, size: float = 132, theme: Theme = LIGHT
) -> str:
    """A single ratio against its limit, drawn as a ring.

    The rule for one value against a maximum: a meter, never a two-slice pie.
    The track is the same hue at low opacity so the ring reads as one scale
    rather than two categories.

    The caption is HTML beneath the SVG, not SVG text: SVG text does not wrap,
    so a label of any length would overflow the viewBox and be clipped.
    """
    r = size / 2 - 11
    cx = cy = size / 2
    colour = theme.accent
    circumference = 2 * 3.14159265 * r
    filled = circumference * min(max(pct, 0), 1)
    svg = _svg(
        size,
        size,
        f'<circle cx="{cx}" cy="{cy}" r="{r:.1f}" fill="none" stroke="{colour}" '
        f'stroke-opacity="0.16" stroke-width="10"/>'
        f'<circle cx="{cx}" cy="{cy}" r="{r:.1f}" fill="none" stroke="{colour}" '
        f'stroke-width="10" stroke-linecap="round" '
        f'stroke-dasharray="{filled:.1f} {circumference:.1f}" '
        f'transform="rotate(-90 {cx} {cy})"/>'
        + _t(cx, cy + 8, value, size=25, fill=theme.ink, weight="600", family=MONO, anchor="middle"),
        cls="chart ring-svg",
    )
    return f'<div class="ring">{svg}<p class="ring-label">{escape(label)}</p></div>'


def hero_stat(value: str, label: str, *, note: str = "") -> str:
    """A headline number. The figure is the chart — no plot beneath it."""
    note_html = f'<p class="hero-note">{escape(note)}</p>' if note else ""
    return (
        f'<div class="hero-stat"><div class="hero-value mono">{escape(value)}</div>'
        f'<div class="hero-label">{escape(label)}</div>{note_html}</div>'
    )


# ----------------------------------------------------------------------------
# charts
# ----------------------------------------------------------------------------


def range_bars(rows: list[dict], *, unit: str = "LPA", width: float = 640, theme: Theme = LIGHT) -> str:
    """Ordered roles against a salary range — a floating bar per role.

    The categories are ordered (junior → senior), so the ordinal ramp is the
    correct colour job: darker reads as further up the ladder.
    """
    label_w = 200
    row_h = 34
    bar_h = 18  # under the 24px cap
    pad_top = 26
    height = pad_top + row_h * len(rows) + 16
    plot_x = label_w
    plot_w = width - label_w - 78

    top = max(r["high"] for r in rows)

    def x(v: float) -> float:
        return plot_x + (v / top) * plot_w

    body = []

    # Recessive hairline grid, one step off the surface.
    for tick in range(0, int(top) + 1, 5):
        gx = x(tick)
        body.append(
            f'<line x1="{gx:.1f}" y1="{pad_top - 10:.0f}" x2="{gx:.1f}" '
            f'y2="{height - 18:.0f}" stroke="{theme.line}" stroke-width="1"/>'
        )
        body.append(
            _t(gx, height - 6, f"{tick}", size=8, fill=theme.muted, family=MONO, anchor="middle")
        )

    for i, row in enumerate(rows):
        y = pad_top + i * row_h
        colour = theme.ramp[min(i, len(theme.ramp) - 1)]
        x0, x1 = x(row["low"]), x(row["high"])
        body.append(
            f'<rect x="{x0:.1f}" y="{y:.1f}" width="{max(x1 - x0, 2):.1f}" '
            f'height="{bar_h}" rx="4" fill="{colour}"/>'
        )
        body.append(_t(0, y + bar_h - 4, row["role"], size=9.5, fill=theme.ink, weight="500"))
        body.append(
            _t(
                x1 + 8,
                y + bar_h - 4,
                row["label"],
                size=9,
                fill=theme.ink2,
                weight="600",
                family=MONO,
            )
        )

    body.append(_t(width, height - 6, unit, size=8, fill=theme.muted, family=MONO, anchor="end"))
    return _svg(width, height, "".join(body))


def split_bar(segments: list[dict], *, width: float = 640, theme: Theme = LIGHT) -> str:
    """Part-to-whole across two segments — a single stacked bar, not a pie.

    A 2px surface gap does the separating; no stroke is drawn around either
    fill. Only the share is drawn inside the bar, because a narrow segment
    cannot hold a caption — those are HTML beneath, keyed by a swatch.
    """
    height = 44
    gap = 2
    total = sum(s["value"] for s in segments)

    body = []
    cursor = 0.0
    for i, seg in enumerate(segments):
        share = seg["value"] / total
        w = share * width - (gap if i < len(segments) - 1 else 0)
        colour = seg.get("colour", theme.ramp[3 if i == 0 else 0])
        body.append(
            f'<rect x="{cursor:.1f}" y="0" width="{max(w, 1):.1f}" '
            f'height="{height}" rx="6" fill="{colour}"/>'
        )
        # White on the dark step, ink on the light one — chosen by luminance.
        body.append(
            _t(
                cursor + 12,
                height / 2 + 6,
                f"{share * 100:.0f}%",
                size=16,
                fill=theme.on(3 if i == 0 else 0),
                weight="600",
                family=MONO,
            )
        )
        cursor += share * width

    return _svg(width, height, "".join(body))


def grouped_bars(
    rows: list[dict], series: list[dict], *, width: float = 640, theme: Theme = LIGHT
) -> str:
    """Two comparable measures per track. One axis — never a second scale.

    Both measures are counts in the same units and the same order of magnitude,
    so they share the axis honestly. A legend is present because there are two
    series, and every bar is direct-labelled since print has no tooltip.
    """
    label_h = 30
    pad_top = 30
    group_h = 56
    bar_h = 18
    gap = 2
    height = pad_top + group_h * len(rows) + label_h
    plot_x = 190
    plot_w = width - plot_x - 40
    top = max(max(r[s["key"]] for s in series) for r in rows)

    body = []

    for tick in range(0, top + 1, 3):
        gx = plot_x + (tick / top) * plot_w
        body.append(
            f'<line x1="{gx:.1f}" y1="{pad_top - 12:.0f}" x2="{gx:.1f}" '
            f'y2="{height - label_h:.0f}" stroke="{theme.line}" stroke-width="1"/>'
        )
        body.append(
            _t(
                gx,
                height - label_h + 14,
                str(tick),
                size=8,
                fill=theme.muted,
                family=MONO,
                anchor="middle",
            )
        )

    for i, row in enumerate(rows):
        gy = pad_top + i * group_h
        body.append(_t(0, gy + 15, row["name"], size=10, fill=theme.ink, weight="600"))
        for j, s in enumerate(series):
            y = gy + j * (bar_h + gap)
            w = (row[s["key"]] / top) * plot_w
            body.append(
                f'<rect x="{plot_x}" y="{y:.1f}" width="{max(w, 2):.1f}" '
                f'height="{bar_h}" rx="4" fill="{s["colour"]}"/>'
            )
            body.append(
                _t(
                    plot_x + w + 7,
                    y + bar_h - 5,
                    str(row[s["key"]]),
                    size=9,
                    fill=theme.ink2,
                    weight="600",
                    family=MONO,
                )
            )

    # Legend — always present at two or more series.
    lx = plot_x
    for s in series:
        body.append(
            f'<rect x="{lx}" y="{4}" width="10" height="10" rx="3" fill="{s["colour"]}"/>'
        )
        body.append(_t(lx + 15, 13, s["label"], size=8.5, fill=theme.muted))
        lx += 22 + len(s["label"]) * 5.0

    return _svg(width, height, "".join(body))


# ----------------------------------------------------------------------------
# diagrams — process, not magnitude
# ----------------------------------------------------------------------------


def free_ladder(rungs: list[dict], *, width: float = 640, theme: Theme = LIGHT) -> str:
    """The funnel spine as a staircase: three free rungs, then the paid one.

    Green is used here as the brand defines it — free and kept-promise — and
    the single paid rung is the one blue element, so the eye lands on the point
    where money starts.
    """
    height = 190
    step_w = width / len(rungs)
    base = height - 42

    body = []
    for i, rung in enumerate(rungs):
        x = i * step_w
        h = 34 + i * 26
        y = base - h
        free = rung["free"]
        fill = ("#0d2a1f" if theme.dark else "#e6f7ef") if free else ("#12233d" if theme.dark else SKY)
        stroke = theme.verify if free else theme.accent
        body.append(
            f'<rect x="{x + 4:.1f}" y="{y:.1f}" width="{step_w - 8:.1f}" '
            f'height="{h:.1f}" rx="8" fill="{fill}" stroke="{stroke}" '
            f'stroke-opacity="0.35" stroke-width="1"/>'
        )
        body.append(
            _t(
                x + step_w / 2,
                y + 20,
                ("FREE · " if free else "STEP ") + rung["step"],
                size=8,
                fill=theme.verify if free else theme.accent,
                weight="600",
                family=MONO,
                anchor="middle",
            )
        )
        body.append(
            _t(
                x + step_w / 2,
                base + 18,
                rung["short"],
                size=9,
                fill=theme.ink,
                weight="600",
                anchor="middle",
            )
        )
        body.append(
            _t(
                x + step_w / 2,
                base + 32,
                rung["note"],
                size=8,
                fill=theme.muted,
                anchor="middle",
            )
        )

    body.append(
        f'<line x1="0" y1="{base:.1f}" x2="{width}" y2="{base:.1f}" '
        f'stroke="{theme.line}" stroke-width="1"/>'
    )
    return _svg(width, height, "".join(body))


def pipeline_flow(stages: list[dict], *, width: float = 640, theme: Theme = LIGHT) -> str:
    """The employer hiring pipeline as a narrowing flow.

    Ordered stages, so the ordinal ramp again: the pipeline visibly darkens and
    narrows from applications to offer. The in-bar label is the stage's short
    kicker — the full title overflowed the narrowest bars, and SVG text neither
    wraps nor clips.
    """
    n = len(stages)
    row_h = 44
    height = n * row_h + 20
    left = 78
    max_w = width - left
    min_w = max_w * 0.52

    body = []
    for i, stage in enumerate(stages):
        y = 14 + i * row_h
        w = max_w - (max_w - min_w) * (i / (n - 1))
        colour = theme.ramp[min(int(i / n * len(theme.ramp)), len(theme.ramp) - 1)]
        bx = left + (max_w - w) / 2
        body.append(
            f'<rect x="{bx:.1f}" y="{y:.1f}" width="{w:.1f}" '
            f'height="{row_h - 10}" rx="6" fill="{colour}"/>'
        )
        body.append(
            _t(
                left + max_w / 2,
                y + 22,
                stage["kicker"],
                size=10,
                fill=theme.on(i),
                weight="600",
                anchor="middle",
            )
        )
        body.append(
            _t(0, y + 22, f"Step {stage['step']}", size=8.5, fill=theme.muted, family=MONO)
        )

    return _svg(width, height, "".join(body))


def journey_timeline(phases: list[dict], *, width: float = 640, theme: Theme = LIGHT) -> str:
    """Six months as one proportional bar, months labelled inside each segment.

    Phase names and details are HTML beneath the bar rather than SVG text:
    SVG text neither wraps nor clips, so labels on narrow segments collided
    with their neighbours. A 2px surface gap separates the segments.
    """
    height = 34
    total = sum(p["weight"] for p in phases)
    gap = 2

    body = []
    cursor = 0.0
    for i, phase in enumerate(phases):
        w = phase["weight"] / total * width
        colour = theme.ramp[min(i, len(theme.ramp) - 1)]
        seg_w = max(w - gap, 2)
        body.append(
            f'<rect x="{cursor:.1f}" y="0" width="{seg_w:.1f}" height="{height}" '
            f'rx="5" fill="{colour}"/>'
        )
        # Ink on the two lightest steps, white on the rest — chosen by the
        # fill's luminance so the label always clears contrast.
        text_fill = theme.on(i)
        body.append(
            _t(
                cursor + seg_w / 2,
                height / 2 + 4,
                phase["month"].replace("Month ", "").replace("Months ", ""),
                size=10,
                fill=text_fill,
                weight="600",
                family=MONO,
                anchor="middle",
            )
        )
        cursor += w

    return _svg(width, height, "".join(body))


def career_flow(steps: list[dict], *, width: float = 640, theme: Theme = LIGHT) -> str:
    """An ordered role progression, as chevrons that darken along the ramp.

    A progression is ordinal, not categorical, so it takes the same single-hue
    ramp as the other ordered figures. Role names sit inside the chevrons; the
    "what you do" line is HTML beneath, because SVG text will not wrap.
    """
    n = len(steps)
    height = 44
    gap = 3
    seg_w = (width - gap * (n - 1)) / n
    notch = 10

    body = []
    for i, step in enumerate(steps):
        x = i * (seg_w + gap)
        colour = theme.ramp[min(i, len(theme.ramp) - 1)]
        # Chevron: flat left edge on the first segment, notched thereafter.
        left_notch = 0 if i == 0 else notch
        path = (
            f"M {x} 0 "
            f"L {x + seg_w - notch:.1f} 0 "
            f"L {x + seg_w:.1f} {height / 2:.1f} "
            f"L {x + seg_w - notch:.1f} {height} "
            f"L {x} {height} "
            f"L {x + left_notch} {height / 2:.1f} Z"
        )
        body.append(f'<path d="{path}" fill="{colour}"/>')
        body.append(
            _t(
                x + seg_w / 2,
                height / 2 + 4,
                step["short"],
                size=8.5,
                fill=theme.on(i),
                weight="600",
                anchor="middle",
            )
        )

    return _svg(width, height, "".join(body))


def breakout_motif() -> str:
    """The Breakout Bar mark, blown up as a cover motif.

    Drawn from the master logo's own geometry (public/logo.svg) with the ink
    tile dropped, so the oversized version cannot drift from the mark — an
    earlier hand-proportioned rebuild put the arrow out of scale with the bars.
    The logo already *is* a bar chart, which is why it works here as both
    brand mark and data visual.
    """
    return (
        '<svg class="chart motif" viewBox="11 11 42 40" width="100%" '
        'xmlns="http://www.w3.org/2000/svg" role="img">'
        '<rect x="13.5" y="36" width="5.5" height="13" rx="2.75" fill="#1B6DF0" opacity="0.4"/>'
        '<rect x="22.5" y="30" width="5.5" height="19" rx="2.75" fill="#1B6DF0" opacity="0.6"/>'
        '<rect x="31.5" y="24" width="5.5" height="25" rx="2.75" fill="#1B6DF0" opacity="0.9"/>'
        '<rect x="41.75" y="20" width="5.5" height="29" rx="2.75" fill="#FFFFFF"/>'
        '<path d="M38.5 20.5 L44.5 13.5 L50.5 20.5" stroke="#FFFFFF" stroke-width="5.5" '
        'stroke-linecap="round" stroke-linejoin="round" fill="none"/>'
        "</svg>"
    )
