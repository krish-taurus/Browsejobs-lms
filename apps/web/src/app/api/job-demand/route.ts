import { NextResponse } from "next/server";

/**
 * Job-market demand per track for the course pages and home tiles. Sourced
 * from kimi-k3 (the platform LLM) as a market estimate, cached hourly, with a
 * curated fallback. Honesty doctrine: the UI always shows the source badge and
 * links visitors to verify counts on Naukri themselves — no scraping, and no
 * numbers presented as exact (ADR 0045: job data only via licensed APIs).
 */

export const revalidate = 3600;

type Portal = { name: string; share: number };
type TrackDemand = {
  total: number;
  trend: string;
  portals: Portal[];
  roles: { role: string; count: number }[];
  cities: { city: string; share: number }[];
};
type Payload = {
  tracks: Record<string, TrackDemand>;
  source: "kimi-k3" | "sample";
  updated: string;
};

const FALLBACK_TRACKS: Record<string, TrackDemand> = {
  "data-engineering": {
    total: 18400, trend: "+14%",
    portals: [{ name: "Naukri", share: 46 }, { name: "LinkedIn", share: 38 }, { name: "Others", share: 16 }],
    roles: [{ role: "Data Engineer", count: 9800 }, { role: "ETL Developer", count: 3900 }, { role: "Analytics Engineer", count: 2600 }, { role: "Big Data Engineer", count: 2100 }],
    cities: [{ city: "Bengaluru", share: 34 }, { city: "Hyderabad", share: 22 }, { city: "Pune", share: 16 }, { city: "Chennai", share: 12 }],
  },
  "devops-cloud": {
    total: 15900, trend: "+11%",
    portals: [{ name: "Naukri", share: 44 }, { name: "LinkedIn", share: 40 }, { name: "Others", share: 16 }],
    roles: [{ role: "DevOps Engineer", count: 7600 }, { role: "Cloud Engineer", count: 4200 }, { role: "SRE", count: 2400 }, { role: "Platform Engineer", count: 1700 }],
    cities: [{ city: "Bengaluru", share: 38 }, { city: "Hyderabad", share: 20 }, { city: "Pune", share: 15 }, { city: "Gurugram", share: 11 }],
  },
  "data-analytics": {
    total: 21700, trend: "+9%",
    portals: [{ name: "Naukri", share: 49 }, { name: "LinkedIn", share: 34 }, { name: "Others", share: 17 }],
    roles: [{ role: "Data Analyst", count: 11200 }, { role: "Business Analyst", count: 6300 }, { role: "BI Analyst", count: 4200 }],
    cities: [{ city: "Bengaluru", share: 30 }, { city: "Mumbai", share: 19 }, { city: "Hyderabad", share: 17 }, { city: "Chennai", share: 12 }],
  },
  "python-backend": {
    total: 13200, trend: "+8%",
    portals: [{ name: "Naukri", share: 45 }, { name: "LinkedIn", share: 39 }, { name: "Others", share: 16 }],
    roles: [{ role: "Backend Engineer", count: 6900 }, { role: "Python Developer", count: 4400 }, { role: "API Engineer", count: 1900 }],
    cities: [{ city: "Bengaluru", share: 36 }, { city: "Hyderabad", share: 18 }, { city: "Pune", share: 14 }, { city: "Chennai", share: 11 }],
  },
};

let cache: Payload | null = null;
let cachedAt = 0;
const TTL_MS = 60 * 60 * 1000;

export async function GET() {
  const key = process.env.KIMI_API_KEY;
  const stamp = new Date().toISOString();
  if (!key) return NextResponse.json({ tracks: FALLBACK_TRACKS, source: "sample", updated: stamp });
  if (cache && Date.now() - cachedAt < TTL_MS) return NextResponse.json(cache);

  try {
    const res = await fetch("https://api.moonshot.ai/v1/chat/completions", {
      method: "POST",
      headers: { "content-type": "application/json", authorization: `Bearer ${key}` },
      body: JSON.stringify({
        model: process.env.KIMI_MODEL ?? "kimi-k3",
        temperature: 1,
        max_tokens: 6000,
        reasoning_effort: "low",
        response_format: { type: "json_object" },
        messages: [
          {
            role: "system",
            content:
              "You are a job-market analyst for Indian tech hiring. Give realistic current estimates of active job postings (order-of-magnitude correct, India, last 30 days, across Naukri/LinkedIn/other portals). Respond with strict JSON only.",
          },
          {
            role: "user",
            content:
              'Return {"tracks":{"data-engineering":{"total":N,"trend":"+12%","portals":[{"name":"Naukri","share":N},{"name":"LinkedIn","share":N},{"name":"Others","share":N}],"roles":[{"role","count"} x3-4],"cities":[{"city","share"} x4]}, "devops-cloud":{...},"data-analytics":{...},"python-backend":{...}}}. total = estimated active postings in India last 30 days for the track. Shares are percentages summing ~100. Role counts sum to roughly total. trend = month-over-month direction.',
          },
        ],
      }),
      signal: AbortSignal.timeout(60_000),
    });
    if (!res.ok) throw new Error(`moonshot ${res.status}`);
    const body = (await res.json()) as { choices?: { message?: { content?: string } }[] };
    const parsed = JSON.parse(body.choices?.[0]?.message?.content ?? "") as { tracks: Record<string, TrackDemand> };
    if (!parsed.tracks || typeof parsed.tracks["data-engineering"]?.total !== "number") throw new Error("bad shape");
    cache = { tracks: parsed.tracks, source: "kimi-k3", updated: stamp };
    cachedAt = Date.now();
    return NextResponse.json(cache);
  } catch {
    return NextResponse.json({ tracks: FALLBACK_TRACKS, source: "sample", updated: stamp });
  }
}
