import { NextResponse } from "next/server";
import { getInterviewQuestions } from "@/lib/kimi-intel";

/** Per-track interview question bank (kimi-k3, hourly cache) — see lib/kimi-intel. */

export const revalidate = 3600;

export async function GET() {
  return NextResponse.json(await getInterviewQuestions());
}
