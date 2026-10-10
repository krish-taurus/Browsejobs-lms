"use client";

import { useState } from "react";
import Link from "next/link";
import { ApLeadButton } from "@/components/ap/pages/ApLeadButton";
import { DISCLAIMER } from "@/content/landing";
import { sameRolePages, type SalaryBand, type SalaryPage } from "@/content/salaries";
import { skillHref } from "@/content/skills";
import "@/components/ap/pages/marketing.css";

/**
 * The body of a /salaries page in the Apple-direction design: a centred hero
 * with the median for the chosen experience band, the percentile track, the
 * skills row, other cities for the same role and a masterclass close. Every
 * figure sits above the mandatory disclaimer.
 */

const BAND_LABEL: Record<string, string> = { fresher: "Fresher", "1-3": "1–3 yrs experience", "3-5": "3–5 yrs" };

function PercentileTrack({ band }: { band: SalaryBand }) {
  const max = band.p75 * 1.15;
  const pct = (v: number) => (v / max) * 100;

  return (
    <div className="sal-track">
      <div className="sal-rail" aria-hidden="true">
        <i style={{ width: `${pct(band.p75)}%` }} />
      </div>
      <ul className="sal-marks">
        {([
          ["p25", band.p25, "entry offers"],
          ["p50", band.p50, "median"],
          ["p75", band.p75, "strong offers"],
        ] as const).map(([key, value, label]) => (
          <li key={key} className={key === "p50" ? "is-median" : undefined} style={{ left: `${pct(value)}%` }}>
            <b>₹{value} LPA</b>
            <span>{label}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

export function SalaryDetail({ page }: { page: SalaryPage }) {
  const [bandIdx, setBandIdx] = useState(page.bands.length > 1 ? 1 : 0);
  const band = page.bands[bandIdx];
  const siblings = sameRolePages(page);

  return (
    <>
      <section className="s-white pg-hero sal-hero">
        <div className="wrap center">
          <nav aria-label="Breadcrumb" className="pg-crumbs">
            <Link href="/salaries">Salaries</Link>
            <span aria-hidden>{" › "}</span>
            <span aria-current="page">
              {page.role}, {page.city}
            </span>
          </nav>
          <p className="eyebrow">Salary intelligence · {page.city}</p>
          <h1 className="h-hero">
            <span>{page.role}</span> <span className="sal-city">in {page.city}</span>
          </h1>
          <p className="lead">{page.blurb}</p>

          <div className="sal-median">
            <p className="eyebrow-sm">Median · {BAND_LABEL[band.band] ?? band.band}</p>
            <p className="sal-figure">
              ₹{band.p50.toFixed(1)}
              <small>LPA</small>
            </p>
            {page.bands.length > 1 && (
              <div className="sal-bands" role="group" aria-label="Experience band">
                {page.bands.map((b, i) => (
                  <button key={b.band} type="button" aria-pressed={i === bandIdx} onClick={() => setBandIdx(i)}>
                    {BAND_LABEL[b.band] ?? b.band}
                  </button>
                ))}
              </div>
            )}
          </div>

          <PercentileTrack key={band.band} band={band} />
          <p className="fine">{DISCLAIMER}</p>
        </div>
      </section>

      <section className="s-paper chapter">
        <div className="wrap center">
          <p className="eyebrow">What earns it</p>
          <h2 className="h-section">The skills behind these offers</h2>
          <ul className="pg-chips is-center sal-skills">
            {page.skills.map((sk) => {
              const href = skillHref(sk);
              return (
                <li key={sk}>{href ? <Link href={href}>{sk}</Link> : <span>{sk}</span>}</li>
              );
            })}
          </ul>
          {page.track && (
            <p className="lead">
              We teach every one of these in the{" "}
              <Link href={page.track.href} className="ap-link">
                {page.track.name} track
              </Link>{" "}
              — rebuilt monthly from real interviews.
            </p>
          )}
        </div>
      </section>

      {siblings.length > 0 && (
        <section className="s-white chapter">
          <div className="wrap">
            <div className="pg-head">
              <p className="eyebrow">Compare cities</p>
              <h2 className="h-section">{page.role} pay across India</h2>
            </div>
            <ul className="pg-tiles" data-reveal-kids="">
              {siblings.map((s) => {
                const b = s.bands[s.bands.length - 1];
                return (
                  <li key={s.slug}>
                    <Link href={`/salaries/${s.slug}`} className="pg-tile">
                      <span className="eyebrow-sm">{s.city}</span>
                      <span className="pg-big">₹{b.p50} LPA</span>
                      <span className="body">median · {BAND_LABEL[b.band] ?? b.band}</span>
                      <span className="more">
                        View breakdown <span className="chev" aria-hidden="true">›</span>
                      </span>
                    </Link>
                  </li>
                );
              })}
            </ul>
            <p className="fine sal-fine">{DISCLAIMER}</p>
          </div>
        </section>
      )}

      <section className="s-black chapter pg-closing">
        <div className="wrap">
          <p className="eyebrow">Free, before any fee</p>
          <h2 className="h-section">See how we train people into these offers</h2>
          <p className="lead">
            A live masterclass on the engine that rebuilds our syllabus from real {page.city} interviews — every month.
          </p>
          <div className="cta-row">
            <ApLeadButton />
          </div>
        </div>
      </section>
    </>
  );
}
