"use client";

import Link from "next/link";
import { useEffect, useRef, useState, type ReactNode } from "react";
import gsap from "gsap";
import { LoginMenu } from "@/components/landing/LoginMenu";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { contact, FOOTER_LINE } from "@/content/landing";
import { recommendedCourses, recruiterFaqs } from "@/content/home";
import { CALL_TIMES } from "@/content/enquiries";
import { prefersReducedMotion } from "@/lib/motion";

const LINKS = [
  { href: "/", label: "Home" },
  { href: "/students", label: "Students" },
  { href: "/courses", label: "Courses" },
  { href: "/employers", label: "For Employers" },
  { href: "/demo", label: "Demo" },
] as const;

export function ArgusButton({
  href,
  variant = "primary",
  children,
}: {
  href?: string;
  variant?: "primary" | "secondary";
  children: ReactNode;
}) {
  const className = variant === "primary" ? "argus-btn argus-btn-primary" : "argus-btn argus-btn-secondary";
  if (href) {
    return (
      <Link href={href} className={className}>
        {children}
      </Link>
    );
  }
  return (
    <button type="button" className={className}>
      {children}
    </button>
  );
}

export function ArgusMore({ href, children }: { href: string; children: ReactNode }) {
  return (
    <Link href={href} className="argus-more">
      {children}
      <i aria-hidden>›</i>
    </Link>
  );
}

export function GlassCard({ index, title, children }: { index: string; title: string; children: ReactNode }) {
  const ref = useRef<HTMLElement>(null);
  return (
    <article
      ref={ref}
      className="argus-card"
      onMouseMove={(event) => {
        const box = ref.current?.getBoundingClientRect();
        if (!box || !ref.current) return;
        ref.current.style.setProperty("--mx", `${event.clientX - box.left}px`);
        ref.current.style.setProperty("--my", `${event.clientY - box.top}px`);
      }}
    >
      <span className="argus-card-no">{index}</span>
      <h3>{title}</h3>
      <div className="argus-body" style={{ marginTop: "0.7rem" }}>
        {children}
      </div>
    </article>
  );
}

export function ArgusBadge({ children }: { children: ReactNode }) {
  return <span className="argus-badge">{children}</span>;
}

export function ArgusFields() {
  return (
    <form
      className="argus-panel"
      style={{ display: "grid", gap: "0.9rem" }}
      onSubmit={(event) => event.preventDefault()}
    >
      <p className="argus-kicker">Sample fields</p>
      <label className="argus-field">
        <input name="name" placeholder=" " autoComplete="name" />
        <span className="argus-label">Full name</span>
      </label>
      <label className="argus-field argus-phone">
        <span className="argus-code">+91</span>
        <input name="phone" inputMode="tel" placeholder=" " autoComplete="tel" />
        <span className="argus-label">Phone</span>
      </label>
      <label className="argus-field">
        <select name="preferred_time" defaultValue="" required>
          <option value="" disabled>
            Choose a time
          </option>
          {CALL_TIMES.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
        <span className="argus-label">Preferred time to call</span>
      </label>
      <label className="argus-check">
        <input type="checkbox" name="consent" />
        <span>BrowseJobs may call or email me about this enquiry.</span>
      </label>
      <div style={{ display: "flex", flexWrap: "wrap", gap: "0.75rem", alignItems: "center" }}>
        <ArgusButton>Take your free AI interview</ArgusButton>
        <ArgusButton variant="secondary">Book free counselling</ArgusButton>
      </div>
    </form>
  );
}

export function ArgusFaq() {
  return (
    <div className="argus-faq">
      {recruiterFaqs.map((item) => (
        <FaqItem key={item.q} question={item.q} answer={item.a} />
      ))}
    </div>
  );
}

function FaqItem({ question, answer }: { question: string; answer: string }) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    if (prefersReducedMotion()) {
      el.style.height = open ? "auto" : "0px";
      return;
    }
    gsap.to(el, { height: open ? "auto" : 0, duration: 0.4, ease: "power3.out" });
  }, [open]);

  return (
    <div>
      <button type="button" className="argus-faq-q" aria-expanded={open} onClick={() => setOpen((value) => !value)}>
        {question}
        <span className={open ? "argus-faq-plus is-open" : "argus-faq-plus"} aria-hidden>
          +
        </span>
      </button>
      <div ref={ref} className="argus-faq-a">
        <p>{answer}</p>
      </div>
    </div>
  );
}

