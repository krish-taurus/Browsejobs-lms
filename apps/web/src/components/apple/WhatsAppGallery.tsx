"use client";

import { useEffect, useId, useState } from "react";
import type { WhatsAppShot } from "@/content/whatsapp-shots";

export function WhatsAppGallery({ shots }: { shots: WhatsAppShot[] }) {
  const [open, setOpen] = useState<string | null>(null);
  const titleId = useId();
  const active = shots.find((shot) => shot.id === open) ?? null;

  useEffect(() => {
    if (!active) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(null);
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [active]);

  if (shots.length === 0) return null;

  return (
    <div className="mt-14">
      <h3 className="text-[21px] font-semibold tracking-[-0.02em] text-[#1d1d1f]">WhatsApp</h3>
      <p className="mx-auto mt-2 max-w-[36rem] text-[17px] text-[#424245]">Messages from people who went through the path.</p>
      <ul className="apple-snap mt-8">
        {shots.map((shot) => (
          <li key={shot.id}>
            <button type="button" className="apple-phone" onClick={() => setOpen(shot.id)} aria-label={`Open screenshot: ${shot.alt}`}>
              <span className="apple-phone-screen">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img src={shot.src} alt="" style={{ objectPosition: shot.objectPosition ?? "center top" }} />
                {shot.blurRegion ? <span aria-hidden className="apple-blur-region" style={shot.blurRegion} /> : null}
              </span>
            </button>
          </li>
        ))}
      </ul>
      {active ? (
        <div className="apple-lightbox" role="dialog" aria-modal="true" aria-labelledby={titleId}>
          <button type="button" className="apple-lightbox-dismiss" aria-label="Close screenshot" onClick={() => setOpen(null)} />
          <figure className="apple-lightbox-frame">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={active.src} alt={active.alt} style={{ objectPosition: active.objectPosition ?? "center top" }} />
            {active.blurRegion ? <span aria-hidden className="apple-blur-region" style={active.blurRegion} /> : null}
            <figcaption id={titleId} className="sr-only">
              {active.alt}
            </figcaption>
          </figure>
        </div>
      ) : null}
    </div>
  );
}
