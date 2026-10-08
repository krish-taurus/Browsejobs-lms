"use client";

import { useEffect, useState } from "react";
import { googleReviews } from "@/content/reviews";
import { successStories } from "@/content/success-stories";
import { isPreviewHost } from "@/lib/preview-host";
import { GoogleReviews } from "./GoogleReviews";
import { openWhatsAppShot } from "./WhatsAppMessages";

export function SuccessStories({ fuller = false, tone = "apple" }: { fuller?: boolean; tone?: "apple" | "argus" }) {
  const [preview, setPreview] = useState(false);
  useEffect(() => {
    setPreview(isPreviewHost(window.location.hostname));
  }, []);
  const stories = successStories.filter((story) => story.published || preview);
  const reviews = googleReviews.filter((review) => (review.published || preview) && review.name.trim() !== "" && review.text.trim() !== "");
  const drafts = stories.filter((story) => !story.published);

  if (stories.filter((story) => story.published).length === 0 && reviews.length === 0 && drafts.length === 0) {
    return null;
  }

  return (
    <section id="success-stories" className={tone === "argus" ? "argus-section argus-stories" : "apple-rise bg-white text-center"}>
      <div className={tone === "argus" ? "argus-stories-inner" : "apple-tile"}>
        <h2 className={tone === "argus" ? "argus-h2" : "apple-display mx-auto max-w-[16ch] text-[clamp(2.5rem,5vw,4.5rem)]"}>Success stories.</h2>
        <p className={tone === "argus" ? "argus-body" : "apple-sub mt-4 text-[#424245]"}>
          How people moved into the role. The path is the same: AI interview, counselling, a course, a retake, then hired.
        </p>
        {fuller ? (
          <p className="apple-sub mt-3 text-[17px] text-[#6e6e73]">
            A published story uses the person’s own words.
          </p>
        ) : null}

        {stories.some((story) => story.published) || drafts.length > 0 ? (
          <ul className="mx-auto mt-12 grid max-w-[960px] gap-5 text-left md:grid-cols-2">
            {stories.map((story) => {
              const draft = !story.published;
              return (
                <li key={story.id} className={draft ? "apple-story-card is-draft" : tone === "argus" ? "apple-story-card argus-card" : "apple-story-card"}>
                  <p className="text-[12px] font-medium uppercase tracking-[0.12em] text-[#6e6e73]">
                    {draft ? "Story coming soon" : "Success story"}
                  </p>
                  <h3 className="mt-3 text-[26px] font-semibold tracking-[-0.03em] text-[#1d1d1f]">{story.title}</h3>
                  <p className="mt-3 text-[17px] text-[#1d1d1f]">
                    <span>{story.beforeRole}</span>
                    <span aria-hidden className="px-2 text-[#6e6e73]">
                      →
                    </span>
                    <span>{story.afterRole}</span>
                  </p>
                  {story.path.length > 0 ? (
                    <ol className="mt-4 flex flex-wrap gap-2">
                      {story.path.map((step) => (
                        <li key={step} className="rounded-full bg-[#f5f5f7] px-3 py-1 text-[13px] text-[#424245]">
                          {step}
                        </li>
                      ))}
                    </ol>
                  ) : null}
                  {story.published && story.how ? <p className="mt-4 text-[17px] leading-snug text-[#424245]">{story.how}</p> : null}
                  {story.published && story.quote ? <blockquote className="mt-4 text-[17px] leading-snug text-[#1d1d1f]">“{story.quote}”</blockquote> : null}
                  {story.published && story.name ? <p className="mt-3 text-[15px] text-[#6e6e73]">{story.name}</p> : null}
                  {draft ? <p className="mt-4 text-[15px] leading-snug text-[#6e6e73]">The person’s own words will go here.</p> : null}
                  {story.shotId ? (
                    <button type="button" className="apple-review-more" onClick={() => openWhatsAppShot(story.shotId!)}>
                      See the message
                    </button>
                  ) : null}
                </li>
              );
            })}
          </ul>
        ) : null}

        {reviews.length > 0 ? <GoogleReviews tone={tone} /> : null}
      </div>
    </section>
  );
}
