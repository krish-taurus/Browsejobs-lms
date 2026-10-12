"use client";

import { useCallback, useEffect, useState } from "react";
import { InterviewStartForm } from "@/components/auth/InterviewStartForm";
import { ApiError, apiJson } from "@/lib/api";

type Invite = {
  first_name: string;
  role: string | null;
  company: string | null;
  round: string;
  status: string;
  day: string | null;
  open: boolean;
};

/**
 * Candidate side of a WhatsApp interview link. Public details first; the
 * WhatsApp OTP sign-in only when they press Start (and only if they are not
 * signed in already); then straight into the voice interview room, where the
 * interview is spoken, recorded and scored.
 */
export function HireInterviewStart({ token }: { token: string }) {
  const [invite, setInvite] = useState<Invite | null>(null);
  const [missing, setMissing] = useState(false);
  const [needLogin, setNeedLogin] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiJson<{ data: Invite }>(`/api/v1/interview-invites/${encodeURIComponent(token)}`)
      .then((res) => setInvite(res.data))
      .catch(() => setMissing(true));
  }, [token]);

  const start = useCallback(async () => {
    setBusy(true);
    setError(null);
    try {
      const res = await apiJson<{ data: { room_url: string } }>(`/api/v1/me/interview-invites/${encodeURIComponent(token)}/start`, { method: "POST" });
      window.location.href = res.data.room_url;
    } catch (err) {
      if (err instanceof ApiError && (err.status === 401 || err.status === 419)) {
        setNeedLogin(true);
      } else {
        setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not start the interview. Please try again.");
      }
      setBusy(false);
    }
  }, [token]);

  // Back from the sign-in step (?go=1): start straight away.
  useEffect(() => {
    if (invite?.open && typeof window !== "undefined" && new URLSearchParams(window.location.search).get("go") === "1") {
      void start();
    }
  }, [invite, start]);

  return (
    <main className="min-h-dvh bg-[#f5f5f7] px-4 py-10 text-[#1d1d1f] md:py-16">
      <div className="mx-auto max-w-lg rounded-[22px] bg-white p-6 shadow-[0_10px_40px_rgba(10,18,32,.10)] md:p-9">
        <p className="font-mono text-[11px] font-semibold uppercase tracking-[0.16em] text-[#1b6df0]">BrowseJobs · Interview</p>

        {missing ? (
          <>
            <h1 className="mt-3 text-2xl font-bold tracking-tight">This link does not work</h1>
            <p className="mt-3 text-[15px] leading-relaxed text-[#6e6e73]">Check that you opened the full link from WhatsApp, or reply in that WhatsApp chat and we will help.</p>
          </>
        ) : invite === null ? (
          <div className="mt-4 h-40 animate-pulse rounded-2xl bg-[#f5f5f7]" />
        ) : (
          <>
            <h1 className="mt-3 text-2xl font-bold tracking-tight md:text-3xl">
              Hi {invite.first_name}, this is your {invite.round} interview
            </h1>
            <p className="mt-2 text-[15px] text-[#6e6e73]">
              {invite.role ?? "The role"}
              {invite.company ? ` at ${invite.company}` : ""}
            </p>

            {invite.status === "done" ? (
              <p className="mt-6 rounded-2xl bg-[#e6f7ef] p-4 text-[15px]">You have taken this interview. The company will be in touch.</p>
            ) : invite.status === "declined" ? (
              <p className="mt-6 rounded-2xl bg-[#f5f5f7] p-4 text-[15px]">You told us you are not interested in this role. Changed your mind? Reply in the WhatsApp chat.</p>
            ) : !invite.open ? (
              <p className="mt-6 rounded-2xl bg-[#f5f5f7] p-4 text-[15px] leading-relaxed">
                {invite.day ? `Your interview opens on ${invite.day}. ` : "Your interview is not open yet. "}
                We will send you a WhatsApp message with this link that morning.
              </p>
            ) : (
              <>
                <ul className="mt-6 space-y-2 text-[15px] leading-relaxed text-[#424245]">
                  <li>• An AI interviewer asks the questions out loud. You answer by speaking.</li>
                  <li>• It takes about 15 minutes. Find a quiet place and allow your microphone.</li>
                  <li>• The interview is recorded and scored, and shared with {invite.company ?? "the company"}.</li>
                </ul>

                {error && (
                  <p className="mt-5 rounded-[10px] bg-[#d64545]/10 px-3 py-2 text-sm text-[#d64545]" role="alert">
                    {error}
                  </p>
                )}

                {needLogin ? (
                  <div className="mt-6">
                    <p className="text-[15px] font-semibold">Sign in with the WhatsApp number this link was sent to</p>
                    <InterviewStartForm next={`/hire-interview/${token}?go=1`} tone="apple" id="hire-interview-login" />
                  </div>
                ) : (
                  <button
                    type="button"
                    disabled={busy}
                    onClick={() => void start()}
                    className="mt-7 inline-flex h-12 w-full items-center justify-center rounded-full bg-[#1b6df0] px-7 text-[17px] font-semibold text-white transition-colors hover:bg-[#0e3fa9] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1b6df0] disabled:opacity-50"
                  >
                    {busy ? "Opening…" : "Start my interview"}
                  </button>
                )}
              </>
            )}
          </>
        )}
      </div>
    </main>
  );
}
