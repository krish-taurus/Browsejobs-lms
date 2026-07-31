"use client";

import { createContext, useContext } from "react";

/**
 * Every editable string on the homepage, keyed by "section.field".
 *
 * These are the FALLBACKS — the shipped copy. The CRM (Website → Homepage)
 * saves overrides into site_contents under the "homepage" key, and only the
 * keys the founder actually changed are stored; everything else falls through
 * to the values here. That means an empty/unreachable CMS can never blank the
 * page, and adding a new string is a one-line change in this file.
 *
 * Headlines that mix plain and gradient/coloured text are split into _start /
 * _highlight / _end parts (same convention the hero already used) so the
 * founder edits words, never markup.
 */
export const HOMEPAGE_DEFAULTS = {
  /* ------------------------------- hero -------------------------------- */
  "hero.kicker": "BrowseJobs · The Career Engine",
  "hero.headline_start": "Six months from",
  "hero.headline_highlight": "“still searching”",
  "hero.headline_end": "to signed.",
  "hero.subline":
    "An AI tutor that never sleeps. Mock interviews that call your phone. A job radar that hunts while you study.",
  "hero.subline_strong": "You bring the effort — we bring the offer.",
  "hero.cta_primary": "Book the free masterclass",
  "hero.cta_secondary": "Watch it work",
  "hero.cta_note": "Free seat · No card · 20,000+ students before you",

  /* ----------------------------- manifesto ------------------------------ */
  "manifesto.line1": "Colleges hand you a degree. Nobody hands you a career.",
  "manifesto.line2": "So we built a machine.",
  "manifesto.verb1_title": "Studies",
  "manifesto.verb1_body": "~50 real interview transcripts, every day",
  "manifesto.verb2_title": "Teaches",
  "manifesto.verb2_body": "exactly what companies ask right now",
  "manifesto.verb3_title": "Drills",
  "manifesto.verb3_body": "you until pressure feels normal",
  "manifesto.verb4_title": "Hunts",
  "manifesto.verb4_body": "jobs with you until you sign an offer",

  /* -------------------------------- path -------------------------------- */
  "path.kicker": "The path",
  "path.headline_start": "Three free steps before",
  "path.headline_highlight": "you pay a rupee.",
  "path.body":
    "Keep scrolling — the page walks the whole journey with you, from your first free call to an accepted offer.",
  "path.step1_title": "Free counselling",
  "path.step1_body": "A written Career Analysis Report — whether you join or not.",
  "path.step2_title": "Free live masterclass",
  "path.step2_body": "See the method live: how the syllabus is reverse-engineered.",
  "path.step3_title": "Free 7-hour bootcamp",
  "path.step3_body": "Sit a real teaching day of live Python before you decide.",
  "path.step4_title": "Paid batch",
  "path.step4_body": "Live, instructor-led. ₹30,000 — EMI 3 × ₹10,000.",
  "path.step5_title": "AI coach",
  "path.step5_body": "PRI, mastery map, and one clear next best action, daily.",
  "path.step6_title": "Mock interviews",
  "path.step6_body": "Voice mocks scored on real interview data, with a gap report.",
  "path.step7_title": "Placement",
  "path.step7_body": "Your profile worked until you accept an offer.",
  "path.finale_title": "Offer accepted.",
  "path.finale_body": "The placement fee starts only here — paid from the salary the offer brings.",

  /* ------------------------------- scenes ------------------------------- */
  "scene1.kicker": "01 · AI Tutor",
  "scene1.title_line1": "Stuck at 2am?",
  "scene1.title_line2": "So is your tutor.",
  "scene1.title_highlight": "Awake.",
  "scene1.body":
    "Every doubt answered in seconds, in your context, from your syllabus. Students ask it 400+ questions a course — because it never judges, never sleeps, never says 'ask me tomorrow'.",
  "scene2.kicker": "02 · Voice Mock Lab",
  "scene2.title_line1": "The interviewer that",
  "scene2.title_highlight": "calls you first.",
  "scene2.body":
    "A voice AI rings your phone, runs a real technical round, and scores every answer — communication, depth, confidence. By interview #12, the real thing feels like a rerun.",
  "scene3.kicker": "03 · Job Radar",
  "scene3.title_line1": "Openings hunted",
  "scene3.title_highlight": "while you sleep.",
  "scene3.body":
    "Live roles from LinkedIn and Naukri, matched to your skill graph and refreshed daily. When you're ready, the interviews are already waiting.",

  /* -------------------------------- bento ------------------------------- */
  "bento.kicker": "Everything included",
  "bento.headline": "One fee. The whole machine.",
  "bento.body":
    "No add-on pricing for the things that get you hired. Every tool below ships with every track.",

  /* ------------------------------ programs ------------------------------ */
  "programs.kicker": "Programs",
  "programs.headline": "Four live tracks. Each one rebuilt monthly.",
  "programs.body_start": "Only four — because",
  "programs.body_strong": "demand decides what we teach.",
  "programs.body_end":
    "We run tracks only where the market is actively hiring, and the live opening counts below prove it.",

  /* ---------------------------- career report --------------------------- */
  "report.kicker": "Free career report · unbiased",
  "report.headline_start": "Where does the market say",
  "report.headline_highlight": "you",
  "report.headline_end": "should go next?",
  "report.body_start":
    "Paste your resume, register once, and get a genuine direction report: what's working, what's missing, and which path fits your skills —",
  "report.body_strong": "even when it's one we don't teach.",
  "report.body_end": "Your resume is analysed and discarded, never stored.",
  "report.cta": "Get my free report",
  "report.note": "The analysis runs only after registration.",

  /* --------------------------------- fees ------------------------------- */
  "fees.kicker": "The fee structure",
  "fees.headline_start": "Two fees. Both in writing.",
  "fees.headline_highlight": "One only after you're hired.",
  "fees.reg_label": "01 · Registration",
  "fees.reg_note": "Payable only after the free masterclass and the free 7-hour bootcamp.",
  "fees.reg_badge1": "One payment",
  "fees.reg_badge2": "EMI · 3 × ₹10,000",
  "fees.reg_guarantee": "30-day money-back guarantee — any reason, full refund, in writing.",
  "fees.place_label": "02 · Placement fee",
  "fees.place_body_start": "Your first 3 months' CTC, due",
  "fees.place_body_strong": "only after you accept an offer",
  "fees.place_body_end":
    "— paid as 6 monthly EMIs from your new salary. Your ₹30,000 registration is adjusted inside it.",
  "fees.example_label": "Worked example · ₹12 LPA offer",

  /* ------------------------------- honesty ------------------------------ */
  "honesty.kicker": "The honesty doctrine",
  "honesty.headline_start": "Don't trust us.",
  "honesty.headline_highlight": "Verify us.",
  "honesty.body": "Fifteen minutes on Naukri is enough to check everything we claim. Here's how.",
  "honesty.step1_time": "5 min",
  "honesty.step1_title": "Count the demand",
  "honesty.step1_body":
    "Open Naukri → search the role + your city → filter “Last 7 days”. If hiring is real, you'll see it in the number.",
  "honesty.step2_time": "5 min",
  "honesty.step2_title": "Read 5 job descriptions",
  "honesty.step2_body":
    "Note the skills that repeat, then compare them against any institute's syllabus — ours included.",
  "honesty.step3_time": "5 min",
  "honesty.step3_title": "Pressure-test everyone",
  "honesty.step3_body": "Three questions separate honest institutes from the rest. Ask them everywhere.",
  "honesty.promise_title": "What we promise in writing",
  "honesty.promise1": "Every commitment you get from us is in writing.",
  "honesty.promise2": "Three full steps are free before you pay a rupee.",
  "honesty.promise3": "The placement fee is due only after you accept an offer.",
  "honesty.promise4": "30-day money-back guarantee — any reason, full refund.",
  "honesty.promise5": "Every counselling call is recorded & AI-monitored.",
  "honesty.never_title": "What we will never tell you",
  "honesty.never1": "“We guarantee you a job.” Nobody honestly can — the market decides.",
  "honesty.never2": "“We'll adjust your experience.” We never fabricate employment.",
  "honesty.never3": "A fixed salary promise.",
  "honesty.never4": "That hiring is certain, or that there's a shortcut.",

  /* ------------------------------- stories ------------------------------ */
  "stories.kicker": "Success stories",
  "stories.headline_start": "From stuck",
  "stories.headline_highlight": "→ hired.",
  "stories.body":
    "Where learners started, the package and company they landed, the rounds they cleared — and the exact question the interview threw at them.",
  "stories.disclaimer":
    "Based on BrowseJobs internal data. Historical figures — not a promise of individual outcome. Cards marked “Sample” are illustrative — real, consented student stories replace them as they're published.",

  /* ------------------------------- reviews ------------------------------ */
  "reviews.kicker": "In their words",
  "reviews.headline": "The people who did it, on the record.",
  "reviews.body": "Real reviews from Google, JustDial and verified students — never a fabricated one.",

  /* -------------------------------- intel ------------------------------- */
  "intel.kicker": "The intelligence board",
  "intel.headline_start": "Live market signal,",
  "intel.headline_highlight": "read by the engine.",
  "intel.rising_label": "Heating up — learn these now",
  "intel.cooling_label": "Cooling down — don't bet a career on these",

  /* ------------------------------- numbers ------------------------------ */
  "numbers.kicker": "The receipts",
  "numbers.stat1_label": "students trained",
  "numbers.stat2_label": "success rate among students who attend interviews",
  "numbers.stat3_label": "highest package (₹)",
  "numbers.footnote_start": "Every number above is backed by verifiable student records —",
  "numbers.footnote_highlight": "scan any certificate's QR to check us.",

  /* -------------------------------- final ------------------------------- */
  "final.headline_start": "The offer letter",
  "final.headline_highlight": "already has a date.",
  "final.body": "One free masterclass decides if this is for you. 45 minutes. Zero pressure.",
  "final.cta": "Claim my free seat →",
  "final.note": "Next batch closes Sunday midnight",
} as const;

export type HomepageCopyKey = keyof typeof HOMEPAGE_DEFAULTS;

const HomepageCopyContext = createContext<Record<string, string>>({});

export const HomepageCopyProvider = HomepageCopyContext.Provider;

/**
 * Reads one string. CRM value wins; a blank or missing override falls back to
 * the shipped copy, so clearing a box in the CRM restores the original line.
 */
export function useCopy(): (key: HomepageCopyKey) => string {
  const overrides = useContext(HomepageCopyContext);

  return (key) => {
    const override = overrides[key];

    return typeof override === "string" && override.trim() !== "" ? override : HOMEPAGE_DEFAULTS[key];
  };
}
