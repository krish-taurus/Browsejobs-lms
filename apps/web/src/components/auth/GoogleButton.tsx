"use client";

import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";

const API_BASE = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

/**
 * "Continue with Google" — renders only when the API reports Google auth is
 * configured (spec §7: optional Google sign-in).
 */
export function GoogleButton() {
  const [enabled, setEnabled] = useState(false);

  useEffect(() => {
    apiJson<{ google_auth: boolean }>("/api/v1/branding")
      .then((b) => setEnabled(Boolean(b.google_auth)))
      .catch(() => setEnabled(false));
  }, []);

  if (!enabled) return null;

  return (
    <>
      <div className="my-5 flex items-center gap-3">
        <span className="h-px flex-1 bg-line" />
        <span className="mono text-[11px] uppercase tracking-widest text-muted">or</span>
        <span className="h-px flex-1 bg-line" />
      </div>
      <a
        href={`${API_BASE}/auth/google/redirect`}
        className="flex w-full items-center justify-center gap-3 rounded-full border border-line bg-white py-3 font-semibold text-ink transition-colors hover:border-trust"
      >
        <svg viewBox="0 0 24 24" className="h-5 w-5" aria-hidden>
          <path fill="#4285F4" d="M23.5 12.3c0-.9-.1-1.5-.3-2.2H12v4.1h6.5c-.1 1.1-.8 2.7-2.4 3.8l-.02.15 3.5 2.7.24.02c2.2-2 3.5-5 3.5-8.6" />
          <path fill="#34A853" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.8-2.9c-1 .7-2.4 1.2-4.1 1.2-3.2 0-5.8-2.1-6.8-4.9l-.14.01-3.6 2.8-.05.13C3.4 21.3 7.4 24 12 24" />
          <path fill="#FBBC05" d="M5.2 14.5c-.25-.7-.4-1.5-.4-2.5s.15-1.8.4-2.5l-.01-.16-3.7-2.8-.12.06C.5 8.1 0 10 0 12s.5 3.9 1.4 5.5l3.8-3" />
          <path fill="#EB4335" d="M12 4.6c2.3 0 3.8 1 4.7 1.8l3.4-3.3C18 1.2 15.2 0 12 0 7.4 0 3.4 2.7 1.4 6.5l3.7 3c1-2.8 3.6-4.9 6.9-4.9" />
        </svg>
        Continue with Google
      </a>
    </>
  );
}
