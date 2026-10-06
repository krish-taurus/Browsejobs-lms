"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

type NavLink = { href: string; label: string };

/**
 * Public-site mobile menu (md:hidden). The desktop nav links are hidden on small
 * screens, so this hamburger surfaces every destination + Login/Sign up. Closes
 * on Escape, on backdrop tap, and on navigation.
 */
export function MobileMenu({
  links,
  tone = "light",
}: {
  links: readonly NavLink[];
  tone?: "light" | "night";
}) {
  const night = tone === "night";
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    window.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    return () => {
      window.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
    };
  }, [open]);

  return (
    <div className="lg:hidden">
      <button
        type="button"
        aria-label={open ? "Close menu" : "Open menu"}
        aria-expanded={open}
        onClick={() => setOpen((o) => !o)}
        className={`inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold shadow-soft transition-colors hover:border-trust hover:text-trust ${
          night ? "border-white/15 bg-ink text-white" : "border-line bg-white text-ink"
        }`}
      >
        {open ? (
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden>
            <path d="M6 6l12 12M18 6L6 18" />
          </svg>
        ) : (
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden>
            <path d="M4 7h16M4 12h16M4 17h16" />
          </svg>
        )}
        {open ? "Close" : "Menu"}
      </button>

      {open && (
        <>
          <div
            className="fixed inset-0 z-40 bg-ink/25 backdrop-blur-sm"
            aria-hidden
            onClick={() => setOpen(false)}
          />
          <div
            role="dialog"
            aria-modal="true"
            aria-label="Menu"
            className={`fixed inset-x-3 top-[4.25rem] z-50 rounded-[22px] border p-2 shadow-soft backdrop-blur-xl ${
              night ? "border-white/10 bg-ink/95 text-white" : "border-line bg-white/95"
            }`}
          >
            {links.map((l) => (
              <a
                key={l.href}
                href={l.href}
                onClick={() => setOpen(false)}
                className={`block rounded-[14px] px-4 py-3 text-[15px] font-medium transition-colors ${
                  night ? "text-white hover:bg-white/10" : "text-ink hover:bg-paper"
                }`}
              >
                {l.label}
              </a>
            ))}
            <div className={`my-1 border-t ${night ? "border-white/10" : "border-line"}`} />
            {/* Both audiences named rather than one generic "Login" — an
                employer should not have to guess which door is theirs. */}
            <Link
              href="/student"
              onClick={() => setOpen(false)}
              className={`block rounded-[14px] px-4 py-3 text-[15px] font-medium transition-colors ${
                night ? "text-white/70 hover:bg-white/10" : "text-muted hover:bg-paper"
              }`}
            >
              Job seeker login
            </Link>
            <Link
              href="/employer"
              onClick={() => setOpen(false)}
              className={`block rounded-[14px] px-4 py-3 text-[15px] font-medium transition-colors ${
                night ? "text-white/70 hover:bg-white/10" : "text-muted hover:bg-paper"
              }`}
            >
              Employer login
            </Link>
            <Link
              href="/register"
              onClick={() => setOpen(false)}
              className="block rounded-[14px] px-4 py-3 text-[15px] font-semibold text-trust transition-colors hover:bg-sky"
            >
              Sign up
            </Link>
          </div>
        </>
      )}
    </div>
  );
}
