import { NextResponse } from "next/server";
import { getMarketSignals } from "@/lib/kimi-intel";

/** Rising/cooling skill demand (kimi-k3, hourly cache) — see lib/kimi-intel. */

export const revalidate = 3600;

export async function GET() {
  return NextResponse.json(await getMarketSignals());
}
