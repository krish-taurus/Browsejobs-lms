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

let csrfRequest: Promise<void> | null = null;

/**
 * Make sure a usable XSRF-TOKEN cookie exists. The cookie — not a memoised
 * flag — is the source of truth: it expires with the session (SESSION_LIFETIME),
 * so a tab left open long enough would otherwise POST without a token and get
 * "CSRF token mismatch." forever until a manual reload.
 */
async function ensureCsrf(force = false): Promise<void> {
  if (!force && getCookie("XSRF-TOKEN")) return;
  csrfRequest ??= fetch(`${API_BASE}/sanctum/csrf-cookie`, {
    credentials: "include",
  })
    .then(() => undefined)
    .finally(() => {
      csrfRequest = null;
    });
  await csrfRequest;
}

/**
 * Send an unsafe request; if the server rejects the CSRF token, take a fresh
 * one and send it again — once.
 *
 * 419 means the token expired or rotated (a session timing out, or the API
 * being redeployed with a new APP_KEY, which invalidates the encrypted
 * cookie). It is recoverable and routine, so it must never reach the caller as
 * a failure.
 *
 * This lives in one place deliberately. It used to exist only inside apiJson,
 * and apiPostBlob went without — so JSON calls healed themselves while audio
 * calls silently failed, and the console dropped to the browser voice at random.
 * Anything that sends an unsafe request goes through here.
 */
async function sendUnsafe(send: (refresh: boolean) => Promise<Response>): Promise<Response> {
  const res = await send(false);

  return res.status === 419 ? send(true) : res;
}

export async function apiJson<T = unknown>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const method = (options.method ?? "GET").toUpperCase();
  const unsafe = method !== "GET" && method !== "HEAD";

  const send = async (refresh: boolean): Promise<Response> => {
    const headers = new Headers(options.headers);
    headers.set("Accept", "application/json");

    if (unsafe) {
      await ensureCsrf(refresh);
      // Let the browser set the multipart boundary for FormData bodies.
      if (!(options.body instanceof FormData)) {
        headers.set("Content-Type", "application/json");
      }
      const xsrf = getCookie("XSRF-TOKEN");
      if (xsrf) headers.set("X-XSRF-TOKEN", xsrf);
    }

    return fetch(`${API_BASE}${path}`, {
      ...options,
      headers,
      credentials: "include",
    });
  };

  const res = unsafe ? await sendUnsafe(send) : await send(false);

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

/**
 * POST and take the answer as a Blob — for endpoints that return a file rather
 * than JSON, such as the mock interviewer's spoken question.
 *
 * Returns null when the server has nothing to give (204), which the caller is
 * expected to treat as "fall back", not as an error.
 */
export async function apiPostBlob(
  path: string,
  body: unknown,
): Promise<Blob | null> {
  const send = async (refresh: boolean): Promise<Response> => {
    await ensureCsrf(refresh);

    const headers = new Headers({ "Content-Type": "application/json" });
    const xsrf = getCookie("XSRF-TOKEN");
    if (xsrf) headers.set("X-XSRF-TOKEN", xsrf);

    return fetch(`${API_BASE}${path}`, {
      method: "POST",
      headers,
      credentials: "include",
      body: JSON.stringify(body),
    });
  };

  const res = await sendUnsafe(send);

  if (res.status === 204) return null;

  const problem = res.status === 419 ? { message: "CSRF token rejected twice." } : {};
  if (!res.ok) throw new ApiError(res.status, problem);

  return res.blob();
}
