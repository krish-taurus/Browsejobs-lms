"use client";

import { use, useEffect, useState } from "react";
import { apiJson } from "@/lib/api";

type Video = { lesson_id: number; title: string | null; is_embed: boolean; url: string | null } | null;

export default function LessonVideoPage({ params }: { params: Promise<{ lesson: string }> }) {
  const { lesson } = use(params);
  const [data, setData] = useState<Video>(null);
  const [loading, setLoading] = useState(true);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    apiJson<{ data: Video }>(`/api/v1/me/lessons/${lesson}/video`)
      .then((r) => setData(r.data))
      .catch(() => setMissing(true))
      .finally(() => setLoading(false));
  }, [lesson]);

  if (loading) return <div className="mx-auto max-w-3xl"><div className="shimmer aspect-video rounded-[14px]" /></div>;

  if (missing || !data || !data.url) {
    return (
      <div className="mx-auto max-w-3xl">
        <h1 className="display text-2xl text-ink">Class video</h1>
        <p className="mt-3 text-sm text-muted">The video for this class is being prepared — check back soon.</p>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl">
      <p className="kicker text-trust">Watch</p>
      <h1 className="display mt-2 text-2xl text-ink">{data.title ?? "Class video"}</h1>

      <div className="mt-6 overflow-hidden rounded-2xl border border-line bg-black">
        <div className="aspect-video">
          {data.is_embed ? (
            <iframe src={data.url} className="h-full w-full" allowFullScreen
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" />
          ) : (
            <video src={data.url} controls className="h-full w-full" preload="metadata" />
          )}
        </div>
      </div>
    </div>
  );
}
