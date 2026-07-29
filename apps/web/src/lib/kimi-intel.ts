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

export type Signal = { skill: string; change: string; note: string };
export type MarketSignals = { rising: Signal[]; cooling: Signal[]; source: "kimi-k3" | "sample"; updated: string };

export type QRound = { round: number; name: string; questions: string[] };
export type QuestionBank = { tracks: Record<string, QRound[]>; source: "kimi-k3" | "sample"; updated: string };

export type TrackDemand = {
  total: number;
  trend: string;
  portals: { name: string; share: number }[];
  roles: { role: string; count: number }[];
  cities: { city: string; share: number }[];
};
export type JobDemand = { tracks: Record<string, TrackDemand>; source: "kimi-k3" | "sample"; updated: string };

const TTL_MS = 60 * 60 * 1000;

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
  try {
    const parsed = await kimiJson<Pick<MarketSignals, "rising" | "cooling">>(
      "You are a pragmatic job-market analyst covering Indian tech hiring (data engineering, DevOps/cloud, data analytics, Python backend). Respond with strict JSON only.",
      'Return {"rising":[{"skill","change","note"} x5],"cooling":[{"skill","change","note"} x4]} for skill demand in Indian tech hiring right now. "change" is a plausible year-over-year demand delta like "+38%" or "-21%". "note" is a punchy reason under 8 words. Skills must be specific and current.',
      4000,
    );
    if (!Array.isArray(parsed.rising) || parsed.rising.length === 0) throw new Error("bad shape");
    signalsCache = { rising: parsed.rising, cooling: parsed.cooling, source: "kimi-k3", updated: stamp };
    signalsAt = Date.now();
    return signalsCache;
  } catch {
    return { ...SIGNALS_FALLBACK, updated: stamp };
  }
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
};

let questionsCache: QuestionBank | null = null;
let questionsAt = 0;

export async function getInterviewQuestions(): Promise<QuestionBank> {
  const stamp = new Date().toISOString();
  if (questionsCache && Date.now() - questionsAt < TTL_MS) return questionsCache;
  try {
    const parsed = await kimiJson<{ tracks: Record<string, QRound[]> }>(
      "You are an interview-prep analyst who has read thousands of real Indian tech interview transcripts. Respond with strict JSON only.",
      'Return {"tracks":{"data-engineering":[{"round":1,"name":"...","questions":["...","...","..."]} x4],"devops-cloud":[x4],"python-backend":[x3],"data-analytics":[x3]}}. For each track give the MOST FREQUENTLY asked real interview questions in Indian tech hiring right now, grouped by round in the usual loop order (screening -> technical deep-dive -> system design/case -> managerial). Round "name" under 4 words. Each question under 12 words, specific and realistic (e.g. "Debug a CrashLoopBackOff — live"). 2-3 questions per round.',
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
  try {
    const parsed = await kimiJson<{ tracks: Record<string, TrackDemand> }>(
      "You are a job-market analyst for Indian tech hiring. Give realistic current estimates of active job postings (order-of-magnitude correct, India, last 30 days, across Naukri/LinkedIn/other portals). Respond with strict JSON only.",
      'Return {"tracks":{"data-engineering":{"total":N,"trend":"+12%","portals":[{"name":"Naukri","share":N},{"name":"LinkedIn","share":N},{"name":"Others","share":N}],"roles":[{"role","count"} x3-4],"cities":[{"city","share"} x4]}, "devops-cloud":{...},"data-analytics":{...},"python-backend":{...}}}. total = estimated active postings in India last 30 days for the track. Shares are percentages summing ~100. Role counts sum to roughly total. trend = month-over-month direction.',
      6000,
    );
    if (!parsed.tracks || typeof parsed.tracks["data-engineering"]?.total !== "number") throw new Error("bad shape");
    demandCache = { tracks: parsed.tracks, source: "kimi-k3", updated: stamp };
    demandAt = Date.now();
    return demandCache;
  } catch {
    return { tracks: DEMAND_FALLBACK, source: "sample", updated: stamp };
  }
}
