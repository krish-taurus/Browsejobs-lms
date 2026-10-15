import type { Metadata } from "next";
import { IntegrityDemo } from "@/components/seo/IntegrityDemo";
import {
  ContactStrip,
  Contents,
  MoneyArticle,
  MoneyFaq,
  MoneyHero,
  MoneySection,
  RelatedLinks,
} from "@/components/seo/MoneyArticle";
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
    a: "Not yet. Today's interviews do not capture camera or window-switch checks, and the AI interview page says so. This page describes what we are building and how it will behave. Join the pilot list to get it first.",
  },
] as const;

const crumbs = [
  { name: "Home", path: "/" },
  { name: "AI hiring", path: SEO_PAGES.aiHiring.path },
  { name: "Secure interviews", path: page.path },
] as const;

const SIGNALS = [
  {
    tag: "Gaze",
    title: "Eyes that read, not recall",
    body: "Tracks where the candidate looks and how. Reading a second screen leaves a steady left-to-right scan; thinking leaves drift and return.",
  },
  {
    tag: "Lip-sync",
    title: "One voice, one face",
    body: "Compares the audio against the mouth frame by frame. A lead or lag, a looped clip, or a synthetic face all break the alignment.",
  },
  {
    tag: "Second voice",
    title: "Catches the whisper behind the answer",
    body: "Separates speakers in the room audio. A prompt, then a pause, then a fluent answer is a pattern, and it is logged with timestamps.",
  },
  {
    tag: "Environment",
    title: "Tabs, windows and tools",
    body: "Flags focus loss, extra displays and answer-assist overlays running during the session.",
  },
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
    <MoneyArticle jsonLd={jsonLd}>
      <MoneyHero
        kicker="For hiring teams"
        title="The interview you can trust the answers to."
        crumbs={crumbs}
        primary={{ kind: "link", href: "/employers", label: "Join the secure-interview pilot" }}
        secondary={{ href: "#demo", label: "See how it catches cheating" }}
        lede={
          <p>
            AI answers, a hidden second screen, a friend whispering off camera. Remote interviews are easy to game and
            hard to audit. BrowseJobs runs the whole hiring pipeline end to end, and its interview is built to be the
            hardest one to cheat: it reads gaze, lip-sync and speaker audio live, and hands your team the evidence.
          </p>
        }
      />
      <Contents
        items={[
          { href: "#problem", label: "The problem" },
          { href: "#demo", label: "How it catches it" },
          { href: "#signals", label: "Four signals" },
          { href: "#film", label: "30-second film" },
          { href: "#report", label: "What you receive" },
          { href: "#faq", label: "Questions" },
        ]}
      />

      <MoneySection id="problem" kicker="The problem" heading="If the answers are not theirs, the score means nothing.">
        <p>
          A fluent answer used to mean a prepared candidate. Now it can mean a language model reading over their
          shoulder, a hidden prompter, or a face that is not speaking the words you hear. Teams spend a round of
          senior time on a candidate who could not repeat the answer unaided.
        </p>
        <p>
          The automated pipeline from job description to graded brief saves hours. It saves nothing if the interview
          at the centre of it can be faked. That is why our first promise is integrity, and the automation is built
          around it.
        </p>
      </MoneySection>

      <MoneySection id="demo" kicker="Staged demonstration" heading="Three ways to cheat. Three signals that expose them.">
        <p>Pick a pattern. The candidate and transcript are staged; the signals shown are the ones the layer reads.</p>
        <IntegrityDemo />
      </MoneySection>

      <MoneySection id="signals" kicker="What is read" heading="Four signals, one integrity score.">
        <div className="not-prose" style={{ display: "grid", gap: 14, marginTop: 8 }}>
          {SIGNALS.map((s) => (
            <div key={s.tag} className="rounded-[14px] border border-line bg-white p-5">
              <p className="mono text-[11px] text-trust">{s.tag}</p>
              <h3 className="mt-1 text-lg font-semibold text-ink">{s.title}</h3>
              <p className="mt-2 text-[15px] leading-relaxed text-ink2">{s.body}</p>
            </div>
          ))}
        </div>
      </MoneySection>

      <MoneySection id="film" kicker="Watch" heading="Thirty seconds: the cheat, and the catch.">
        {FILM_SRC ? (
          <video
            controls
            playsInline
            preload="metadata"
            src={FILM_SRC}
            style={{ width: "100%", borderRadius: 14, background: "#000" }}
          />
        ) : (
          <ol className="not-prose" style={{ display: "grid", gap: 10, paddingLeft: 0, listStyle: "none" }}>
            {[
              ["0–8s", "A candidate nails every answer. Cut wider: a second laptop, a phone, a friend."],
              ["8–16s", "The same interview through our layer: gaze trace, audio-mouth drift, a second voice."],
              ["16–24s", "Integrity score drops. Clip and timestamps attach to the report."],
              ["24–30s", "A recruiter reviews the evidence, decides, moves on. BrowseJobs: interviews you can trust."],
            ].map(([t, d]) => (
              <li key={t} className="rounded-[14px] border border-line bg-white p-4 text-[15px] text-ink2">
                <span className="mono text-[11px] text-trust">{t}</span>
                <br />
                {d}
              </li>
            ))}
          </ol>
        )}
      </MoneySection>

      <MoneySection id="report" kicker="What you receive" heading="Evidence, not a verdict.">
        <p>
          Every interview ends in a graded brief with an integrity section: the score, each flag, its timestamp and
          the clip behind it. A clean interview says so. A flagged one is parked for a human, who decides.
        </p>
        <ul>
          <li>Integrity score alongside the rubric score, shown separately.</li>
          <li>Flag timeline you can scrub, with the source clip for each flag.</li>
          <li>Candidate told up front what is checked, and consents.</li>
          <li>No automatic rejection. No automatic offer.</li>
        </ul>
      </MoneySection>

      <MoneyFaq faqs={faqs} />
      <RelatedLinks
        links={[
          { href: SEO_PAGES.aiHiring.path, label: "AI hiring platform", note: "The end-to-end pipeline around the interview" },
          { href: SEO_PAGES.aiInterview.path, label: "AI interview platform", note: "How the interviews run today" },
        ]}
      />
      <ContactStrip phone={EMPLOYER_PHONE} />
    </MoneyArticle>
  );
}
