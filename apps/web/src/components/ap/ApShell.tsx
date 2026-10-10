"use client";

import Link from "next/link";
import { Inter_Tight } from "next/font/google";
import { useEffect, useRef, type ReactNode } from "react";
import "./ap.css";

const interTight = Inter_Tight({ subsets: ["latin"], weight: ["600", "700"], variable: "--font-inter-tight", display: "swap" });

export type ApCurrent = "home" | "students" | "courses" | "employers" | "demo";
type Current = ApCurrent;

const LINKS: { key: Current; label: string; href: string }[] = [
  { key: "home", label: "Home", href: "/" },
  { key: "students", label: "Students", href: "/students" },
  { key: "courses", label: "Courses", href: "/courses" },
  { key: "employers", label: "For Employers", href: "/employers" },
  { key: "demo", label: "Demo", href: "/employers/mission-control-demo" },
];

/**
 * The Apple-direction page shell (approved design demo, Oct 2026): global nav,
 * phone menu, footer, the scroll engine and the 3D process scenes. Pages render
 * their chapters as children; everything is scoped under `.ap` (see ap.css).
 */
export function ApShell({
  current,
  cta = { label: "Take the free AI interview", href: "/#top" },
  staticNav = false,
  children,
}: {
  current?: Current;
  cta?: { label: string; href: string };
  staticNav?: boolean;
  children: ReactNode;
}) {
  const root = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    const el = root.current;
    if (!el) return;
    let stopEffects: (() => void) | null = null;
    let stopScenes: (() => void) | null = null;
    let cancelled = false;
    import("./effects.mjs").then(({ initApEffects }) => {
      if (!cancelled) stopEffects = initApEffects(el);
    });
    if (el.querySelector("[data-3d]")) {
      import("./scenes3d.mjs")
        .then(({ mountApScenes }) => {
          if (!cancelled) stopScenes = mountApScenes(el);
        })
        .catch(() => undefined);
    }
    return () => {
      cancelled = true;
      stopEffects?.();
      stopScenes?.();
    };
  }, []);

  return (
    <div ref={root} className={`ap ${interTight.variable}`}>
      <header className={`gnav${staticNav ? " gnav--static" : ""}`}>
        <nav className="gnav-inner" aria-label="Global">
          <Link className="wordmark" href="/" aria-label="BrowseJobs home">
            BrowseJobs
          </Link>
          <ul className="gnav-links">
            {LINKS.map((l) => (
              <li key={l.key}>
                <a href={l.href} aria-current={l.key === current ? "page" : undefined}>
                  {l.label}
                </a>
              </li>
            ))}
          </ul>
          <div className="gnav-right">
            <details className="gnav-login">
              <summary>Log in</summary>
              <div className="gnav-login-menu">
                <Link href="/student">Job seeker</Link>
                <Link href="/employer">Employer</Link>
              </div>
            </details>
            <a className="btn btn-primary btn-sm gnav-cta" href={cta.href}>
            {cta.label}
          </a>
          </div>
          <button className="gnav-menu" type="button" aria-expanded="false" aria-controls="mnav">
            Menu <i aria-hidden="true"></i>
          </button>
        </nav>
      </header>
      <div className="mnav" id="mnav" role="dialog" aria-modal="true" aria-label="Menu">
        <div className="mnav-top">
          <span className="wordmark">BrowseJobs</span>
          <button className="mnav-close" type="button">
            Close
          </button>
        </div>
        <ul>
          {LINKS.map((l) => (
            <li key={l.key}>
              <a href={l.href}>{l.label}</a>
            </li>
          ))}
          <li>
            <Link href="/student">Job seeker login</Link>
          </li>
          <li>
            <Link href="/employer">Employer login</Link>
          </li>
        </ul>
        <a className="btn btn-primary" href={cta.href}>
          {cta.label}
        </a>
      </div>

      <main id="main">{children}</main>

      <footer className="footer">
        <div className="wrap">
          <div className="fine-top">
            <p>The hiring floor on these pages is sample data. A person always releases the offer.</p>
            <p>
              Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Hiring depends on the live market and your
              performance.
            </p>
          </div>
          <div className="footer-cols">
            <div>
              <h3>Candidates</h3>
              <ul>
                <li><Link href="/#top">Free AI interview</Link></li>
                <li><Link href="/students">Students</Link></li>
                <li><Link href="/how-it-works">How it works</Link></li>
                <li><Link href="/get-hired">Get hired</Link></li>
                <li><Link href="/courses">Courses</Link></li>
                <li><Link href="/courses/enquire">Ask about a course</Link></li>
              </ul>
            </div>
            <div>
              <h3>Employers</h3>
              <ul>
                <li><Link href="/employers">AI Recruiter</Link></li>
                <li><Link href="/employers/how-it-works">How it works</Link></li>
                <li><Link href="/employers/mission-control-demo">Watch the demo</Link></li>
                <li><Link href="/employers/faq">FAQ</Link></li>
                <li><Link href="/employers/enquire">Enquire</Link></li>
                <li><Link href="/taurusai">Taurus AI</Link></li>
              </ul>
            </div>
            <div>
              <h3>Company</h3>
              <ul>
                <li><Link href="/founder">Dr Krish Bharggav</Link></li>
                <li><Link href="/answers">Answers</Link></li>
                <li><Link href="/reviews">Reviews</Link></li>
                <li><Link href="/privacy-policy">Privacy</Link></li>
                <li><Link href="/terms">Terms</Link></li>
              </ul>
            </div>
            <div>
              <h3>Courses</h3>
              <ul>
                <li><Link href="/courses/data-engineering">Data Engineering</Link></li>
                <li><Link href="/courses/devops-cloud">DevOps &amp; Cloud</Link></li>
                <li><Link href="/courses/python-backend">Python Backend</Link></li>
                <li><Link href="/courses/data-analytics">Data Analytics</Link></li>
              </ul>
            </div>
          </div>
          <div className="footer-bottom">
            <span>IBrowseJobs Technologies Pvt Ltd · Whitefield, Bengaluru, Karnataka 560066 · +91 86185 19825 · hello@browsejobs.ai · Mon–Sat, 9:00 AM – 7:00 PM IST</span>
            <span>Every promise in writing · Every call recorded &amp; AI-monitored.</span>
          </div>
          <div className="footer-word" aria-hidden="true">
            BROWSEJOBS
          </div>
        </div>
      </footer>
    </div>
  );
}
