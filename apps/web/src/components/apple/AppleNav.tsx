"use client";

import Link from "next/link";
import { useState } from "react";

const LINKS = [
  { href: "/#interview-start", label: "AI interview" },
  { href: "/employers", label: "Employers" },
  { href: "/courses", label: "Courses" },
  { href: "/jobs", label: "Jobs" },
] as const;

export function AppleNav({ dark = false }: { dark?: boolean }) {
  const [open, setOpen] = useState(false);
  return (
    <header className={`apple-nav${dark ? " is-dark" : ""}`}>
      <nav className="apple-nav-bar mx-auto w-full max-w-[1100px] gap-6 px-5" aria-label="Primary">
        <Link href="/" className="text-[14px] font-semibold tracking-[-0.02em]">
          BrowseJobs
        </Link>
        <ul className="apple-links hidden flex-1 items-center justify-center gap-8 text-[12px] md:flex">
          {LINKS.map((item) => (
            <li key={item.href}>
              <Link href={item.href} className="inline-flex min-h-11 items-center">
                {item.label}
              </Link>
            </li>
          ))}
        </ul>
        <Link
          href="/#interview-start"
          className="apple-links ml-auto hidden text-[12px] md:inline-flex md:min-h-11 md:items-center"
        >
          Take your free AI interview
        </Link>
        <button
          type="button"
          className="ml-auto inline-flex h-11 items-center px-2 text-[12px] md:hidden"
          aria-expanded={open}
          aria-controls="apple-menu"
          onClick={() => setOpen((value) => !value)}
        >
          {open ? "Close" : "Menu"}
        </button>
      </nav>
      {open ? (
        <ul id="apple-menu" className="apple-menu text-[17px] md:hidden">
          {LINKS.map((item) => (
            <li key={item.href}>
              <Link href={item.href} className="flex min-h-11 items-center" onClick={() => setOpen(false)}>
                {item.label}
              </Link>
            </li>
          ))}
          <li>
            <Link href="/#interview-start" className="flex min-h-11 items-center" onClick={() => setOpen(false)}>
              Take your free AI interview
            </Link>
          </li>
        </ul>
      ) : null}
    </header>
  );
}
