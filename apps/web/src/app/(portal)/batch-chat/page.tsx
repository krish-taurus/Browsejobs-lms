"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { apiJson } from "@/lib/api";

type Room = { number: string; name: string };

type ChatMessage = {
  id: number;
  author: string;
  from_staff: boolean;
  mine: boolean;
  body: string;
  sent_at: string | null;
};

type RoomPayload = {
  batch: string;
  name: string;
  staff_count: number;
  student_count: number;
  messages: ChatMessage[];
};

function clock(iso: string | null): string {
  if (iso === null) return "";
  const d = new Date(iso);
  return d.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
}

/**
 * The batch's own room: ask a doubt in front of the whole class, and whoever is
 * free answers — trainer, HR, or the team leads.
 *
 * Deliberately not a private line to one trainer. A question asked here is
 * answered once and read by everyone who had it, and nobody's doubt sits in an
 * individual's inbox waiting for them to come back.
 */
export default function BatchChatPage() {
  const [rooms, setRooms] = useState<Room[] | null>(null);
  const [active, setActive] = useState<string | null>(null);
  const [room, setRoom] = useState<RoomPayload | null>(null);
  const [draft, setDraft] = useState("");
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const bottomRef = useRef<HTMLDivElement | null>(null);
  const lastIdRef = useRef<number>(0);

  useEffect(() => {
    apiJson<{ data: { batches: Room[] } }>("/api/v1/me/batch-chat")
      .then((r) => {
        setRooms(r.data.batches);
        setActive(r.data.batches[0]?.number ?? null);
      })
      .catch(() => setRooms([]));
  }, []);

  const load = useCallback(
    (quiet: boolean) => {
      if (active === null) return;
      apiJson<{ data: RoomPayload }>(`/api/v1/me/batch-chat/${encodeURIComponent(active)}`)
        .then((r) => {
          setRoom(r.data);
          if (!quiet) setError(null);
        })
        .catch(() => {
          if (!quiet) setError("Chat is unavailable right now — try again shortly.");
        });
    },
    [active],
  );

  useEffect(() => { load(false); }, [load]);

  // A quiet refresh, so an answer arrives without anyone reloading the page.
  useEffect(() => {
    const t = setInterval(() => load(true), 10000);
    return () => clearInterval(t);
  }, [load]);

  // Follow the conversation down only when something new has actually arrived —
  // scrolling on every poll would yank the page away from someone reading back.
  useEffect(() => {
    const newest = room?.messages.at(-1)?.id ?? 0;
    if (newest !== lastIdRef.current) {
      lastIdRef.current = newest;
      bottomRef.current?.scrollIntoView({ behavior: "smooth" });
    }
  }, [room]);

  async function send() {
    const body = draft.trim();
    if (body === "" || active === null || sending) return;

    setSending(true);
    setError(null);
    try {
      await apiJson(`/api/v1/me/batch-chat/${encodeURIComponent(active)}`, {
        method: "POST",
        body: JSON.stringify({ body }),
      });
      setDraft("");
      load(true);
    } catch {
      setError("That did not send. Try again.");
    } finally {
      setSending(false);
    }
  }

  if (rooms === null) {
    return <div className="mx-auto max-w-3xl"><div className="shimmer h-64 rounded-[14px]" /></div>;
  }

  if (rooms.length === 0) {
    return (
      <div className="mx-auto max-w-3xl">
        <h1 className="display text-2xl text-ink">Batch Chat</h1>
        <p className="mt-2 text-sm text-muted">
          You are not seated in a batch yet. Once you join one, its room opens here.
        </p>
      </div>
    );
  }

  return (
    <div className="mx-auto flex max-w-3xl flex-col">
      <div className="flex items-baseline justify-between gap-3">
        <h1 className="display text-2xl text-ink">Batch Chat</h1>
        {room !== null && (
          <span className="mono text-xs text-muted">
            {room.student_count} student{room.student_count === 1 ? "" : "s"} · {room.staff_count} staff
          </span>
        )}
      </div>
      <p className="mt-1 text-sm text-muted">
        Ask your doubt here and anyone on the team can answer — the whole batch sees the reply.
      </p>

      {rooms.length > 1 && (
        <div className="mt-4 flex flex-wrap gap-2">
          {rooms.map((r) => (
            <button
              key={r.number}
              onClick={() => setActive(r.number)}
              className={`rounded-full border px-4 py-1.5 text-xs font-semibold transition ${
                r.number === active ? "border-trust bg-trust text-white" : "border-line bg-white text-ink hover:border-trust"
              }`}
            >
              {r.name}
            </button>
          ))}
        </div>
      )}

      {error !== null && <p className="mt-3 text-sm text-warn">{error}</p>}

      <div className="mt-4 rounded-2xl border border-line bg-white">
        <div className="border-b border-line px-5 py-3">
          <p className="text-xs font-semibold uppercase tracking-widest text-muted">{room?.name ?? active}</p>
        </div>

        <div className="max-h-[55vh] min-h-64 space-y-3 overflow-y-auto px-5 py-4">
          {room === null ? (
            <div className="shimmer h-24 rounded-[10px]" />
          ) : room.messages.length === 0 ? (
            <p className="py-10 text-center text-sm text-muted">
              No questions yet. Ask the first one — no doubt is too small.
            </p>
          ) : (
            room.messages.map((m) => (
              <div key={m.id} className={m.mine ? "flex justify-end" : "flex justify-start"}>
                <div className={`max-w-[80%] rounded-[12px] px-3 py-2 ${m.mine ? "bg-trust text-white" : "bg-paper text-ink"}`}>
                  {!m.mine && (
                    <p className={`text-[11px] font-semibold ${m.from_staff ? "text-trust" : "text-muted"}`}>
                      {m.author}{m.from_staff ? " · BrowseJobs" : ""}
                    </p>
                  )}
                  <p className="whitespace-pre-wrap text-sm leading-relaxed">{m.body}</p>
                  <p className={`mt-0.5 text-[10px] ${m.mine ? "text-white/70" : "text-muted"}`}>{clock(m.sent_at)}</p>
                </div>
              </div>
            ))
          )}
          <div ref={bottomRef} />
        </div>

        <div className="flex items-end gap-2 border-t border-line px-4 py-3">
          <textarea
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              // Enter sends, Shift+Enter starts a line — the habit every chat teaches.
              if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                void send();
              }
            }}
            rows={1}
            maxLength={4000}
            placeholder="Ask your doubt…"
            className="max-h-32 min-h-10 flex-1 resize-y rounded-[12px] border border-line bg-white px-3 py-2 text-sm text-ink outline-none focus:border-trust"
          />
          <button
            onClick={() => void send()}
            disabled={sending || draft.trim() === ""}
            className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-40"
          >
            {sending ? "Sending…" : "Send"}
          </button>
        </div>
      </div>
    </div>
  );
}
