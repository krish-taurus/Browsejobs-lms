import { NextResponse } from "next/server";

/**
 * Same-origin proxy so the public site can store an enquiry without exposing
 * the Laravel origin. The visitor IP is forwarded only for the API to rate-limit.
 */
export async function POST(request: Request) {
  const api = process.env.API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000";
  const body = await request.text();
  const forwarded = request.headers.get("x-forwarded-for")?.split(",")[0]?.trim() || request.headers.get("x-real-ip") || "";

  const headers: Record<string, string> = {
    "content-type": "application/json",
    accept: "application/json",
    "user-agent": request.headers.get("user-agent") ?? "",
  };
  if (forwarded) headers["x-enquiry-client-ip"] = forwarded;
  const secret = process.env.ENQUIRY_PROXY_SECRET;
  if (secret) headers["x-enquiry-proxy"] = secret;

  try {
    const upstream = await fetch(`${api.replace(/\/$/, "")}/api/v1/enquiries`, {
      method: "POST",
      headers,
      body,
      signal: AbortSignal.timeout(8000),
    });
    const text = await upstream.text();
    return new Response(text, {
      status: upstream.status,
      headers: { "content-type": upstream.headers.get("content-type") ?? "application/json" },
    });
  } catch {
    return NextResponse.json(
      { message: "We could not save that just now. Please try again." },
      { status: 502 },
    );
  }
}
