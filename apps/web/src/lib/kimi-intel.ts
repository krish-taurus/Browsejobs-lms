/**
 * Shared kimi-k3 market-intelligence getters with in-process hourly caches.
 * The API routes (/api/market-signals, /api/interview-questions,
 * /api/job-demand) are thin wrappers over these, and /api/question-bank
 * calls them directly — same process, same caches, no self-HTTP (which
 * fails behind the production proxy).
 *
 * kimi-k3 quirks: temperature must be 1; reasoning_effort "low" keeps
 * latency ~15s; max_tokens must leave room for thinking tokens.
 */

/**
 * Where a figure came from, and the only thing a badge may claim.
 *
 * `counted` — measured over postings we ingested.
 * `sample`  — bundled illustrative content. Never a measurement, and the UI
 *             must not imply otherwise.
 *
 * `kimi-k3` is gone from market figures on purpose: the prompt behind them
 * asked a model for estimates that were "order-of-magnitude correct", and the
 * page badged that as live market analysis.
 */
export type SourceKind = "counted" | "sample";

export type Signal = { skill: string; change: string; note: string };
export type MarketSignals = { rising: Signal[]; cooling: Signal[]; source: SourceKind; updated: string; sample?: number };

export type QRound = { round: number; name: string; questions: string[] };
export type QuestionBank = { tracks: Record<string, QRound[]>; source: "kimi-k3" | "sample"; updated: string };

export type TrackDemand = {
  total: number;
  trend: string;
  portals: { name: string; share: number }[];
  roles: { role: string; count: number }[];
  cities: { city: string; share: number }[];
};
export type JobDemand = { tracks: Record<string, TrackDemand>; source: SourceKind; updated: string; sample?: number };

const TTL_MS = 60 * 60 * 1000;

type CountedFeed = {
  tracks: Record<string, {
    label: string;
    sufficient: boolean;
    total: number;
    trend_pct?: number;
    cities?: { name: string; share: number }[];
    roles?: { name: string; count: number }[];
    sources?: { name: string; share: number }[];
  }>;
  rising: { skill: string; change_pct: number; postings: number }[];
  cooling: { skill: string; change_pct: number; postings: number }[];
  sample: number;
  window_days: number;
  sufficient: boolean;
};

/** The counted snapshot from the API, or null when it cannot be reached. */
async function countedFeed(): Promise<CountedFeed | null> {
  const base = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";
  try {
    const res = await fetch(`${base}/api/v1/market-intel`, { next: { revalidate: 3600 } });
    if (!res.ok) return null;
    const body = (await res.json()) as { data?: { feed?: CountedFeed } };
    return body.data?.feed ?? null;
  } catch {
    return null;
  }
}

async function kimiJson<T>(system: string, user: string, maxTokens: number): Promise<T> {
  const key = process.env.KIMI_API_KEY;
  if (!key) throw new Error("no key");
  const res = await fetch("https://api.moonshot.ai/v1/chat/completions", {
    method: "POST",
    headers: { "content-type": "application/json", authorization: `Bearer ${key}` },
    body: JSON.stringify({
      model: process.env.KIMI_MODEL ?? "kimi-k3",
      temperature: 1,
      max_tokens: maxTokens,
      reasoning_effort: "low",
      response_format: { type: "json_object" },
      messages: [
        { role: "system", content: system },
        { role: "user", content: user },
      ],
    }),
    signal: AbortSignal.timeout(60_000),
  });
  if (!res.ok) throw new Error(`moonshot ${res.status}`);
  const body = (await res.json()) as { choices?: { message?: { content?: string } }[] };
  return JSON.parse(body.choices?.[0]?.message?.content ?? "") as T;
}

/* ------------------------------ market signals ------------------------------ */

