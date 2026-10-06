"use client";

import { useEffect, useState } from "react";
import { openLeadModal } from "@/components/landing/leadModalBus";

/** Mobile bar after the hero. Same action as the page's one primary CTA. */
export function ScreenBar() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const onScroll = () => setVisible(window.scrollY > 520);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  if (!visible) return null;

  return (
    <div className="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white/95 p-3 backdrop-blur-md md:hidden">
      <button
        type="button"
        onClick={() => openLeadModal({ variant: "counselling" })}
        className="w-full rounded-full bg-trust py-3 font-semibold text-white"
      >
        Start free AI interview
      </button>
    </div>
  );
}
