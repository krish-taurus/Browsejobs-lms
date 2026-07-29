import { NextResponse } from "next/server";

/**
 * Live market-signal feed for the /v3 landing template. Asks kimi-k3 (the
 * platform LLM) for rising/cooling skill demand in Indian tech hiring and
 * caches the answer for an hour. Falls back to a curated sample when the key
 * is missing or the call fails, flagged via `source` so the UI can say so.
 */

export const revalidate = 3600;

type Signal = { skill: string; change: string; note: string };
type Payload = { rising: Signal[]; cooling: Signal[]; source: "kimi-k3" | "sample"; updated: string };

const FALLBACK: Omit<Payload, "updated"> = {
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

// Module-level cache: the first request pays the LLM latency (~15s on low
// reasoning effort), every request after that is instant for an hour.
let cache: Payload | null = null;
let cachedAt = 0;
const TTL_MS = 60 * 60 * 1000;

export async function GET() {
  const key = process.env.KIMI_API_KEY;
  const stamp = new Date().toISOString();
  if (!key) return NextResponse.json({ ...FALLBACK, updated: stamp });
  if (cache && Date.now() - cachedAt < TTL_MS) return NextResponse.json(cache);

  try {
    const res = await fetch("https://api.moonshot.ai/v1/chat/completions", {
      method: "POST",
      headers: { "content-type": "application/json", authorization: `Bearer ${key}` },
      body: JSON.stringify({
        // kimi-k3 is a reasoning model: it only accepts temperature 1, and
        // max_tokens must leave room for its thinking tokens.
        model: process.env.KIMI_MODEL ?? "kimi-k3",
        temperature: 1,
        max_tokens: 4000,
        reasoning_effort: "low",
        response_format: { type: "json_object" },
        messages: [
          {
            role: "system",
            content:
              "You are a pragmatic job-market analyst covering Indian tech hiring (data engineering, DevOps/cloud, data analytics, Python backend). Respond with strict JSON only.",
          },
          {
            role: "user",
            content:
              'Return {"rising":[{"skill","change","note"} x5],"cooling":[{"skill","change","note"} x4]} for skill demand in Indian tech hiring right now. "change" is a plausible year-over-year demand delta like "+38%" or "-21%". "note" is a punchy reason under 8 words. Skills must be specific and current.',
          },
        ],
      }),
      signal: AbortSignal.timeout(45_000),
    });
    if (!res.ok) throw new Error(`moonshot ${res.status}`);
    const body = (await res.json()) as { choices?: { message?: { content?: string } }[] };
    const parsed = JSON.parse(body.choices?.[0]?.message?.content ?? "") as Pick<Payload, "rising" | "cooling">;
    if (!Array.isArray(parsed.rising) || !Array.isArray(parsed.cooling) || parsed.rising.length === 0) {
      throw new Error("bad shape");
    }
    cache = { rising: parsed.rising, cooling: parsed.cooling, source: "kimi-k3", updated: stamp };
    cachedAt = Date.now();
    return NextResponse.json(cache);
  } catch {
    return NextResponse.json({ ...FALLBACK, updated: stamp });
  }
}