const SIGNALS_FALLBACK: Omit<MarketSignals, "updated"> = {
  source: "sample",
  rising: [
    { skill: "GenAI / LLM engineering", change: "+62%", note: "Every GCC is staffing AI teams" },
    { skill: "Databricks + Spark", change: "+38%", note: "Lakehouse migrations accelerating" },
    { skill: "Kubernetes & platform eng", change: "+31%", note: "Platform teams replacing pure ops" },
    { skill: "dbt + analytics engineering", change: "+27%", note: "Modern data stack adoption" },
    { skill: "Power BI + DAX", change: "+19%", note: "Analytics hiring steady in GCCs" },
  ],
  cooling: [
    { skill: "Manual QA testing", change: "-34%", note: "Automation replacing manual cycles" },
    { skill: "Legacy PHP maintenance", change: "-22%", note: "Stacks consolidating to newer runtimes" },
    { skill: "Hadoop administration", change: "-28%", note: "Workloads moving to cloud lakehouses" },
    { skill: "Excel-only reporting", change: "-17%", note: "BI tools now the baseline" },
  ],
};

let signalsCache: MarketSignals | null = null;
let signalsAt = 0;

export async function getMarketSignals(): Promise<MarketSignals> {
  const stamp = new Date().toISOString();
  if (signalsCache && Date.now() - signalsAt < TTL_MS) return signalsCache;

  const feed = await countedFeed();

  // `sufficient` is the API's own judgement about its sample. Rendering
  // whatever came back regardless is how a real count over nine postings ends
  // up presented as a market signal.
  if (feed && feed.sufficient && feed.rising.length > 0) {
    const toSignal = (m: { skill: string; change_pct: number; postings: number }): Signal => ({
      skill: m.skill,
      change: `${m.change_pct > 0 ? "+" : ""}${m.change_pct}%`,
      note: `${m.postings} postings in the last ${feed.window_days} days`,
    });

    signalsCache = {
      rising: feed.rising.map(toSignal),
      cooling: feed.cooling.map(toSignal),
      source: "counted",
      sample: feed.sample,
      updated: stamp,
    };
    signalsAt = Date.now();
    return signalsCache;
  }

  return { ...SIGNALS_FALLBACK, updated: stamp };
}

/* ---------------------------- interview questions --------------------------- */

const QUESTIONS_FALLBACK: Record<string, QRound[]> = {
  "data-engineering": [
    { round: 1, name: "SQL screening", questions: ["Top 3 salaries per department — write it", "Explain window functions vs GROUP BY", "Find duplicate rows without DISTINCT"] },
    { round: 2, name: "Pipelines & Spark", questions: ["Why is your Spark job slow? Debug it", "Partitioning vs bucketing — when each?", "Design an incremental load for late data"] },
    { round: 3, name: "System design", questions: ["Design a daily 1TB ETL pipeline", "Handle schema drift in production"] },
    { round: 4, name: "Managerial", questions: ["Walk through a pipeline failure you owned", "Why data engineering after your background?"] },
  ],
  "devops-cloud": [
    { round: 1, name: "Linux & scripting", questions: ["Debug a server at 100% CPU", "Explain what happens during a kernel panic"] },
    { round: 2, name: "Containers & K8s", questions: ["Debug a CrashLoopBackOff — live", "Liveness vs readiness probes — why both?", "Multi-stage Docker builds — what and why?"] },
    { round: 3, name: "IaC & CI/CD", questions: ["Terraform state got corrupted — recover it", "Design a zero-downtime deploy"] },
    { round: 4, name: "Managerial", questions: ["Worst production incident — your role?", "How do you push back on risky releases?"] },
  ],
  "python-backend": [
    { round: 1, name: "Python core", questions: ["Explain decorators with a real use-case", "Generators vs lists — memory story"] },
    { round: 2, name: "APIs & data", questions: ["Design a rate limiter", "N+1 queries — find and fix them"] },
    { round: 3, name: "System design", questions: ["Design a URL shortener at scale", "Where do you add caching, and why there?"] },
  ],
  "data-analytics": [
    { round: 1, name: "SQL & Excel", questions: ["Running totals with window functions", "INDEX-MATCH vs VLOOKUP — when and why?"] },
    { round: 2, name: "BI & storytelling", questions: ["A DAX measure returns wrong totals — debug", "Walk us through a dashboard you built"] },
    { round: 3, name: "Case round", questions: ["Sales dropped 12% — investigate with data", "Which metric would you cut? Defend it"] },
  ],
  "ai-engineering": [
    { round: 1, name: "LLM screening", questions: ["Context window vs tokens — explain the cost", "When would you not build an agent?"] },
    { round: 2, name: "RAG deep-dive", questions: ["Your RAG cites the wrong section — debug it", "Chunking strategy, and how you measure it", "Hybrid search vs pure vector — when each?"] },
    { round: 3, name: "Agents & safety", questions: ["Stop a prompt injection reaching a write tool", "Design an approval gate for an agent action"] },
    { round: 4, name: "Evaluation", questions: ["How do you prove a prompt change is safe?", "Build an eval set without labelled data"] },
  ],
};

