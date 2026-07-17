"use client";

import { useCallback, useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { EmptyState } from "@/components/ui/EmptyState";

type Guidance = { intro: string; actions: string[] } | null;
type CelebrationRow = {
  id: number;
  display: string;
  role_title: string;
  company: string | null;
  published_at: string;
  is_me: boolean;
  guidance: Guidance;
};
type PulseData = {
  celebrations: CelebrationRow[];
  digest: {
    date: string;
    narrative: string;
    sources: { id: number; title: string; url: string; source_name: string }[];
  } | null;
  content: { id: number; kind: string; title: string; url: string; published_at: string }[];
};

const KIND_LABEL: Record<string, string> = {
  youtube: "▶ Video",
  podcast: "🎙 Podcast",
  instagram: "◎ Post",
};

export default function PulsePage() {
  const [data, setData] = useState<PulseData | null>(null);
  const [loading, setLoading] = useState(true);
  const [openGuidance, setOpenGuidance] = useState<Record<number, Guidance | "loading">>({});

  const load = useCallback(async () => {
    try {
      const res = await apiJson<{ data: PulseData }>("/api/v1/me/pulse");
      setData(res.data);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function showPath(id: number) {
    setOpenGuidance((g) => ({ ...g, [id]: "loading" }));
    try {
      const res = await apiJson<{ data: { intro: string; actions: string[] } }>(
        `/api/v1/me/pulse/celebrations/${id}/guidance`,
        { method: "POST" },
      );
      setOpenGuidance((g) => ({ ...g, [id]: res.data }));
    } catch {
      setOpenGuidance((g) => ({ ...g, [id]: null }));
    }
  }

  function openContent(item: PulseData["content"][number]) {
    void apiJson(`/api/v1/me/pulse/content/${item.id}/viewed`, { method: "POST" }).catch(() => {});
    window.open(item.url, "_blank", "noopener");
  }

  if (loading) {
    return (
      <div className="mx-auto max-w-4xl space-y-4">
        {Array.from({ length: 3 }).map((_, i) => (
          <div key={i} className="shimmer h-40 rounded-2xl" />
        ))}
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-4xl">
      <p className="kicker text-trust">Pulse</p>
      <h1 className="display mt-2 text-3xl text-ink">The market, your batch, our content</h1>

      {/* Market Pulse digest */}
      <section className="mt-8 rounded-2xl bg-ink p-6 text-white">
        <div className="flex items-center justify-between">
          <p className="kicker text-sky/70">Market Pulse</p>
          {data?.digest && <span className="mono text-xs text-sky/50">{data.digest.date}</span>}
        </div>
        {data?.digest ? (
          <>
            <p className="mt-3 whitespace-pre-line text-sm leading-relaxed text-sky/90">
              {data.digest.narrative}
            </p>
            {data.digest.sources.length > 0 && (
              <div className="mt-4 flex flex-wrap gap-2">
                {data.digest.sources.map((s) => (
                  <a
                    key={s.id}
                    href={s.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="mono rounded-full border border-white/15 px-3 py-1 text-[11px] text-sky/70 hover:border-trust hover:text-white"
                  >
                    {s.source_name} ↗
                  </a>
                ))}
              </div>
            )}
          </>
        ) : (
          <p className="mt-3 text-sm text-sky/60">
            Today&apos;s digest lands each morning once the market desk curates it.
          </p>
        )}
      </section>

      {/* Celebration wall */}
      <section className="mt-8">
        <p className="kicker text-verify">Wins on your course</p>
        {data?.celebrations.length ? (
          <div className="mt-4 space-y-4">
            {data.celebrations.map((c) => {
              const guidance = c.guidance ?? openGuidance[c.id];
              return (
                <article key={c.id} className="rounded-2xl border border-line bg-white p-6">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-ink">
                      🎉 <span className="font-semibold">{c.display}</span> received an offer as{" "}
                      <span className="font-semibold">{c.role_title}</span>
                      {c.company && <> at {c.company}</>}!
                    </p>
                    <span className="mono text-xs text-muted">{c.published_at}</span>
                  </div>

                  {!c.is_me && (
                    <div className="mt-4">
                      {guidance === undefined && (
                        <button
                          onClick={() => void showPath(c.id)}
                          className="rounded-full bg-trust px-5 py-2 text-sm font-semibold text-white hover:bg-deep"
                        >
                          Your path to the same →
                        </button>
                      )}
                      {guidance === "loading" && <div className="shimmer h-24 rounded-[10px]" />}
                      {guidance && guidance !== "loading" && (
                        <div className="rounded-[10px] bg-sky p-4">
                          <p className="text-sm font-semibold text-deep">{guidance.intro}</p>
                          <ul className="mt-2.5 space-y-2">
                            {guidance.actions.map((a) => (
                              <li key={a} className="flex gap-2.5 text-sm text-ink2">
                                <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-trust" />
                                {a}
                              </li>
                            ))}
                          </ul>
                        </div>
                      )}
                    </div>
                  )}
                </article>
              );
            })}
          </div>
        ) : (
          <div className="mt-4">
            <EmptyState
              title="The wall is waiting for its first win"
              body="When someone on your course accepts an offer (and consents to share), it's celebrated here — with your personal path to the same."
            />
          </div>
        )}
      </section>

      {/* Content Hub */}
      <section className="mt-8">
        <p className="kicker text-trust">Content Hub</p>
        {data?.content.length ? (
          <div className="mt-4 divide-y divide-line rounded-2xl border border-line bg-white">
            {data.content.map((item) => (
              <button
                key={item.id}
                onClick={() => openContent(item)}
                className="flex w-full items-center gap-4 px-5 py-4 text-left transition-colors hover:bg-paper"
              >
                <span className="mono shrink-0 text-xs text-muted">
                  {KIND_LABEL[item.kind] ?? item.kind}
                </span>
                <span className="flex-1 font-medium text-ink">{item.title}</span>
                <span className="text-trust">↗</span>
              </button>
            ))}
          </div>
        ) : (
          <div className="mt-4">
            <EmptyState
              title="New drops land here"
              body="Podcast episodes, videos and posts from BrowseJobs appear the moment they release."
            />
          </div>
        )}
      </section>
    </div>
  );
}
