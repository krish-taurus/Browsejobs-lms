import type { Metadata } from "next";
import Link from "next/link";
import { ApShell } from "@/components/ap/ApShell";
import { SGetStarted } from "@/components/ap/generated/employers";
import { SecureTrust } from "@/components/ap/SecureTrust";
import { contact } from "@/content/landing";
import { EMPLOYER_PHONE } from "@/content/phones";
import { SEO_PAGES } from "@/content/seo-nav";
import { breadcrumbNode, faqNode, jsonLdGraph, moneyMetadata, webPageNode } from "@/lib/seo";

const page = SEO_PAGES.secureInterview;

/**
 * Set to the hosted 30-second film once it exists (e.g. "/media/secure-interview-30s.mp4").
 * While null, the film slot shows the storyboard instead of an empty player.
 */
const FILM_SRC: string | null = null;

const faqs = [
  {
    q: "What kinds of cheating does the integrity layer look for?",
    a: "Three patterns that account for most interview fraud: reading answers from a screen, a voice that does not belong to the face on camera (lip-sync mismatch, pre-recorded or synthetic video), and a second person feeding answers. Each is read from a different signal, and each is shown to your team with the clip it came from.",
  },
  {
    q: "Does a flag reject the candidate?",
    a: "No. A flag parks the interview for a human. Your team sees the signal, the timestamp and the clip, then decides. Automation never rejects anyone and never releases an offer.",
  },
  {
    q: "Is it perfect?",
    a: "No detector is, and we will not print a catch-rate we cannot defend. Every integrity score ships with its evidence so a false alarm is easy to overturn and a real one is easy to prove.",
  },
  {
    q: "What does the candidate see?",
    a: "They are told before the interview starts which signals are checked, and they consent to camera and microphone analysis. Nothing runs silently.",
  },
  {
    q: "Is the integrity layer live today?",
    a: "Not yet. Today's interviews do not capture camera or window-switch checks, and the AI interview page says so. This page describes what we are building and how it will behave. Join the pilot to get it first.",
  },
] as const;

const crumbs = [
  { name: "Home", path: "/" },
  { name: "AI hiring", path: SEO_PAGES.aiHiring.path },
  { name: "Secure interviews", path: page.path },
] as const;

const SIGNALS = [
  { n: "01", t: "Gaze", b: "Where the candidate looks, and how. Reading leaves a steady scan. Thinking leaves drift and return." },
  { n: "02", t: "Lip-sync", b: "Audio against the mouth, frame by frame. A lead, a lag, a looped clip or a synthetic face breaks the match." },
  { n: "03", t: "Second voice", b: "Speakers separated in the room audio. A prompt, a pause, then a fluent answer is logged with timestamps." },
  { n: "04", t: "Environment", b: "Focus loss, extra displays and answer-assist overlays running during the session." },
] as const;

const FILM = [
  ["0–8s", "A candidate nails every answer. Pull back: a second laptop, a phone, a friend."],
  ["8–16s", "Same interview through our layer: gaze trace, audio-mouth drift, a second voice."],
  ["16–24s", "Integrity score drops. Clips and timestamps attach to the report."],
  ["24–30s", "A recruiter reviews the evidence and decides. BrowseJobs: interviews you can trust."],
] as const;

export const metadata: Metadata = moneyMetadata(page);

