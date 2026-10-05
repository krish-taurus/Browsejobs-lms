"use client";

import Link from "next/link";
import { useCallback, useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiJson } from "@/lib/api";

/**
 * The dashboard's promo row (candidate request, Aug 2026, "like a Flipkart
 * banner") — BrowseJobs' own offers/features and podcast content, entirely
 * CRM-managed (Dashboard Banners screen). Several cards side by side,
 * horizontally scrollable/swipeable with native scroll-snap (no timer, no
 * JS-driven animation — the same simple mechanic Flipkart's own row uses),
 * and completely hidden when there's nothing to show.
 */

type Banner = {
  id: number;
  title: string;
  subtitle: string | null;
  image_url: string;
  link_url: string | null;
  link_label: string | null;
};

const CARD_CLASS =
  "relative aspect-video w-[78%] shrink-0 snap-start overflow-hidden rounded-2xl border border-line bg-ink sm:w-[46%] lg:w-[31%]";

function BannerCard({ banner }: { banner: Banner }) {
  const isInternal = banner.link_url?.startsWith("/") ?? false;
  // eslint-disable-next-line @next/next/no-img-element -- external Spaces URL, not a local/optimizable asset
  const image = <img src={banner.image_url} alt={banner.title} className="h-full w-full object-cover" draggable={false} />;

  if (!banner.link_url) return <div className={CARD_CLASS}>{image}</div>;

  return isInternal ? (
    <Link href={banner.link_url} className={CARD_CLASS}>{image}</Link>
  ) : (
    <a href={banner.link_url} target="_blank" rel="noopener noreferrer" className={CARD_CLASS}>{image}</a>
  );
}

export function DashboardBannerCarousel() {
  const { data } = useQuery({
    queryKey: ["me", "banners"],
    queryFn: () => apiJson<{ data: Banner[] }>("/api/v1/me/banners"),
    staleTime: 5 * 60 * 1000,
  });

  const banners = data?.data ?? [];
  const scrollerRef = useRef<HTMLDivElement | null>(null);
  const [active, setActive] = useState(0);

  const onScroll = useCallback(() => {
    const el = scrollerRef.current;
    const first = el?.children[0] as HTMLElement | undefined;
    if (!el || !first) return;
    const cardWidth = first.offsetWidth + 12; // matches the row's gap-3
    setActive(Math.round(el.scrollLeft / cardWidth));
  }, []);

  function goTo(i: number) {
    const el = scrollerRef.current;
    const first = el?.children[0] as HTMLElement | undefined;
    if (!el || !first) return;
    el.scrollTo({ left: i * (first.offsetWidth + 12), behavior: "smooth" });
  }

  if (banners.length === 0) return null;

  return (
    <>
      <p className="kicker mt-6 text-trust">Our latest podcast</p>
      <div
        ref={scrollerRef}
        onScroll={onScroll}
        className="mt-2 flex snap-x snap-mandatory gap-3 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        {banners.map((b) => (
          <BannerCard key={b.id} banner={b} />
        ))}
      </div>

      {banners.length > 1 && (
        <div className="mt-2 flex justify-center gap-1.5">
          {banners.map((b, i) => (
            <button
              key={b.id}
              type="button"
              onClick={() => goTo(i)}
              aria-label={`Go to banner ${i + 1}`}
              className={`h-1.5 rounded-full transition-all ${i === active ? "w-5 bg-trust" : "w-1.5 bg-line"}`}
            />
          ))}
        </div>
      )}
    </>
  );
}
