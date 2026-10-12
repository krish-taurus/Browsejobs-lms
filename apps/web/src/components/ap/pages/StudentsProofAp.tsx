"use client";

import Image from "next/image";
import { useCallback, useEffect, useId, useRef, useState, type ReactNode } from "react";
import { GOOGLE_LISTING_URL, GOOGLE_RATING_LINE, GOOGLE_RATING_VALUE, googleReviews, reviewExcerpt, type GoogleReview } from "@/content/reviews";
import { successStories } from "@/content/success-stories";
import { showcaseShots, whatsappShots, type WhatsAppShot } from "@/content/whatsapp-shots";
import { isPreviewHost } from "@/lib/preview-host";

/** A horizontally scrolling Apple-style gallery with previous / next paddles. */
function Gallery({ label, className, children }: { label: string; className?: string; children: ReactNode }) {
  const track = useRef<HTMLUListElement | null>(null);
  const [edge, setEdge] = useState({ start: true, end: false });

  const measure = useCallback(() => {
    const el = track.current;
    if (!el) return;
    setEdge({ start: el.scrollLeft <= 4, end: el.scrollLeft + el.clientWidth >= el.scrollWidth - 4 });
  }, []);

  useEffect(() => {
    measure();
    window.addEventListener("resize", measure);
    return () => window.removeEventListener("resize", measure);
  }, [measure]);

  const page = (dir: 1 | -1) => {
    const el = track.current;
    if (!el) return;
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    el.scrollBy({ left: dir * Math.max(280, el.clientWidth * 0.8), behavior: reduce ? "auto" : "smooth" });
  };

  return (
    <div className={`st-gallery-wrap${className ? ` ${className}` : ""}`}>
      <ul ref={track} className="st-gallery" aria-label={label} onScroll={measure}>
        {children}
      </ul>
      <div className="st-paddles">
        <button type="button" className="st-paddle" aria-label={`Previous ${label.toLowerCase()}`} disabled={edge.start} onClick={() => page(-1)}>
          <svg viewBox="0 0 16 16" aria-hidden="true">
            <path d="M10 3L5 8l5 5" />
          </svg>
        </button>
        <button type="button" className="st-paddle" aria-label={`Next ${label.toLowerCase()}`} disabled={edge.end} onClick={() => page(1)}>
          <svg viewBox="0 0 16 16" aria-hidden="true">
            <path d="M6 3l5 5-5 5" />
          </svg>
        </button>
      </div>
    </div>
  );
}

function Stars({ count }: { count: number }) {
  return (
    <span className="stars" aria-hidden="true">
      {"★".repeat(count)}
      {count < 5 ? <span className="off">{"★".repeat(5 - count)}</span> : null}
    </span>
  );
}

function ReviewCard({ review }: { review: GoogleReview }) {
  const [open, setOpen] = useState(false);
  const { excerpt, truncated } = reviewExcerpt(review.text);
  return (
    <article className="review st-review">
      <Stars count={review.stars} />
      <span className="sr-only">{`${review.stars} out of 5`}</span>
      <p>{open ? review.text : excerpt}</p>
      {truncated ? (
        <button type="button" className="st-textbtn" aria-expanded={open} onClick={() => setOpen((value) => !value)}>
          {open ? "Show less" : "Read more"}
        </button>
      ) : null}
      <span className="who">
        {review.name}
        <small>Google review</small>
      </span>
    </article>
  );
}

/**
 * Success stories, Google reviews and the WhatsApp messages for /students.
 * Unpublished stories render only on preview hosts (localhost, quick tunnels).
 * "See the message" on a story opens the matching screenshot.
 */
