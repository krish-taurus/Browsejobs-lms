"use client";

import Link from "next/link";
import { useState } from "react";
import { usePathname } from "next/navigation";
import { LoginMenu } from "@/components/landing/LoginMenu";

const LINKS = [
  { href: "/", label: "Home" },
  { href: "/students", label: "Students" },
  { href: "/employers", label: "For Employers" },
  { href: "/demo", label: "Demo" },
] as const;

export function AppleNav({ dark = false }: { dark?: boolean }) {
  const [open, setOpen] = useState(false);
  const path = usePathname();
  const tone = dark ? "night" : "light";

  return (
    <header className={`apple-nav${dark ? " is-dark" : ""}`}>
      <nav className="apple-nav-bar mx-auto w-full max-w-[1100px] px-5" aria-label="Primary">
        <Link href="/" className="apple-nav-brand">
          BrowseJobs
        </Link>
        <ul className="apple-nav-links">
          {LINKS.map((item) => (
            <li key={item.href}>
              <Link href={item.href} aria-current={path === item.href ? "page" : undefined}>
                {item.label}
              </Link>
            </li>
          ))}
          <li>
            <LoginMenu tone={tone} appearance="apple" />
          </li>
        </ul>
        <Link href="/employers/enquire" className="apple-nav-cta">
          <span className="hidden lg:inline">Onboard with us for the future of hiring</span>
          <span className="lg:hidden">Onboard with us</span>
        </Link>
        <button
          type="button"
          className="apple-nav-menu"
          aria-expanded={open}
          aria-controls="apple-menu"
          onClick={() => setOpen((value) => !value)}
        >
          {open ? "Close" : "Menu"}
        </button>
      </nav>
      {open ? (
        <ul id="apple-menu" className="apple-menu md:hidden">
          {LINKS.map((item) => (
            <li key={item.href}>
              <Link href={item.href} onClick={() => setOpen(false)}>
                {item.label}
              </Link>
            </li>
          ))}
          <li>
            <LoginMenu tone={tone} appearance="apple" />
          </li>
          <li>
            <Link href="/employers/enquire" className="apple-nav-cta mt-2" onClick={() => setOpen(false)}>
              Onboard with us for the future of hiring
            </Link>
          </li>
        </ul>
      ) : null}
    </header>
  );
}
