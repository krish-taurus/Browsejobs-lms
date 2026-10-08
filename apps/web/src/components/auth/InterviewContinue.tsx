"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { InterviewStartForm } from "@/components/auth/InterviewStartForm";
import { ApiError, apiJson } from "@/lib/api";
import { cvMockApi } from "@/lib/candidate";
import { mockPath } from "@/lib/mockKinds";

type Gate = "loading" | "auth" | "cv" | "error";

/**
 * After sign-in, start the CV interview (15 questions). No CV yet means
 * My CV first — the interview will not start without one.
 */
export function InterviewContinue() {
  const router = useRouter();
  const [gate, setGate] = useState<Gate>("loading");
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    cvMockApi
      .start()
      .then((result) => {
        if (!cancelled) router.replace(mockPath("cv", result.data.mock_id, true));
      })
      .catch(async (err: unknown) => {
        if (cancelled) return;
        if (err instanceof ApiError && err.status === 401) {
          setGate("auth");
          return;
        }
        if (err instanceof ApiError && err.body.errors?.cv) {
          setGate("cv");
          return;
        }
        if (!(err instanceof ApiError)) {
          try {
            await apiJson("/api/v1/me");
          } catch {
            if (!cancelled) setGate("auth");
            return;
          }
        }
        if (!cancelled) {
          setMessage(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the interview.");
          setGate("error");
        }
      });

    return () => {
      cancelled = true;
    };
  }, [router]);

  return (
    <div className="home-canvas min-h-screen px-5 py-16">
      <div className="mx-auto max-w-lg">
        <p className="kicker text-verify">Free · AI interview</p>
        <h1 className="display mt-4 text-4xl text-fg">Take your free AI interview.</h1>

        {gate === "loading" && <p className="mt-6 text-muted">Starting your interview…</p>}

        {gate === "auth" && (
          <>
            <p className="mt-4 text-lg text-muted">
              It&apos;s free. Sign in with your phone. A new number asks for your name, then a code.
            </p>
            <InterviewStartForm />
          </>
        )}

        {gate === "cv" && (
          <div className="mt-6 space-y-4">
            <p className="text-lg text-muted">
              The interview is 15 questions from your CV. Build that CV first, then come back and start. It stays free.
            </p>
            <Link
              href="/cv"
              className="inline-flex rounded-full bg-trust px-7 py-3.5 font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)]"
            >
              Build your CV
            </Link>
          </div>
        )}

        {gate === "error" && (
          <div className="mt-6 space-y-4">
            <p className="text-warn" role="alert">
              {message}
            </p>
            <Link href="/jobs-for-you" className="font-semibold text-sky hover:underline">
              Open the interview from your jobs page
            </Link>
          </div>
        )}
      </div>
    </div>
  );
}
