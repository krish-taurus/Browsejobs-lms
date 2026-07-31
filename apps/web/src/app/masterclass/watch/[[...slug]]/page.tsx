"use client";

import Link from "next/link";
import { use, useCallback, useEffect, useRef, useState } from "react";
import { apiJson } from "@/lib/api";
import { Wordmark } from "@/components/brand/Wordmark";

type WatchInfo = {
  course: { name: string; slug: string; tagline: string | null };
  video_url: string | null;
  status: "waiting" | "live" | "unavailable";
  starts_at: string;
  offset_seconds: number;
  duration_minutes: number;
  server_now: string;
};

/** Seconds between two ISO timestamps, clamped at 0. */
function secondsUntil(target: string, from: Date) {
  return Math.max(0, Math.floor((new Date(target).getTime() - from.getTime()) / 1000));
}

function pad(n: number) {
  return String(n).padStart(2, "0");
}

/** YouTube video id from watch/share/embed URLs, else null. */
function youTubeId(url: string): string | null {
  const m = url.match(
    /(?:youtube\.com\/(?:watch\?v=|embed\/|live\/)|youtu\.be\/)([\w-]{6,})/,
  );
  return m ? m[1] : null;
}

export default function MasterclassWatch({
  params,
}: {
  params: Promise<{ slug?: string[] }>;
}) {
  const { slug } = use(params);
  const courseSlug = slug?.[0];

  const [info, setInfo] = useState<WatchInfo | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [countdown, setCountdown] = useState<number>(0);
  const [joined, setJoined] = useState(false);
  // Server-vs-browser clock skew, so the countdown trusts the server.
  const skewRef = useRef(0);
  const videoRef = useRef<HTMLVideoElement>(null);

  const load = useCallback(async () => {
    try {
      const data = await apiJson<WatchInfo>(
        `/api/v1/masterclass/watch/${courseSlug ?? ""}`,
      );
      skewRef.current = new Date(data.server_now).getTime() - Date.now();
      setInfo(data);
      setCountdown(
        secondsUntil(data.starts_at, new Date(Date.now() + skewRef.current)),
      );
    } catch {
      setError("This masterclass is not available right now. Please check the link or try again later.");
    }
  }, [courseSlug]);

  useEffect(() => {
    void load();
  }, [load]);

  // Tick the countdown; when it hits zero re-ask the server (waiting → live).
  useEffect(() => {
    if (!info || info.status !== "waiting") return;
    const t = setInterval(() => {
      const left = secondsUntil(
        info.starts_at,
        new Date(Date.now() + skewRef.current),
      );
      setCountdown(left);
      if (left <= 0) void load();
    }, 1000);
    return () => clearInterval(t);
  }, [info, load]);

  // Joining mid-stream: recompute the live offset at click time so the player
  // lands exactly where the "broadcast" is now.
  const liveOffsetNow = useCallback(() => {
    if (!info) return 0;
    const started =
      (Date.now() + skewRef.current - new Date(info.starts_at).getTime()) / 1000;
    return Math.max(0, Math.floor(started));
  }, [info]);

  const h = Math.floor(countdown / 3600);
  const m = Math.floor((countdown % 3600) / 60);
  const s = countdown % 60;

  return (
    <div className="min-h-screen px-5 py-10">
      <div className="mx-auto w-full max-w-3xl">
        <Link href="/" className="flex justify-center" aria-label="BrowseJobs home">
          <Wordmark />
        </Link>

        <div className="mt-8 rounded-2xl border border-line bg-white p-7 shadow-sm">
          {error && <p className="text-sm text-warn">{error}</p>}

          {info && (
            <>
              <div className="flex flex-wrap items-center gap-2">
                <p className="kicker text-trust">Free Masterclass</p>
                {info.status === "live" && (
                  <span className="rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-white">
                    ● Live
                  </span>
                )}
              </div>
              <h1 className="display mt-2 text-2xl text-ink">{info.course.name}</h1>
              {info.course.tagline && (
                <p className="mt-1 text-sm text-muted">{info.course.tagline}</p>
              )}

              {info.status === "unavailable" && (
                <p className="mt-6 text-sm text-muted">
                  Today&apos;s session details are being prepared. Our team will send you the
                  session link on WhatsApp — or{" "}
                  <Link href="/masterclass" className="text-trust hover:underline">
                    book your seat here
                  </Link>
                  .
                </p>
              )}

              {info.status === "waiting" && (
                <div className="mt-8 text-center">
                  <p className="text-sm text-muted">Today&apos;s session starts in</p>
                  <p className="mono mt-2 text-5xl font-bold text-ink">
                    {pad(h)}:{pad(m)}:{pad(s)}
                  </p>
                  <p className="mt-3 text-sm text-muted">
                    {new Date(info.starts_at).toLocaleString(undefined, {
                      weekday: "long",
                      hour: "numeric",
                      minute: "2-digit",
                    })}{" "}
                    — keep this page open, it starts automatically.
                  </p>
                </div>
              )}

              {info.status === "live" && !joined && (
                <div className="mt-8 text-center">
                  <p className="text-sm text-muted">
                    The session is in progress — join now, you&apos;ll drop straight into the
                    live stream.
                  </p>
                  <button
                    onClick={() => setJoined(true)}
                    className="mt-4 rounded-full bg-trust px-8 py-3 font-semibold text-white transition-colors hover:bg-deep"
                  >
                    ▶ Join the Masterclass
                  </button>
                </div>
              )}

              {info.status === "live" && joined && info.video_url && (
                <div className="mt-6 overflow-hidden rounded-xl bg-black">
                  {youTubeId(info.video_url) ? (
                    <iframe
                      className="aspect-video w-full"
                      src={`https://www.youtube.com/embed/${youTubeId(info.video_url)}?start=${liveOffsetNow()}&autoplay=1&controls=0&rel=0&modestbranding=1&disablekb=1`}
                      title={info.course.name}
                      allow="autoplay; encrypted-media"
                      allowFullScreen
                    />
                  ) : (
                    <video
                      ref={videoRef}
                      className="aspect-video w-full"
                      src={info.video_url}
                      autoPlay
                      playsInline
                      controls={false}
                      onLoadedMetadata={() => {
                        if (videoRef.current) {
                          videoRef.current.currentTime = liveOffsetNow();
                        }
                      }}
                    />
                  )}
                </div>
              )}

              {(info.status === "live" || info.status === "waiting") && (
                <div className="mt-8 rounded-xl border border-line bg-paper p-5 text-center">
                  <p className="text-sm font-semibold text-ink">
                    Ready to go deeper? The 7-day free bootcamp is the next step.
                  </p>
                  <p className="mt-1 text-sm text-muted">
                    Reply on WhatsApp after the session, or create your account to get started.
                  </p>
                  <Link
                    href="/register"
                    className="mt-3 inline-block rounded-full bg-trust px-6 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-deep"
                  >
                    Create your account
                  </Link>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
