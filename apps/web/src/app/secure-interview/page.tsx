import type { Metadata } from "next";
import Link from "next/link";
import { ApShell } from "@/components/ap/ApShell";
import { SGetStarted } from "@/components/ap/generated/employers";
import { SampleReport } from "@/components/ap/SampleReport";
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
    a: "Reading answers from a phone or second screen, a second person in the room, and leaving the interview to look something up are caught today from camera, tab and paste signals. A voice that does not belong to the face (lip-sync, pre-recorded or synthetic video) and a second voice are in pilot. Each flag is shown to your team with the photo it came from.",
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
    q: "Do you catch answer-assist apps like Parakeet?",
    a: "We catch their footprint, not their name. A browser cannot list the apps running on a computer, so we read what using one does to the interview: the window losing focus while the tab stays visible, the tab being hidden, and pasted text. Each is flagged. In practice interviews a repeat violation closes the interview. In employer interviews nothing closes on its own: the moment is noted with a photo for your team to decide. Naming and blocking specific apps would need a desktop client, which we have not built.",
  },
  {
    q: "What does the candidate see?",
    a: "They are told before the interview starts which signals are checked, and they consent to camera and microphone analysis. Nothing runs silently.",
  },
  {
    q: "What is live today, and what is in pilot?",
    a: "Live: tab and window-focus checks, paste detection, face not visible, a possible second person in frame, gaze down or to the side, and random check-in photos, all shown in the report with a photo. These are camera estimates and can misfire on glasses, posters or reflections, so every flag carries its photo. In pilot: lip-sync matching and second-voice analysis. We will say so on the report when they ship.",
  },
] as const;

const crumbs = [
  { name: "Home", path: "/" },
  { name: "AI hiring", path: SEO_PAGES.aiHiring.path },
  { name: "Secure interviews", path: page.path },
] as const;

const SIGNALS = [
  { n: "01", t: "Gaze", b: "Looking down or to the side, flagged as a possible with a photo. Live today." },
  { n: "02", t: "Faces in frame", b: "Face not visible, or a possible second person. Live today, with random check-in photos." },
  { n: "03", t: "Lip-sync and voice", b: "Audio against the mouth, and a second voice behind the answer. In pilot." },
  { n: "04", t: "Answer-assist tools", b: "Leaving the tab, the window losing focus (how an always-on-top answer tool is used), or pasting an answer. Live today." },
] as const;

const FILM = [
  ["0–8s", "A candidate nails every answer. Pull back: a second laptop, a phone, a friend."],
  ["8–16s", "Same interview through our layer: gaze drops, a second face, a face that leaves the frame."],
  ["16–24s", "Flags land in the report with a time and a photo."],
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
              Looking away to read. A second person in the room. A voice that is not the face. BrowseJobs interviews flag them,
              attach a photo to each flag, and leave the decision to your team.
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
        <SampleReport />

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