export default function SecureInterviewPage() {
  const jsonLd = jsonLdGraph([
    webPageNode(page),
    breadcrumbNode(crumbs),
    {
      "@type": "Service",
      name: "BrowseJobs secure AI interview",
      serviceType: "Proctored AI interview with cheating detection",
      url: `https://browsejobs.ai${page.path}`,
      provider: {
        "@type": "Organization",
        name: "BrowseJobs",
        email: contact.email,
        telephone: EMPLOYER_PHONE.tel,
        url: "https://browsejobs.ai",
      },
      areaServed: "IN",
    },
    faqNode(faqs),
  ]);

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <ApShell current="employers" cta={{ label: "Join the pilot", href: "/employers#get-started" }}>
        <section className="s-black emp-hero" id="top">
          <div className="wrap center">
            <p className="eyebrow">Secure interviews</p>
            <h1 className="h-hero">
              <span>The interview you can</span> <span>trust the answers to.</span>
            </h1>
            <p className="lead">
              Reading from a screen. A voice that is not the face. Someone feeding the answers. BrowseJobs interviews are
              built to catch all three, and to show your team the evidence.
            </p>
            <div className="cta-row">
              <Link className="btn btn-primary" href="/employers#get-started">
                Join the pilot
              </Link>
              <a className="more" href="#secure">
                See how it catches cheating <span className="chev" aria-hidden="true">›</span>
              </a>
            </div>
          </div>
          <div className="eclipse" aria-hidden="true"></div>
        </section>

        <section className="s-white chapter" id="problem">
          <div className="wrap center">
            <p className="eyebrow">The problem</p>
            <h2 className="h-section" data-reveal="">
              If the answers are not theirs, the score means nothing.
            </h2>
            <p className="lead" data-reveal="">
              A fluent answer used to mean a prepared candidate. Now it can mean a model reading over their shoulder.
              Automation saves hours only if the interview at its centre cannot be faked.
            </p>
          </div>
        </section>

        <SecureTrust audience="page" />

        <section className="s-paper chapter" id="signals">
          <div className="wrap">
            <div className="center" style={{ marginBottom: "clamp(28px,4vw,48px)" }}>
              <p className="eyebrow">What is read</p>
              <h2 className="h-section" style={{ fontSize: "clamp(32px,4.6vw,56px)" }}>
                Four signals. One integrity score.
              </h2>
            </div>
            <div className="tiles-2" data-reveal-kids="">
              {SIGNALS.map((s) => (
                <article key={s.n} className="tile tile-pad">
                  <p className="eyebrow-sm num">{s.n}</p>
                  <h3 className="h-tile" style={{ marginTop: "10px" }}>
                    {s.t}
                  </h3>
                  <p className="body">{s.b}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section className="s-black chapter" id="film">
          <div className="wrap center">
            <p className="eyebrow">Watch</p>
            <h2 className="h-section">Thirty seconds: the cheat, and the catch.</h2>
            {FILM_SRC ? (
              <video
                controls
                playsInline
                preload="metadata"
                src={FILM_SRC}
                style={{ width: "100%", maxWidth: 960, borderRadius: 24, marginTop: 32, background: "#000" }}
              />
            ) : (
              <div className="tiles-2" style={{ marginTop: 32, textAlign: "left" }} data-reveal-kids="">
                {FILM.map(([t, d]) => (
                  <article key={t} className="tile tile-pad">
                    <p className="eyebrow-sm num">{t}</p>
                    <p className="body" style={{ marginTop: 8 }}>
                      {d}
                    </p>
                  </article>
                ))}
              </div>
            )}
          </div>
        </section>

        <section className="s-white chapter" id="report">
          <div className="wrap center">
            <p className="eyebrow">What you receive</p>
            <h2 className="h-section">Evidence, not a verdict.</h2>
            <p className="lead">
              Every interview ends in a graded brief with an integrity section: the score, each flag, its timestamp and the
              clip behind it. A clean interview says so. A flagged one waits for a person.
            </p>
            <ul className="check-list" style={{ textAlign: "left", maxWidth: 560, marginInline: "auto" }}>
              <li>Integrity score shown beside the rubric score, never blended into it.</li>
              <li>A flag timeline you can scrub, with the source clip for each flag.</li>
              <li>The candidate is told up front what is checked, and consents.</li>
              <li>No automatic rejection. No automatic offer.</li>
            </ul>
          </div>
        </section>

        <section className="s-paper chapter" id="faq">
          <div className="wrap center">
            <h2 className="h-section">Questions before you book.</h2>
            <div className="faq">
              {faqs.map((f) => (
                <details key={f.q}>
                  <summary>
                    <h3 className="q">{f.q}</h3>
                  </summary>
                  <p className="answer">{f.a}</p>
                </details>
              ))}
            </div>
          </div>
        </section>

        <SGetStarted />
      </ApShell>
    </>
  );
}
