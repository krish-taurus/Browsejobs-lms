"use client";

import Link from "next/link";
import type { InputHTMLAttributes, ReactNode } from "react";
import { ApLoop } from "@/components/ap/ApLoop";
import { taurusDisplayFont } from "@/components/ap/pages/taurus-font";
import "./auth.css";

/*
 * The sign-in pages (job seeker, sign-up, employer, employer invite, staff) in
 * the site's Apple direction: a black panel with a HyperFrames loop on the
 * left, a quiet white form on the right. Phones get the form only.
 */

type Portal = "student" | "employer" | "staff" | "taurus";

const ART: Record<Portal, { eyebrow: string; title: string; lede: string; fine: string; loop: { src: string; poster: string; kind: "phone" | "ambient" } }> = {
  student: {
    eyebrow: "BrowseJobs",
    title: "Your free AI interview is waiting.",
    lede: "About 15 questions from your CV. Score 75% or more, and we send your CV to 3,000 HR recruiters.",
    fine: "Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your performance.",
    loop: { src: "/media/home/hero-phone.mp4", poster: "/media/home/hero-phone-poster.jpg", kind: "phone" },
  },
  employer: {
    eyebrow: "BrowseJobs AI Recruiter · Powered by Taurus AI",
    title: "Hire over WhatsApp. Watch every step.",
    lede: "Taurus screens, calls and interviews candidates, and checks with you before every step that matters.",
    fine: "The screen shows sample data. A person on your team always approves the offer.",
    loop: { src: "/media/auth/employer-phone.mp4", poster: "/media/auth/employer-phone-poster.jpg", kind: "phone" },
  },
  taurus: {
    eyebrow: "Taurus AI · by BrowseJobs",
    title: "One live floor for every AI agent you run.",
    lede: "See what each agent is doing, approve anything that goes out, and ask Taurus out loud.",
    fine: "Your agents, your keys. A client only ever sees their own workspace.",
    loop: { src: "/media/home/closing-rings.mp4", poster: "/media/home/closing-rings-poster.jpg", kind: "ambient" },
  },
  staff: {
    eyebrow: "BrowseJobs",
    title: "Staff portal.",
    lede: "Cohorts, content, leads and placements, in one place.",
    fine: "For BrowseJobs staff only. Every sign-in is logged.",
    loop: { src: "/media/home/closing-rings.mp4", poster: "/media/home/closing-rings-poster.jpg", kind: "ambient" },
  },
};

export function AuthScreen({
  portal,
  eyebrow,
  title,
  lede,
  links,
  children,
  below,
}: {
  portal: Portal;
  eyebrow: string;
  title: string;
  lede?: ReactNode;
  /** Small links in the top bar of the form side. */
  links?: { href: string; label: string }[];
  children: ReactNode;
  /** Switch-portal links under the form. */
  below?: ReactNode;
}) {
  const art = ART[portal];
  return (
    <div className={`bj-auth bj-auth--${portal} ${taurusDisplayFont.variable}`}>
      <aside className={`bj-auth-art bj-auth-art--${art.loop.kind}`} aria-hidden="true">
        {art.loop.kind === "ambient" && <ApLoop className="bj-auth-ambient" src={art.loop.src} poster={art.loop.poster} />}
        <div className="bj-auth-art-copy">
          <p className="bj-auth-art-eyebrow">{art.eyebrow}</p>
          <p className="bj-auth-art-title">{art.title}</p>
          <p className="bj-auth-art-lede">{art.lede}</p>
        </div>
        {art.loop.kind === "phone" && (
          <div className="bj-auth-phone">
            <div className="bj-auth-phone-screen">
              <ApLoop src={art.loop.src} poster={art.loop.poster} />
            </div>
          </div>
        )}
        <p className="bj-auth-art-fine">{art.fine}</p>
      </aside>

      <div className="bj-auth-side">
        <header className="bj-auth-top">
          <Link href="/" className="bj-auth-wordmark" aria-label="BrowseJobs home">
            BrowseJobs
          </Link>
          {links && links.length > 0 && (
            <nav aria-label="Other sign-in pages" className="bj-auth-toplinks">
              {links.map((l) => (
                <Link key={l.href} href={l.href}>
                  {l.label}
                </Link>
              ))}
            </nav>
          )}
        </header>

        <main className="bj-auth-main" id="main">
          <div className="bj-auth-card">
            <p className="bj-auth-eyebrow">{eyebrow}</p>
            <h1>{title}</h1>
            {lede && <p className="bj-auth-lede">{lede}</p>}
            {children}
          </div>
          {below && <div className="bj-auth-below">{below}</div>}
        </main>

        <footer className="bj-auth-foot">
          <span>IBrowseJobs Technologies Pvt Ltd</span>
          <Link href="/privacy-policy">Privacy</Link>
          <Link href="/terms">Terms</Link>
        </footer>
      </div>
    </div>
  );
}

/**
 * A field whose label sits inside the box and floats up once there's text.
 * The placeholder stays in the DOM (tests and password managers use it) but is
 * drawn transparent so it never overlaps the label.
 */
export function AuthField({
  id,
  label,
  trailing,
  className,
  ...input
}: { id: string; label: string; trailing?: ReactNode } & InputHTMLAttributes<HTMLInputElement>) {
  return (
    <div className={`bj-auth-field${trailing ? " has-trailing" : ""}${className ? ` ${className}` : ""}`}>
      <input id={id} placeholder={input.placeholder ?? " "} {...input} />
      <label htmlFor={id}>{label}</label>
      {trailing}
    </div>
  );
}

export function AuthAlert({ children, tone = "error" }: { children: ReactNode; tone?: "error" | "warn" }) {
  return (
    <p className={`bj-auth-alert bj-auth-alert--${tone}`} role={tone === "error" ? "alert" : undefined}>
      {children}
    </p>
  );
}
