/**
 * Post-submission copy for /thank-you. Every public lead form lands here with
 * its `lead_type`, so the confirmation tells the person what they actually
 * asked for and what happens next — instead of a generic "we'll be in touch".
 *
 * Brand voice (CLAUDE.md §Brand Voice): the steps describe what *we* will do,
 * with no promise about hiring outcomes and no invented timelines beyond the
 * speed-to-lead SLA the CRM already enforces.
 */

import type { LeadVariant } from "@/components/landing/leadModalBus";

/** Every lead_type the public site can submit (Lead::TYPES on the API). */
export type ThankYouVariant = LeadVariant | "syllabus" | "bootcamp" | "contact" | "employer";

export type ThankYouCopy = {
  /** Mono kicker above the headline. */
  kicker: string;
  title: string;
  body: string;
  /** Ordered "what happens next" steps. */
  steps: readonly { title: string; body: string }[];
};

const CONTACT_STEP = {
  title: "We call you",
  body: "A counsellor calls the number you gave us, usually within 15 minutes during working hours. If you'd rather we didn't call, tell them on WhatsApp and we'll keep it to messages.",
} as const;

export const THANK_YOU: Record<ThankYouVariant, ThankYouCopy> = {
  masterclass: {
    kicker: "Seat requested",
    title: "Your masterclass seat is being confirmed.",
    body: "The masterclass is a live session showing how the syllabus is reverse-engineered from real interviews. It's free, and it's the first of three free steps — nothing is payable until after the bootcamp.",
    steps: [
      CONTACT_STEP,
      {
        title: "You get the joining link",
        body: "The link arrives on WhatsApp once your seat is confirmed, with a reminder before the session starts. Nothing to remember.",
      },
      {
        title: "You attend, then decide",
        body: "After the masterclass you're invited to the free 7-hour Python bootcamp. Registration only comes up after that — and only if you want it.",
      },
    ],
  },

  counselling: {
    kicker: "Counselling booked",
    title: "Your free counselling call is on its way.",
    body: "You'll leave the call with a written Career Analysis Report — whether or not you ever join a program. That's the point of it.",
    steps: [
      CONTACT_STEP,
      {
        title: "You get the written report",
        body: "A Career Analysis Report covering where you are now, the roles worth targeting, and the gap between the two. Yours to keep.",
      },
      {
        title: "You're invited to the masterclass",
        body: "If the direction makes sense, the next free step is the live masterclass. No obligation either way.",
      },
    ],
  },

  waitlist: {
    kicker: "On the waitlist",
    title: "You're on the list for this program.",
    body: "This program hasn't opened yet. You'll be told before it's announced publicly, so you get first pick of the batch.",
    steps: [
      {
        title: "We hold your place",
        body: "No payment, no commitment — just your name against this program.",
      },
      {
        title: "You hear first",
        body: "When dates are set, we message you on WhatsApp before the batch is announced anywhere else.",
      },
      {
        title: "Meanwhile, the masterclass is open",
        body: "The free live masterclass runs on the programs already open. It's the fastest way to see whether the method suits you.",
      },
    ],
  },

  syllabus: {
    kicker: "Syllabus sent",
    title: "The syllabus is on its way.",
    body: "It's the real module-by-module breakdown, rebuilt each month from the questions asked in live interviews — not a marketing one-pager.",
    steps: [
      {
        title: "Check your inbox",
        body: "The download link is in your email. If it hasn't arrived in a few minutes, check spam, then call us.",
      },
      CONTACT_STEP,
      {
        title: "See it taught live",
        body: "The free masterclass walks through how this syllabus gets rebuilt. Worth an hour before you decide anything.",
      },
    ],
  },

  bootcamp: {
    kicker: "Bootcamp seat requested",
    title: "Your free bootcamp seat is being confirmed.",
    body: "Seven hours of hands-on Python, free, and the last of the three free steps before anything is payable.",
    steps: [
      CONTACT_STEP,
      {
        title: "You get the joining link",
        body: "Sent on WhatsApp with a reminder before the session. Bring a laptop — it's hands-on throughout.",
      },
      {
        title: "You decide afterwards",
        body: "Registration is discussed only after the bootcamp, once you've seen how we actually teach.",
      },
    ],
  },

  contact: {
    kicker: "Message received",
    title: "Thanks — we have your message.",
    body: "Someone from the team will get back to you on the number you gave us.",
    steps: [
      CONTACT_STEP,
      {
        title: "You get a straight answer",
        body: "If we can't help with what you asked, we'll tell you that rather than route you into a sales call.",
      },
    ],
  },

  employer: {
    kicker: "Enquiry received",
    title: "Thanks — we have your hiring enquiry.",
    body: "The first six months run free. Before any of it starts, we'll walk through the pipeline on one of your live roles so you can see what your team would actually receive.",
    steps: [
      {
        title: "We set up the onboarding call",
        body: "Someone from the team reaches out to book a walkthrough, usually within two working days.",
      },
      {
        title: "Your first role goes live",
        body: "We run one of your open JDs through the pipeline end to end and show you the candidate brief it produces.",
      },
      {
        title: "Commercials in writing",
        body: "Whichever model fits — agency or per interview — is agreed and put in writing before anything is charged.",
      },
    ],
  },
};

/** Falls back to the generic contact copy for an unknown or missing type. */
export function thankYouCopy(variant: string | undefined): ThankYouCopy {
  if (variant && variant in THANK_YOU) return THANK_YOU[variant as ThankYouVariant];
  return THANK_YOU.contact;
}
