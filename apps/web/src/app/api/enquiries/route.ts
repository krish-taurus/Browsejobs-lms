import https from "node:https";
import { NextResponse } from "next/server";

/**
 * Same-origin proxy. Production runs Next and Laravel on one VPS:
 * Nginx serves api.browsejobs.ai, and Next posts to that vhost on loopback
 * so Laravel sees 127.0.0.1. ENQUIRY_PROXY_SECRET is optional.
 *
 * API_URL, then NEXT_PUBLIC_API_URL, then https://api.browsejobs.ai.
 * A local artisan URL (localhost or 127.0.0.1) is called as given.
 */
const DEFAULT_API = "https://api.browsejobs.ai";

type Upstream = { status: number; body: string; contentType: string };

function apiBase(): string {
  const raw = process.env.API_URL?.trim() || process.env.NEXT_PUBLIC_API_URL?.trim() || DEFAULT_API;
  return raw.replace(/\/$/, "");
}

function isLocalApi(base: string): boolean {
  try {
    const url = new URL(base);
    return url.hostname === "localhost" || url.hostname === "127.0.0.1" || url.hostname === "::1" || url.hostname === "[::1]";
  } catch {
    return false;
  }
}

function postLoopbackTls(hostname: string, body: string, headers: Record<string, string>): Promise<Upstream> {
  return new Promise((resolve, reject) => {
    const req = https.request(
      {
        host: "127.0.0.1",
        port: 443,
        servername: hostname,
        method: "POST",
        path: "/api/v1/enquiries",
        headers: {
          ...headers,
          host: hostname,
          "content-length": Buffer.byteLength(body),
        },
        timeout: 4000,
      },
      (res) => {
        const chunks: Buffer[] = [];
        res.on("data", (chunk: Buffer) => {
          chunks.push(chunk);
        });
        res.on("end", () => {
          const type = res.headers["content-type"];
          resolve({
            status: res.statusCode ?? 502,
            body: Buffer.concat(chunks).toString("utf8"),
            contentType: Array.isArray(type) ? (type[0] ?? "application/json") : (type ?? "application/json"),
          });
        });
      },
    );
    req.on("error", reject);
    req.on("timeout", () => {
      req.destroy(new Error("Enquiry API timed out"));
    });
    req.write(body);
    req.end();
  });
}

async function postDirect(base: string, body: string, headers: Record<string, string>): Promise<Upstream> {
  const upstream = await fetch(`${base}/api/v1/enquiries`, {
    method: "POST",
    headers,
    body,
    signal: AbortSignal.timeout(8000),
  });
  return {
    status: upstream.status,
    body: await upstream.text(),
    contentType: upstream.headers.get("content-type") ?? "application/json",
  };
}

function responseOf(result: Upstream): Response {
  return new Response(result.body, {
    status: result.status,
    headers: { "content-type": result.contentType },
  });
}

export async function POST(request: Request) {
  const base = apiBase();
  const body = await request.text();
  const forwarded = request.headers.get("x-forwarded-for")?.split(",")[0]?.trim() || request.headers.get("x-real-ip") || "";
  const headers: Record<string, string> = {
    "content-type": "application/json",
    accept: "application/json",
    "user-agent": request.headers.get("user-agent") ?? "",
  };
  if (forwarded) headers["x-enquiry-client-ip"] = forwarded;
  const secret = process.env.ENQUIRY_PROXY_SECRET?.trim();
  if (secret) headers["x-enquiry-proxy"] = secret;

  try {
    if (!isLocalApi(base)) {
      const hostname = new URL(base).hostname;
      try {
        const loopback = await postLoopbackTls(hostname, body, headers);
        // 404/3xx means this port is not the API vhost. The public URL is the fallback.
        if (loopback.status === 404 || loopback.status === 301 || loopback.status === 302 || loopback.status === 308) {
          return responseOf(await postDirect(base, body, headers));
        }
        return responseOf(loopback);
      } catch {
        return responseOf(await postDirect(base, body, headers));
      }
    }

    return responseOf(await postDirect(base, body, headers));
  } catch {
    return NextResponse.json(
      { message: "We could not save that just now. Please try again." },
      { status: 502 },
    );
  }
}