export function StudentsProofAp() {
  const [preview, setPreview] = useState(false);
  const [open, setOpen] = useState<string | null>(null);
  const titleId = useId();
  const closeRef = useRef<HTMLButtonElement | null>(null);
  const returnTo = useRef<HTMLElement | null>(null);

  useEffect(() => {
    setPreview(isPreviewHost(window.location.hostname));
  }, []);

  const stories = successStories.filter((story) => story.published || preview);
  const reviews = googleReviews.filter((review) => review.published && review.name.trim() !== "" && review.text.trim() !== "");
  const shots = showcaseShots(whatsappShots.filter((shot) => shot.published && shot.src.trim() !== ""));
  const active: WhatsAppShot | null = shots.find((shot) => shot.id === open) ?? null;

  const show = (id: string) => {
    returnTo.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    setOpen(id);
  };

  useEffect(() => {
    if (!active) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(null);
    };
    document.addEventListener("keydown", onKey);
    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    closeRef.current?.focus();
    const back = returnTo.current;
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = previous;
      back?.focus();
    };
  }, [active]);

  return (
    <>
      <section className="s-paper chapter" id="success-stories">
        <div className="wrap center">
          <h2 className="h-section">Success stories.</h2>
          <p className="lead">How people moved into the role. The path is the same: AI interview, counselling, a course, a retake, then hired.</p>
          <p className="body">A published story uses the person&apos;s own words.</p>
          {stories.length > 0 ? (
            <div className="stories" data-reveal-kids="">
              {stories.map((story) => {
                const draft = !story.published;
                return (
                  <article key={story.id} className={`tile tile-pad story${draft ? " st-draft" : ""}`}>
                    <p className="eyebrow-sm">{draft ? "Story coming soon" : "Success story"}</p>
                    <h3 className="h-card">{story.title}</h3>
                    <p className="route">
                      {story.beforeRole} <i aria-hidden="true"></i> {story.afterRole}
                    </p>
                    {story.path.length > 0 ? (
                      <ol className="st-chips">
                        {story.path.map((step) => (
                          <li key={step}>{step}</li>
                        ))}
                      </ol>
                    ) : null}
                    {!draft && story.how ? <p className="body">{story.how}</p> : null}
                    {!draft && story.quote ? <blockquote>“{story.quote}”</blockquote> : null}
                    {!draft && story.name ? <p className="who">{story.name}</p> : null}
                    {draft ? <p className="body">The person&apos;s own words will go here.</p> : null}
                    {story.shotId && shots.some((shot) => shot.id === story.shotId) ? (
                      <button type="button" className="more st-linkbtn" onClick={() => show(story.shotId!)}>
                        See the message <span className="chev" aria-hidden="true">›</span>
                      </button>
                    ) : null}
                  </article>
                );
              })}
            </div>
          ) : null}
          {reviews.length > 0 ? (
            <div className="rating">
              <span className="big" aria-hidden="true">
                {GOOGLE_RATING_VALUE}
              </span>
              <div className="meta">
                <Stars count={5} />
                <a className="more" href={GOOGLE_LISTING_URL} target="_blank" rel="noopener noreferrer">
                  {GOOGLE_RATING_LINE} <span className="chev" aria-hidden="true">›</span>
                </a>
              </div>
            </div>
          ) : null}
        </div>
        {reviews.length > 0 ? (
          <Gallery label="Google reviews">
            {reviews.map((review) => (
              <li key={review.id}>
                <ReviewCard review={review} />
              </li>
            ))}
          </Gallery>
        ) : null}
      </section>

      {shots.length > 0 ? (
        <section className="s-black chapter" id="real-messages">
          <div className="wrap center">
            <h2 className="h-section">Real messages from our students.</h2>
            <p className="lead">Words from people who wrote in after they moved.</p>
          </div>
          <Gallery label="Messages" className="st-shots">
            {shots.map((shot) => (
              <li key={shot.id}>
                <figure className="st-shot">
                  <button type="button" className="st-shot-phone" aria-label={`Open the message: ${shot.headline}`} onClick={() => show(shot.id)}>
                    <Image src={shot.src} alt={shot.alt} width={shot.width} height={shot.height} sizes="380px" />
                  </button>
                  <figcaption>
                    <b>{shot.headline}</b>
                    {shot.person ? <small>{shot.person}</small> : null}
                  </figcaption>
                </figure>
              </li>
            ))}
          </Gallery>
          <div className="wrap center">
            <div className="cta-row">
              <a className="btn btn-primary" href="#interview-start">
                Take the free AI interview
              </a>
            </div>
          </div>
        </section>
      ) : null}

      {active ? (
        <div className="st-lightbox" role="dialog" aria-modal="true" aria-labelledby={titleId}>
          <button type="button" className="st-lightbox-dismiss" aria-label="Close screenshot" tabIndex={-1} onClick={() => setOpen(null)} />
          <div className="st-lightbox-panel">
            <button ref={closeRef} type="button" className="btn btn-sm st-lightbox-close" onClick={() => setOpen(null)}>
              Close
            </button>
            <figure>
              <Image src={active.src} alt={active.alt} width={active.width} height={active.height} sizes="(min-width: 834px) 520px, 92vw" />
              <figcaption id={titleId}>
                {active.headline}
                {active.person ? ` · ${active.person}` : ""}
              </figcaption>
            </figure>
          </div>
        </div>
      ) : null}
    </>
  );
}
