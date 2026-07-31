"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";

/**
 * Two audiences sign in here, and they are not interchangeable: a job
 * seeker lands on OTP sign-in, an employer on the workspace password form.
 * Sending everyone to one of them and letting the other work it out is the
 * kind of small confusion that loses an employer on their first visit.
 *
 * Kept keyboard-usable: Escape closes, focus is visible, the trigger states
 * its expanded status, and the menu closes on outside click.
 */

const OPTIONS = [
  {
    href: "/student",
    label: "Job seeker",
    hint: "Applications, mocks and your profile",
  },
  {
    href: "/employer",
    label: "Employer",
    hint: "Your hiring workspace and pipelines",
  },
];

export function LoginMenu() {
  const [open, setOpen] = useState(false);
  const wrapRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;

    function onPointer(e: MouseEvent) {
      if (!wrapRef.current?.contains(e.target as Node)) setOpen(false);
    }
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") setOpen(false);
    }

    document.addEventListener("mousedown", onPointer);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onPointer);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  return (
    <div ref={wrapRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-haspopup="menu"
        className="flex items-center gap-1 text-sm font-medium text-muted transition-colors hover:text-ink"
      >
        Login
        <span
          aria-hidden
          className={`text-[10px] transition-transform ${open ? "rotate-180" : ""}`}
        >
          ▾
        </span>
      </button>

      {open && (
        <div
          role="menu"
          className="absolute right-0 top-full z-50 mt-3 w-64 overflow-hidden rounded-[14px] border border-line bg-white shadow-soft"
        >
          {OPTIONS.map((o) => (
            <Link
              key={o.href}
              href={o.href}
              role="menuitem"
              onClick={() => setOpen(false)}
              className="block px-4 py-3 transition-colors hover:bg-paper"
            >
              <span className="block text-sm font-semibold text-ink">{o.label}</span>
              <span className="mt-0.5 block text-xs text-muted">{o.hint}</span>
            </Link>
          ))}
          <div className="border-t border-line px-4 py-3">
            <Link
              href="/register"
              onClick={() => setOpen(false)}
              className="text-xs font-semibold text-trust hover:text-deep"
            >
              New here? Create a free account →
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}