let questionsCache: QuestionBank | null = null;
let questionsAt = 0;

export async function getInterviewQuestions(): Promise<QuestionBank> {
  const stamp = new Date().toISOString();
  if (questionsCache && Date.now() - questionsAt < TTL_MS) return questionsCache;
  try {
    const parsed = await kimiJson<{ tracks: Record<string, QRound[]> }>(
      "You are an interview-prep analyst who has read thousands of real Indian tech interview transcripts. Respond with strict JSON only.",
      'Return {"tracks":{"data-engineering":[{"round":1,"name":"...","questions":["...","...","..."]} x4],"devops-cloud":[x4],"python-backend":[x3],"data-analytics":[x3],"ai-engineering":[x4]}}. For each track give the MOST FREQUENTLY asked real interview questions in Indian tech hiring right now, grouped by round in the usual loop order (screening -> technical deep-dive -> system design/case -> managerial). Round "name" under 4 words. Each question under 12 words, specific and realistic (e.g. "Debug a CrashLoopBackOff — live"). 2-3 questions per round.',
      6000,
    );
    if (!parsed.tracks || !Array.isArray(parsed.tracks["data-engineering"]) || parsed.tracks["data-engineering"].length === 0) {
      throw new Error("bad shape");
    }
    questionsCache = { tracks: parsed.tracks, source: "kimi-k3", updated: stamp };
    questionsAt = Date.now();
    return questionsCache;
  } catch {
    return { tracks: QUESTIONS_FALLBACK, source: "sample", updated: stamp };
  }
}

/* -------------------------------- job demand -------------------------------- */

const DEMAND_FALLBACK: Record<string, TrackDemand> = {
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

let demandCache: JobDemand | null = null;
let demandAt = 0;

export async function getJobDemand(): Promise<JobDemand> {
  const stamp = new Date().toISOString();
  if (demandCache && Date.now() - demandAt < TTL_MS) return demandCache;

  const feed = await countedFeed();

  if (feed && feed.sufficient) {
    const tracks: Record<string, TrackDemand> = {};

    for (const [slug, t] of Object.entries(feed.tracks)) {
      // A track below the floor is left out rather than shown at whatever
      // number it happens to have. Someone choosing what to learn should not
      // be handed a figure we would not defend.
      if (!t.sufficient) continue;

      tracks[slug] = {
        total: t.total,
        trend: `${(t.trend_pct ?? 0) > 0 ? "+" : ""}${t.trend_pct ?? 0}%`,
        portals: (t.sources ?? []).map((x) => ({ name: x.name, share: x.share })),
        roles: (t.roles ?? []).map((x) => ({ role: x.name, count: x.count })),
        cities: (t.cities ?? []).map((x) => ({ city: x.name, share: x.share })),
      };
    }

    if (Object.keys(tracks).length > 0) {
      demandCache = { tracks, source: "counted", sample: feed.sample, updated: stamp };
      demandAt = Date.now();
      return demandCache;
    }
  }

  return { tracks: DEMAND_FALLBACK, source: "sample", updated: stamp };
}
