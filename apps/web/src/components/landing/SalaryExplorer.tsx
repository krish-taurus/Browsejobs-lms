"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { apiJson } from "@/lib/api";
import { Kicker } from "@/components/brand/Kicker";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { MonoCounter } from "@/components/motion/MonoCounter";

/**
 * Salary Explorer — free interactive benchmark widget (PRD §6.21 data):
 * pick a role, city and experience band; the p25/median/p75 figures animate
 * in mono. Money arrives server-owned in paise and is rendered as LPA.
 * Mandatory Disclaimer follows the figures (spec §3).
 */

type Row = { role: string; city: string; band: string; p25_paise: number; p50_paise: number; p75_paise: number };

const BAND_LABEL: Record<string, string> = { fresher: "Fresher", "1-3": "1–3 yrs", "3-5": "3–5 yrs" };
const toLpa = (paise: number) => paise / 10_000_000;

export function SalaryExplorer() {
  // Plain fetch — public marketing pages mount no QueryClientProvider.
  const [rows, setRows] = useState<Row[]>([]);
  useEffect(() => {
    let alive = true;
    apiJson<{ data: { rows: Row[] } }>("/api/v1/salaries")
      .then((res) => alive && setRows(res.data.rows))
      .catch(() => {}); // section hides gracefully without data
    return () => {
      alive = false;
    };
  }, []);

  const roles = useMemo(() => [...new Set(rows.map((r) => r.role))], [rows]);
  const [role, setRole] = useState<string | null>(null);
  const activeRole = role ?? roles[0] ?? null;

  const cities = useMemo(() => [...new Set(rows.filter((r) => r.role === activeRole).map((r) => r.city))], [rows, activeRole]);
  const [city, setCity] = useState<string | null>(null);
  const activeCity = city && cities.includes(city) ? city : cities[0] ?? null;

  const bands = useMemo(
    () => rows.filter((r) => r.role === activeRole && r.city === activeCity).map((r) => r.band),
    [rows, activeRole, activeCity],
  );
  const [band, setBand] = useState<string | null>(null);
  const activeBand = band && bands.includes(band) ? band : bands[0] ?? null;

  const row = rows.find((r) => r.role === activeRole && r.city === activeCity && r.band === activeBand);

  if (rows.length === 0) return null; // section disappears gracefully without data

  const selectCls =
    "rounded-[10px] border border-line bg-white px-3 py-2 text-sm font-medium text-ink outline-none focus:border-trust";

  return (
    <section id="salaries" className="mx-auto max-w-6xl px-5 py-16 md:py-24">
      <ScrollReveal>
        <Kicker>Salary explorer · free</Kicker>
        <h2 className="display mt-3 max-w-2xl text-3xl text-ink md:text-5xl">
          What the market actually pays
        </h2>
        <p className="mt-4 max-w-2xl text-ink2/70">
          Benchmarks from our placement and offer data — the same numbers our
          counsellors use when a student evaluates an offer.
        </p>
      </ScrollReveal>

      <ScrollReveal delay={0.08}>
        <div className="mt-10 rounded-[22px] border border-line bg-white p-7 shadow-soft md:p-9">
          <div className="flex flex-wrap items-center gap-3">
            <select aria-label="Role" className={selectCls} value={activeRole ?? ""} onChange={(e) => { setRole(e.target.value); setCity(null); setBand(null); }}>
              {roles.map((r) => <option key={r}>{r}</option>)}
            </select>
            <select aria-label="City" className={selectCls} value={activeCity ?? ""} onChange={(e) => { setCity(e.target.value); setBand(null); }}>
              {cities.map((c) => <option key={c}>{c}</option>)}
            </select>
            <div className="flex gap-1.5">
              {bands.map((b) => (
                <button
                  key={b}
                  type="button"
                  onClick={() => setBand(b)}
                  className={`rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors ${
                    b === activeBand ? "bg-trust text-white" : "border border-line text-muted hover:border-trust"
                  }`}
                >
                  {BAND_LABEL[b] ?? b}
                </button>
              ))}
            </div>
          </div>

          {row && (
            <div className="mt-8 grid gap-6 sm:grid-cols-3">
              {([
                ["Entry offers · p25", row.p25_paise, false],
                ["Median · p50", row.p50_paise, true],
                ["Strong offers · p75", row.p75_paise, false],
              ] as const).map(([label, paise, loud]) => (
                <div key={label} className={`rounded-[14px] border p-5 ${loud ? "border-trust/40 bg-sky/40" : "border-line"}`}>
                  <p className="mono text-[10px] uppercase tracking-[0.18em] text-muted">{label}</p>
                  <p className={`mono mt-2 font-semibold ${loud ? "text-4xl text-trust" : "text-3xl text-ink"}`}>
                    ₹<MonoCounter value={toLpa(paise)} decimals={1} plain className="inline" /> LPA
                  </p>
                </div>
              ))}
            </div>
          )}

          <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
            <Disclaimer />
            <Link href="/salaries" className="shrink-0 text-sm font-semibold text-trust hover:underline">
              City-by-city deep dives →
            </Link>
          </div>
        </div>
      </ScrollReveal>
    </section>
  );
}
