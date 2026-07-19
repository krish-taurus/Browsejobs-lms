"use client";

import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { CITIES, type CityPulse, type Trend } from "@/content/market-pulse";
import { DEMAND_OUTLOOK, FUNDING_SIGNALS, type FundingSignal, type OutlookSeries } from "@/content/intel-board";

/**
 * Live market-intelligence snapshot with graceful fallback: fetches
 * /api/v1/market-intel (daily-refreshed, sector-level) and merges onto the
 * bundled illustrative content, so the boards render instantly and upgrade
 * in place when the API answers. Never throws — fallback is the contract.
 */

type ApiSnapshot = {
  city_pulse: { key: string; name: string; trend: Trend; skills: { name: string; trend: Trend }[] }[] | null;
  funding: { sector: string; stage: string; hub: string; hiring_lag_months: number; roles: string[] }[] | null;
  outlook: { track: string; points: number[]; direction: "rising" | "steady" }[] | null;
  effective_on?: string | null;
};

export type MarketIntel = {
  cities: CityPulse[];
  funding: FundingSignal[];
  outlook: OutlookSeries[];
  effectiveOn: string | null;
  live: boolean;
};

const FALLBACK: MarketIntel = {
  cities: CITIES,
  funding: FUNDING_SIGNALS,
  outlook: DEMAND_OUTLOOK,
  effectiveOn: null,
  live: false,
};

export function useMarketIntel(): MarketIntel {
  const [intel, setIntel] = useState<MarketIntel>(FALLBACK);

  useEffect(() => {
    let alive = true;
    apiJson<{ data: ApiSnapshot }>("/api/v1/market-intel")
      .then(({ data }) => {
        if (!alive || !data) return;
        setIntel({
          // API rows carry no canvas coordinates — merge trends/skills onto
          // the bundled geometry by city key, keeping unknown cities as-is.
          cities: CITIES.map((c) => {
            const live = data.city_pulse?.find((p) => p.key === c.key);
            return live ? { ...c, trend: live.trend, skills: live.skills } : c;
          }),
          funding: data.funding?.map((f) => ({
            sector: f.sector,
            stage: f.stage,
            hub: f.hub,
            hiringLagMonths: f.hiring_lag_months,
            roles: f.roles,
          })) ?? FALLBACK.funding,
          outlook: data.outlook ?? FALLBACK.outlook,
          effectiveOn: data.effective_on ?? null,
          live: Boolean(data.city_pulse || data.funding || data.outlook),
        });
      })
      .catch(() => {}); // fallback already rendered
    return () => {
      alive = false;
    };
  }, []);

  return intel;
}
