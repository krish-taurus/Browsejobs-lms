/**
 * Thin API client for the Sanctum SPA cookie flow (ADR 0004). Fetches the CSRF
 * cookie before unsafe requests, sends credentials, and forwards the XSRF token.
 */
const API_BASE =
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export class ApiError extends Error {
  constructor(
    public status: number,
    public body: { message?: string; errors?: Record<string, string[]> },
  ) {
    super(body?.message ?? `Request failed (${status})`);
  }

  /** First validation message, if any. */
  get firstError(): string | undefined {
    const errs = this.body?.errors;
    if (errs) {
      const first = Object.values(errs)[0];
      if (first?.[0]) return first[0];
    }
    return this.body?.message;
  }
}

function getCookie(name: string): string | null {
  if (typeof document === "undefined") return null;
  const match = document.cookie.match(
    new RegExp("(^|;\\s*)" + name + "=([^;]*)"),
  );
  return match ? decodeURIComponent(match[2]) : null;
}

let csrfReady = false;

async function ensureCsrf(): Promise<void> {
  if (csrfReady) return;
  await fetch(`${API_BASE}/sanctum/csrf-cookie`, { credentials: "include" });
  csrfReady = true;
}

export async function apiJson<T = unknown>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const method = (options.method ?? "GET").toUpperCase();
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");

  const isForm = options.body instanceof FormData;

  if (method !== "GET" && method !== "HEAD") {
    await ensureCsrf();
    // Let the browser set the multipart boundary for FormData bodies.
    if (!isForm) headers.set("Content-Type", "application/json");
    const xsrf = getCookie("XSRF-TOKEN");
    if (xsrf) headers.set("X-XSRF-TOKEN", xsrf);
  }

  const res = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
    credentials: "include",
  });

  const body =
    res.status === 204 ? null : await res.json().catch(() => null);

  if (!res.ok) {
    throw new ApiError(res.status, body ?? {});
  }

  return body as T;
}

/** Fetch a file (e.g. a CSV template) as a Blob, credentials included. */
export async function apiBlob(path: string): Promise<Blob> {
  const res = await fetch(`${API_BASE}${path}`, { credentials: "include" });
  if (!res.ok) throw new ApiError(res.status, {});
  return res.blob();
}
