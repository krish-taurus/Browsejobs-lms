import { revalidatePath } from "next/cache";
import { NextResponse, type NextRequest } from "next/server";

/**
 * Internal endpoint the Laravel API pings the moment a JD's status actually
 * changes (publish, pause, close) — see App\Jobs\RevalidatePublicJobBoard.
 * Drops the public job board's cached copy immediately instead of leaving a
 * closed role looking applyable until its 60s ISR window happens to be
 * revalidated by the next visitor.
 *
 * Never reachable from a browser: requires the same secret the API holds,
 * checked here rather than left to network-level trust.
 */
export async function POST(request: NextRequest) {
  const secret = request.headers.get("x-internal-secret");
  if (!secret || secret !== process.env.REVALIDATE_SECRET) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }

  let jobId: unknown;
  try {
    const body = await request.json();
    jobId = (body as { job_id?: unknown }).job_id;
  } catch {
    jobId = undefined;
  }

  revalidatePath("/jobs");
  if (typeof jobId === "number" || typeof jobId === "string") {
    revalidatePath(`/jobs/${jobId}`);
  }

  return NextResponse.json({ revalidated: true });
}
