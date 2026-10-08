"use client";

import { useEffect, useId, useState } from "react";
import Link from "next/link";
import { showcaseShots, whatsappShots, type WhatsAppShot } from "@/content/whatsapp-shots";

const OPEN = "bj-open-whatsapp";

export function openWhatsAppShot(id: string) {
  window.dispatchEvent(new CustomEvent(OPEN, { detail: id }));
}

function ShotFigure({ shot, onOpen }: { shot: WhatsAppShot; onOpen: (id: string) => void }) {
  return (
    <figure className="apple-shot">
      <button type="button" className="apple-phone is-shot" onClick={() => onOpen(shot.id)}>
        <span className="apple-phone-screen">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={shot.src}
            alt={shot.alt}
            width={shot.width}
            height={shot.height}
            loading="lazy"
            decoding="async"
          />
        </span>
      </button>
      <figcaption className="apple-shot-caption">
        <span className="apple-shot-headline">{shot.headline}</span>
        {shot.person ? <span className="apple-shot-name">{shot.person}</span> : null}
      </figcaption>
    </figure>
  );
}

export function WhatsAppMessages({ variant }: { variant: "carousel" | "grid" }) {
  const shots = showcaseShots(whatsappShots.filter((shot) => shot.published && shot.src.trim() !== ""));
  const [open, setOpen] = useState<string | null>(null);
  const titleId = useId();
  const active = shots.find((shot) => shot.id === open) ?? null;

  useEffect(() => {
    const onOpen = (event: Event) => {
      const id = (event as CustomEvent<string>).detail;
      if (whatsappShots.some((shot) => shot.published && shot.id === id)) setOpen(id);
    };
    window.addEventListener(OPEN, onOpen);
    return () => window.removeEventListener(OPEN, onOpen);
  }, []);

  useEffect(() => {
    if (!active) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(null);
    };
    document.addEventListener("keydown", onKey);
    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = previous;
    };
  }, [active]);

  if (shots.length === 0) return null;

  return (
    <section id="real-messages" className={variant === "carousel" ? "apple-messages apple-rise text-center text-white" : "apple-tile apple-rise bg-[#f5f5f7] text-center"}>
      <div className={variant === "carousel" ? "apple-tile" : undefined}>
        <h2 className="apple-display mx-auto max-w-[16ch] text-[clamp(2.5rem,5vw,4.5rem)]">Real messages from our students.</h2>
        <p className={`apple-sub mt-4 ${variant === "carousel" ? "text-[#a1a1a6]" : "text-[#424245]"}`}>
          Words from people who wrote in after they moved.
        </p>
        {variant === "carousel" ? (
          <ul className="apple-snap apple-snap-dark mt-12">
            {shots.map((shot) => (
              <li key={shot.id}>
                <ShotFigure shot={shot} onOpen={setOpen} />
              </li>
            ))}
          </ul>
        ) : (
          <ul className="apple-shot-grid mt-12">
            {shots.map((shot) => (
              <li key={shot.id}>
                <ShotFigure shot={shot} onOpen={setOpen} />
              </li>
            ))}
          </ul>
        )}
        {variant === "carousel" ? (
          <div className="mt-10">
            <Link href="/#interview-start" className="apple-pill">
              Take the free AI interview
            </Link>
          </div>
        ) : null}
      </div>
      {active ? (
        <div className="apple-lightbox" role="dialog" aria-modal="true" aria-labelledby={titleId}>
          <button type="button" className="apple-lightbox-dismiss" aria-label="Close screenshot" onClick={() => setOpen(null)} />
          <div className="apple-lightbox-panel">
            <button type="button" className="apple-lightbox-close" onClick={() => setOpen(null)}>
              Close
            </button>
            <figure className="apple-lightbox-frame is-shot">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={active.src} alt={active.alt} width={active.width} height={active.height} />
              <figcaption id={titleId}>
                {active.headline}
                {active.person ? ` · ${active.person}` : ""}
              </figcaption>
            </figure>
          </div>
        </div>
      ) : null}
    </section>
  );
}
