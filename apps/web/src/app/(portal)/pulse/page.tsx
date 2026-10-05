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
type ContentRow = { id: number; kind: string; title: string; url: string; view_count: number | null; published_at: string };
type PulseData = {
  celebrations: CelebrationRow[];
  digest: {
    date: string;
    narrative: string;
    sources: { id: number; title: string; url: string; source_name: string }[];
  } | null;
  content: ContentRow[];
};

const KIND_LABEL: Record<string, string> = {
  youtube: "Video",
  podcast: "Podcast",
  instagram: "Post",
};

/** Each kind gets its own wash + icon when there's no thumbnail to show (podcast, post). */
const KIND_STYLE: Record<string, { wash: string; icon: string }> = {
  youtube: { wash: "from-trust/25 to-trust/5", icon: "▶" },
  podcast: { wash: "from-violet-500/25 to-violet-500/5", icon: "🎙" },
  instagram: { wash: "from-pink-500/25 to-pink-500/5", icon: "◎" },
};

/** A youtu.be / youtube.com URL's video id, or null for anything else. */
function youtubeId(url: string): string | null {
  try {
    const u = new URL(url);
    if (u.hostname === "youtu.be") return u.pathname.slice(1) || null;
    if (u.hostname.endsWith("youtube.com")) {
      if (u.pathname === "/watch") return u.searchParams.get("v");
      if (u.pathname.startsWith("/embed/")) return u.pathname.split("/")[2] ?? null;
      if (u.pathname.startsWith("/shorts/")) return u.pathname.split("/")[2] ?? null;
    }
    return null;
  } catch {
    return null;
  }
}

function viewsLabel(n: number): string {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M views`;
  if (n >= 1_000) return `${(n / 1_000).toFixed(1)}k views`;
  return `${n} view${n === 1 ? "" : "s"}`;
}

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

  function openContent(item: ContentRow) {
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
      <section className="mt-8 pb-10">
        <p className="kicker text-trust">Content Hub</p>
        {data?.content.length ? (
          <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            {data.content.map((item) => {
              const ytId = item.kind === "youtube" ? youtubeId(item.url) : null;
              const style = KIND_STYLE[item.kind] ?? KIND_STYLE.podcast;
              return (
                <button
                  key={item.id}
                  onClick={() => openContent(item)}
                  className="group overflow-hidden rounded-2xl border border-line bg-white text-left shadow-[0_1px_2px_rgba(16,24,40,0.03)] transition hover:shadow-[0_4px_16px_-6px_rgba(16,24,40,0.12)]"
                >
                  <div className="relative aspect-video w-full overflow-hidden bg-paper">
                    {ytId ? (
                      // eslint-disable-next-line @next/next/no-img-element -- external thumbnail host, not a static asset
                      <img
                        src={`https://i.ytimg.com/vi/${ytId}/hqdefault.jpg`}
                        alt={item.title}
                        className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                      />
                    ) : (
                      <div className={`flex h-full w-full items-center justify-center bg-gradient-to-br ${style.wash}`}>
                        <span className="text-3xl">{style.icon}</span>
                      </div>
                    )}
                    <span className="mono absolute left-2.5 top-2.5 rounded-full bg-black/60 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-widest text-white backdrop-blur-sm">
                      {KIND_LABEL[item.kind] ?? item.kind}
                    </span>
                  </div>
                  <div className="p-3.5">
                    <p className="line-clamp-2 text-sm font-semibold leading-snug text-ink">{item.title}</p>
                    <p className="mono mt-1.5 text-[11px] text-muted">
                      {item.view_count ? `${viewsLabel(item.view_count)} on BrowseJobs` : "Open ↗"}
                    </p>
                  </div>
                </button>
              );
            })}
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
