"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiJson } from "@/lib/api";
import { StoryCard, type Story } from "@/components/courses/StoryCard";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { Kicker } from "@/components/brand/Kicker";
import { ScrollReveal } from "@/components/motion/ScrollReveal";

type HomeStory = Story & { course_slug: string | null; course_name: string | null };

/**
 * Home-page success stories across all courses (Platform Spec §3). Renders the
 * placement-story cards; samples carry a visible "Sample" badge + note so no
 * visitor is misled. Degrades to nothing when there are no published stories.
 */
export function SuccessStories() {
  const [stories, setStories] = useState<HomeStory[] | null>(null);

  useEffect(() => {
    let live = true;
    apiJson<{ data: HomeStory[] }>("/api/v1/success-stories")
      .then((r) => live && setStories(r.data))
      .catch(() => live && setStories([]));
    return () => {
      live = false;
    };
  }, []);

  if (!stories || stories.length === 0) return null;
  const hasSample = stories.some((s) => s.is_sample);

  return (
    <section className="mx-auto max-w-6xl px-5 py-20 md:py-28">
      <ScrollReveal className="mb-12 text-center">
        <Kicker className="text-verify">Success stories</Kicker>
        <h2 className="display mx-auto mt-3 max-w-2xl text-3xl text-ink md:text-5xl">From stuck → hired</h2>
        <p className="mx-auto mt-4 max-w-xl text-ink2/70">
          Where learners started, the package and company they landed, and the rounds they cleared.
        </p>
      </ScrollReveal>

      <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        {stories.map((s, i) => (
          <StoryCard
            key={i}
            story={s}
            cta={
              s.course_slug ? (
                <Link
                  href={`/courses/${s.course_slug}`}
                  className="rounded-full border border-trust/40 bg-trust/10 px-4 py-2 text-sm font-semibold text-trust transition-colors hover:bg-trust/15"
                >
                  See the {s.course_name} course →
                </Link>
              ) : null
            }
          />
        ))}
      </div>

      <div className="mt-6">
        <Disclaimer />
      </div>
      {hasSample && (
        <p className="mono mt-2 text-center text-xs text-muted">
          Cards marked “Sample” are illustrative — real, consented student stories replace them as they’re published.
        </p>
      )}
    </section>
  );
}
