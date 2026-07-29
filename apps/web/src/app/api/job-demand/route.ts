import { NextResponse } from "next/server";
import { getJobDemand } from "@/lib/kimi-intel";

/** Per-track job-market demand estimates (kimi-k3, hourly cache) — see lib/kimi-intel. */

export const revalidate = 3600;

export async function GET() {
  return NextResponse.json(await getJobDemand());
}