export function ArgusNav() {
  const [open, setOpen] = useState(false);
  const [hidden, setHidden] = useState(false);
  const menuRef = useRef<HTMLUListElement>(null);

  useEffect(() => {
    let last = window.scrollY;
    const onScroll = () => {
      const y = window.scrollY;
      setHidden(y > last && y > 72);
      last = y;
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  useEffect(() => {
    const links = menuRef.current?.querySelectorAll("a, button");
    if (!open || !links?.length || prefersReducedMotion()) return;
    gsap.from(links, { y: 18, opacity: 0, duration: 0.6, ease: "power3.out", stagger: 0.08 });
  }, [open]);

  return (
    <header className={hidden && !open ? "argus-nav is-hidden" : "argus-nav"}>
      <nav className="argus-nav-bar" aria-label="Primary">
        <Link href="/" className="argus-logo">
          BrowseJobs
        </Link>
        <ul className="argus-nav-links">
          {LINKS.map((item) => (
            <li key={item.href}>
              <Link href={item.href}>{item.label}</Link>
            </li>
          ))}
        </ul>
        <div className="argus-nav-actions">
          <LoginMenu tone="night" appearance="argus" />
          <Link href="/employers/enquire" className="argus-btn argus-btn-primary">
            <span className="argus-nav-cta-long">Onboard with us for the future of hiring</span>
            <span className="argus-nav-cta-short">Onboard with us</span>
          </Link>
          <button
            type="button"
            className="argus-nav-menu"
            aria-expanded={open}
            aria-controls="argus-menu"
            onClick={() => setOpen((value) => !value)}
          >
            {open ? "Close" : "Menu"}
          </button>
        </div>
      </nav>
      {open ? (
        <ul id="argus-menu" ref={menuRef} className="argus-menu">
          {LINKS.map((item) => (
            <li key={item.href}>
              <Link href={item.href} onClick={() => setOpen(false)}>
                {item.label}
              </Link>
            </li>
          ))}
          <li>
            <Link href="/employers/enquire" onClick={() => setOpen(false)}>
              Onboard with us for the future of hiring
            </Link>
          </li>
        </ul>
      ) : null}
    </header>
  );
}

export function ArgusFooter() {
  const courses = recommendedCourses();
  return (
    <footer className="argus-foot">
      <div className="argus-foot-grid">
        <div>
          <h2>Candidates</h2>
          <ul>
            <li><Link href="/#interview-start">Free AI interview</Link></li>
            <li><Link href="/students">Students</Link></li>
            <li><Link href="/how-it-works">How it works</Link></li>
            <li><Link href="/get-hired">Get hired</Link></li>
            <li><Link href="/courses">Courses</Link></li>
            <li><Link href="/courses/enquire">Ask about a course</Link></li>
          </ul>
        </div>
        <div>
          <h2>Employers</h2>
          <ul>
            <li><Link href="/employers">AI Recruiter</Link></li>
            <li><Link href="/employers/how-it-works">How it works</Link></li>
            <li><Link href="/demo">Watch the demo</Link></li>
            <li><Link href="/employers/faq">FAQ</Link></li>
            <li><Link href="/employers/enquire">Enquire</Link></li>
          </ul>
        </div>
        <div>
          <h2>Company</h2>
          <ul>
            <li><Link href="/founder">Dr Krish Bharggav</Link></li>
            <li><Link href="/answers">Answers</Link></li>
            <li><Link href="/privacy-policy">Privacy</Link></li>
            <li><Link href="/terms">Terms</Link></li>
          </ul>
        </div>
        <div>
          <h2>Courses</h2>
          <ul>
            {courses.map((course) => (
              <li key={course.slug}>
                <Link href={course.href}>{course.name}</Link>
              </li>
            ))}
          </ul>
        </div>
      </div>
      <div style={{ maxWidth: 1100, margin: "2rem auto 0" }}>
        <p className="argus-body">
          {contact.entity} · {contact.address} · {contact.phone} · {contact.email} · {contact.hours}
        </p>
        <p className="argus-body" style={{ marginTop: "0.8rem" }}>
          The hiring floor on these pages is sample data. A person always releases the offer.
        </p>
        <Disclaimer tone="argus" />
        <p className="argus-disclaimer">{FOOTER_LINE}</p>
      </div>
      <p className="argus-wordmark">B R O W S E J O B S</p>
    </footer>
  );
}
