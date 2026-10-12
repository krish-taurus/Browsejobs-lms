"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiError } from "@/lib/api";
import { useWorkspace } from "@/components/employer/EmployerShell";
import { GhostButton, Label, Pill, PrimaryButton, Skeleton } from "@/components/employer/ui";
import { employerApi, type WhatsAppPostingData } from "@/lib/employer";

/**
 * Hiring step 1 — post jobs on WhatsApp.
 *
 * The member scans the QR (or taps "Open WhatsApp"); WhatsApp opens with
 * "JOIN BJ-XXXXXX" ready to send to the BrowseJobs number, which links their
 * phone to this workspace. From then on they describe a role in plain words,
 * the agent drafts the JD, and it goes live only when they reply YES.
 */
export function WhatsAppJobPosting() {
  const { workspace } = useWorkspace();
  const [data, setData] = useState<WhatsAppPostingData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [showChat, setShowChat] = useState(false);

  const load = useCallback(() => {
    employerApi
      .whatsappPosting(workspace.id)
      .then((res) => setData(res.data))
      .catch(() => setError("Could not load WhatsApp posting."));
  }, [workspace.id]);

  useEffect(load, [load]);

  async function regenerate() {
    if (!confirm("Make a new QR code? The old code stops working. Phones already connected stay connected.")) return;
    setBusy(true);
    try {
      setData((await employerApi.regenerateWhatsappCode(workspace.id)).data);
    } catch (err) {
      setError(err instanceof ApiError ? (err.firstError ?? err.message) : "Could not make a new code.");
    } finally {
      setBusy(false);
    }
  }

  async function unlink(id: number, phone: string) {
    if (!confirm(`Disconnect ${phone}? It will no longer be able to post jobs for ${workspace.name}.`)) return;
    setBusy(true);
    try {
      await employerApi.unlinkWhatsapp(workspace.id, id);
      load();
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="rounded-[var(--bj-dash-radius)] border bg-white p-5 md:p-6" style={{ borderColor: "var(--bj-dash-border)" }}>
      <div className="grid gap-6 md:grid-cols-[auto_1fr] md:items-start">
        <div className="mx-auto w-[184px] md:mx-0">
          {data === null ? (
            <Skeleton className="h-[184px] w-[184px]" />
          ) : data.qr_svg ? (
            <div
              className="rounded-2xl border p-2 [&>svg]:h-auto [&>svg]:w-full"
              style={{ borderColor: "var(--bj-dash-border)" }}
              // Rendered server-side from our own wa.me link — no user input inside.
              dangerouslySetInnerHTML={{ __html: data.qr_svg }}
            />
          ) : (
            <div className="grid h-[184px] place-items-center rounded-2xl border p-4 text-center text-[12px]" style={{ borderColor: "var(--bj-dash-border)", color: "var(--bj-dash-muted)" }}>
              {data.code === null ? "Only owners and recruiters can post jobs." : "WhatsApp is not connected yet."}
            </div>
          )}
          {data?.code && <p className="mt-2 text-center font-mono text-[12px] font-semibold tracking-wider">{data.code}</p>}
        </div>

        <div>
          <Label>Post jobs on WhatsApp</Label>
          <h2 className="bj-dash-serif mt-1.5 text-xl tracking-tight md:text-2xl" style={{ color: "var(--bj-dash-ink)" }}>
            Scan once. Then just message the job.
          </h2>
          <ol className="mt-3 space-y-1.5 text-sm" style={{ color: "var(--bj-dash-muted)" }}>
            <li><span className="font-mono font-semibold" style={{ color: "var(--bj-dash-primary)" }}>1</span> Scan the QR with your phone and press send — your number is connected.</li>
            <li><span className="font-mono font-semibold" style={{ color: "var(--bj-dash-primary)" }}>2</span> Message the role in your own words: title, experience, location, how many people.</li>
            <li><span className="font-mono font-semibold" style={{ color: "var(--bj-dash-primary)" }}>3</span> Check the draft and reply <b>YES</b>. It goes live and the shortlist agent starts searching.</li>
          </ol>

          <div className="mt-4 flex flex-wrap items-center gap-3">
            {data?.chat_url && (
              <PrimaryButton href={data.chat_url}>Open WhatsApp</PrimaryButton>
            )}
            {data?.code && (
              <GhostButton disabled={busy} onClick={() => void regenerate()}>New QR code</GhostButton>
            )}
            {data?.business_number && (
              <span className="font-mono text-[11px]" style={{ color: "var(--bj-dash-muted)" }}>
                BrowseJobs number +{data.business_number}
              </span>
            )}
          </div>
          {error && <p className="mt-3 text-sm text-[var(--bj-dash-score-below)]">{error}</p>}

          {data && data.links.length > 0 && (
            <div className="mt-5 border-t pt-4" style={{ borderColor: "var(--bj-dash-border)" }}>
              <p className="text-[13px] font-semibold">Connected phones</p>
              <ul className="mt-2 space-y-2">
                {data.links.map((l) => (
                  <li key={l.id} className="flex flex-wrap items-center justify-between gap-2 text-[13px]">
                    <span>
                      <span className="font-mono">{l.phone}</span>
                      {l.profile_name && <span style={{ color: "var(--bj-dash-muted)" }}> · {l.profile_name}</span>}
                      {l.acts_as && <span style={{ color: "var(--bj-dash-muted)" }}> · posts as {l.acts_as}</span>}
                      {l.state === "confirming" && <span className="ml-2"><Pill tone="amber">Draft waiting for YES</Pill></span>}
                    </span>
                    {data.code !== null && (
                      <button type="button" disabled={busy} onClick={() => void unlink(l.id, l.phone)} className="text-[12px] font-semibold text-[var(--bj-dash-muted)] hover:text-[var(--bj-dash-score-below)]">
                        Disconnect
                      </button>
                    )}
                  </li>
                ))}
              </ul>
              {data.messages.length > 0 && (
                <button type="button" onClick={() => setShowChat((v) => !v)} className="mt-3 text-[12px] font-semibold" style={{ color: "var(--bj-dash-primary)" }}>
                  {showChat ? "Hide" : "Show"} recent WhatsApp messages
                </button>
              )}
              {showChat && (
                <ul className="mt-3 max-h-80 space-y-2 overflow-y-auto rounded-2xl p-3" style={{ background: "var(--bj-dash-canvas)" }}>
                  {data.messages.map((m) => (
                    <li key={m.id} className={`flex ${m.direction === "out" ? "justify-end" : "justify-start"}`}>
                      <span
                        className="max-w-[85%] whitespace-pre-line rounded-2xl px-3 py-2 text-[12px] leading-relaxed"
                        style={m.direction === "out"
                          ? { background: "var(--bj-dash-soft)", color: "var(--bj-dash-ink)" }
                          : { background: "#ffffff", color: "var(--bj-dash-ink)", border: "1px solid var(--bj-dash-border)" }}
                      >
                        {m.body}
                        {m.status === "failed" && <span className="block text-[10px] text-[var(--bj-dash-score-below)]">Not delivered</span>}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
