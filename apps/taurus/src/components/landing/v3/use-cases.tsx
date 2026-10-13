"use client";

/**
 * Landing v3 — use cases as AI crews (Sprint 058).
 *
 * Five jobs a business hands to Taurus, each done by a small crew of AI
 * Employees that pass work to one another — the cross-functional story —
 * with the points where a person approves marked in the trace. Told as a
 * stacked card deck: each card pins near the top of the viewport and the
 * next slides over it while the pinned card recedes. Every card runs
 * Situation → the crew → an animated hand-off trace → what changes.
 *
 * Results are deliberately structural ("nothing posts without approval"),
 * never invented percentages: these are illustrative designs, not customer
 * claims, and the disclosure under the deck says so.
 */

import { motion, useScroll, useTransform } from "framer-motion";
import { useEffect, useRef, useState } from "react";
import { EASE, Magnetic, Reveal, SectionHeading } from "@/components/landing/v3/motion";
import { withBasePath } from "@/lib/base-path";

interface CrewMember {
  name: string;
  job: string;
}

interface Step {
  /** Which crew member does it — or "You" for a human approval. */
  who: string;
  what: string;
  approval?: boolean;
}

export interface CrewStory {
  id: string;
  tag: string;
  headline: string;
  situation: string;
  crew: CrewMember[];
  steps: Step[];
  channels: string[];
  outcomes: { k: string; v: string }[];
  cta: string;
  dark: boolean;
}

export const CREW_STORIES: CrewStory[] = [
  {
    id: "social-media",
    tag: "Social media manager",
    headline: "Always-on social media, in your voice, without the 11pm scramble.",
    situation:
      "Posting consistently across Instagram, LinkedIn and X takes hours a week, comments go unanswered over the weekend, and nobody has time to see what actually worked.",
    crew: [
      { name: "Planner", job: "Builds the weekly content calendar from your goals and launches" },
      { name: "Writer", job: "Drafts captions, threads and carousels in your brand voice" },
      { name: "Brand reviewer", job: "Checks every draft against your DNA and boundaries" },
      { name: "Community", job: "Replies to comments and DMs, escalates complaints" },
    ],
    steps: [
      { who: "Planner", what: "Turns this month's launches into a weekly calendar" },
      { who: "Writer", what: "Drafts posts for each platform from the Knowledge Vault" },
      { who: "Brand reviewer", what: "Flags off-brand claims and fixes tone" },
      { who: "You", what: "Approve the week's posts in one review", approval: true },
      { who: "Planner", what: "Schedules them at each platform's best time" },
      { who: "Community", what: "Answers comments and DMs around the clock" },
      { who: "Community", what: "Hands a complaint to your team with the full thread" },
    ],
    channels: ["Instagram", "LinkedIn", "X", "Facebook", "WhatsApp"],
    outcomes: [
      { v: "0", k: "posts published without your approval" },
      { v: "24/7", k: "comments and DMs answered" },
      { v: "1", k: "weekly review instead of daily firefighting" },
    ],
    cta: "Build a social media crew",
    dark: true,
  },
  {
    id: "lead-generation",
    tag: "Lead generator + SDR",
    headline: "A pipeline that fills itself — and only hands sales the warm ones.",
    situation:
      "Sales spends its mornings researching prospects and writing cold emails, then forgets to follow up. Warm replies sit in an inbox while the next list gets built.",
    crew: [
      { name: "Researcher", job: "Finds companies and people that match your ideal customer" },
      { name: "Qualifier", job: "Scores each lead against your criteria and enriches the record" },
      { name: "Outreach", job: "Writes personal first emails and follows up on schedule" },
      { name: "Booker", job: "Answers replies, handles objections, books the meeting" },
    ],
    steps: [
      { who: "Researcher", what: "Builds a list from your ideal-customer profile" },
      { who: "Qualifier", what: "Scores and enriches every lead, drops poor fits" },
      { who: "You", what: "Approve the first email template and the send volume", approval: true },
      { who: "Outreach", what: "Sends personal first emails and three follow-ups" },
      { who: "Booker", what: "Reads replies and answers questions from your docs" },
      { who: "Booker", what: "Books the meeting on a sales rep's calendar" },
      { who: "Qualifier", what: "Writes the CRM record and a call brief for the rep" },
    ],
    channels: ["Email", "LinkedIn", "WhatsApp", "Website chat", "CRM via webhook"],
    outcomes: [
      { v: "100%", k: "of replies answered the same day" },
      { v: "0", k: "CRM records typed by hand" },
      { v: "1", k: "brief per meeting, written before the call" },
    ],
    cta: "Build a lead generation crew",
    dark: false,
  },
  {
    id: "support-desk",
    tag: "Customer support desk",
    headline: "Instant answers on every channel, and a human for the hard ones.",
    situation:
      "Customers ask the same twenty questions on chat, email and WhatsApp while urgent cases wait in the same queue — and every hand-off makes them repeat themselves.",
    crew: [
      { name: "Front desk", job: "Answers instantly from your help docs and order data" },
      { name: "Specialist", job: "Handles billing, refunds and technical cases" },
      { name: "Quality", job: "Reviews conversations and suggests knowledge updates" },
    ],
    steps: [
      { who: "Front desk", what: "Greets the customer and identifies the order" },
      { who: "Front desk", what: "Answers from the Knowledge Vault, citing the policy" },
      { who: "Specialist", what: "Takes over a refund request with the full context" },
      { who: "You", what: "Approve refunds above your limit", approval: true },
      { who: "Specialist", what: "Confirms the outcome and closes the ticket" },
      { who: "Quality", what: "Reviews the week's conversations against your DNA" },
      { who: "Quality", what: "Proposes help-article fixes for the gaps it found" },
    ],
    channels: ["Website chat", "WhatsApp", "Email", "SMS", "Phone"],
    outcomes: [
      { v: "Seconds", k: "to a first answer, any hour" },
      { v: "100%", k: "answers grounded in your own docs" },
      { v: "0", k: "times a customer repeats themselves" },
    ],
    cta: "Build a support crew",
    dark: false,
  },
  {
    id: "recruitment",
    tag: "Recruitment pipeline",
    headline: "From job post to shortlist, interviewed and scored, while you sleep.",
    situation:
      "Recruiters lose days to screening CVs, chasing candidates for slots and writing the same updates to hiring managers.",
    crew: [
      { name: "Sourcer", job: "Writes the job post and finds matching candidates" },
      { name: "Screener", job: "Ranks CVs against the role and shortlists" },
      { name: "Interviewer", job: "Runs a spoken AI interview and scores it" },
      { name: "Coordinator", job: "Books slots and sends every update on WhatsApp" },
    ],
    steps: [
      { who: "Sourcer", what: "Drafts the job description from one message" },
      { who: "You", what: "Approve the post before it goes live", approval: true },
      { who: "Screener", what: "Scores every applicant and builds the shortlist" },
      { who: "You", what: "Say yes to start round one", approval: true },
      { who: "Coordinator", what: "Invites candidates to pick an interview slot" },
      { who: "Interviewer", what: "Interviews, scores and flags anything unusual" },
      { who: "Coordinator", what: "Sends you each report with the CV attached" },
    ],
    channels: ["WhatsApp", "Email", "Job boards", "Video interview room"],
    outcomes: [
      { v: "Every", k: "applicant gets a reply" },
      { v: "1", k: "scored report per interview, with the CV" },
      { v: "0", k: "rounds sent without your yes" },
    ],
    cta: "Build a recruitment crew",
    dark: false,
  },
  {
    id: "operations-reporting",
    tag: "Operations & reporting",
    headline: "The numbers on your desk on Monday, and the exceptions already chased.",
    situation:
      "Leaders wait days for the weekly report because the data lives in five tools, and the exceptions it reveals land on someone's desk too late.",
    crew: [
      { name: "Data", job: "Pulls figures from your databases, sheets and tools" },
      { name: "Analyst", job: "Explains what changed and why, citing every source" },
      { name: "Ops", job: "Chases the exceptions with the right owner" },
    ],
    steps: [
      { who: "Data", what: "Syncs sales, stock and support data every night" },
      { who: "Analyst", what: "Writes the weekly report in plain language" },
      { who: "Analyst", what: "Flags late orders and low stock as exceptions" },
      { who: "Ops", what: "Messages each owner and tracks the follow-up" },
      { who: "You", what: "Approve any purchase order before it is raised", approval: true },
      { who: "Ops", what: "Raises the order and logs every step" },
      { who: "Analyst", what: "Delivers the report at 8am every Monday" },
    ],
    channels: ["Databases", "Google Drive", "SharePoint", "Slack", "Microsoft Teams", "Email"],
    outcomes: [
      { v: "8am", k: "your Monday report, every Monday" },
      { v: "100%", k: "figures with their source cited" },
      { v: "0", k: "exceptions left without an owner" },
    ],
    cta: "Build an operations crew",
    dark: true,
  },
];

/** Tailwind's lg breakpoint: where the crew cards pin and recede. */
function useWideScreen(): boolean {
  const [wide, setWide] = useState(false);
  useEffect(() => {
    const query = window.matchMedia("(min-width: 1024px)");
    const update = () => setWide(query.matches);
    update();
    query.addEventListener("change", update);
    return () => query.removeEventListener("change", update);
  }, []);
  return wide;
}

const label = (ink: boolean) => `text-[11px] font-semibold uppercase tracking-[0.24em] ${ink ? "text-neutral-500" : "text-neutral-400"}`;

/** One pinned crew card that recedes as the next slides over it. */
function CrewCard({ story, index }: { story: CrewStory; index: number }) {
  const ref = useRef<HTMLDivElement>(null);
  const { scrollYProgress } = useScroll({ target: ref, offset: ["start start", "end start"] });
  const wide = useWideScreen();
  const scale = useTransform(scrollYProgress, [0, 1], [1, wide ? 0.94 : 1]);
  const dim = useTransform(scrollYProgress, [0, 1], [1, wide ? 0.55 : 1]);

  const ink = story.dark;
  return (
    // Pinned only on wide screens: stacked in one column, a card is taller
    // than a phone screen, and a pinned card that tall hides its own bottom.
    <div ref={ref} id={story.id} className="relative mb-6 scroll-mt-24 lg:sticky lg:top-[10vh] lg:mb-8" style={{ zIndex: index + 1 }}>
      <motion.article
        style={{ scale, opacity: dim }}
        className={`origin-top overflow-hidden rounded-[2rem] border shadow-[0_40px_100px_-40px_rgba(0,0,0,0.4)] ${
          ink ? "border-neutral-800 bg-neutral-950 text-white" : "border-neutral-200 bg-white text-neutral-950"
        }`}
      >
        <div className="grid gap-8 p-6 sm:p-10 lg:grid-cols-[1fr_1fr] lg:gap-12">
          {/* The job and the crew */}
          <div>
            <p className={label(ink)}>
              {String(index + 1).padStart(2, "0")} · {story.tag}
            </p>
            <h3 className="mt-3 text-balance text-2xl font-semibold leading-tight tracking-[-0.02em] sm:text-[2.1rem]">
              {story.headline}
            </h3>
            <p className={`mt-4 text-[15px] leading-relaxed ${ink ? "text-neutral-300" : "text-neutral-600"}`}>{story.situation}</p>

            <p className={`mt-7 ${label(ink)}`}>The crew</p>
            <ul className="mt-3 grid gap-2 sm:grid-cols-2">
              {story.crew.map((m) => (
                <li
                  key={m.name}
                  className={`rounded-2xl border px-3.5 py-3 ${ink ? "border-neutral-800 bg-neutral-900/60" : "border-neutral-200 bg-neutral-50/70"}`}
                >
                  <p className="text-[13px] font-semibold">{m.name}</p>
                  <p className={`mt-0.5 text-[12px] leading-snug ${ink ? "text-neutral-400" : "text-neutral-500"}`}>{m.job}</p>
                </li>
              ))}
            </ul>

            <p className={`mt-6 ${label(ink)}`}>Works on</p>
            <p className="mt-2 flex flex-wrap gap-1.5">
              {story.channels.map((c) => (
                <span
                  key={c}
                  className={`rounded-full border px-2.5 py-1 text-[11.5px] ${ink ? "border-neutral-800 text-neutral-300" : "border-neutral-200 text-neutral-600"}`}
                >
                  {c}
                </span>
              ))}
            </p>
          </div>

          {/* Hand-off trace + outcomes */}
          <div className="flex flex-col justify-between gap-7">
            <div>
              <p className={label(ink)}>How the crew hands it on</p>
              <div className="relative mt-3">
                <span aria-hidden className={`absolute bottom-2 left-[9px] top-2 w-px ${ink ? "bg-neutral-800" : "bg-neutral-200"}`} />
                <motion.ol
                  initial="hidden"
                  whileInView="show"
                  viewport={{ once: true, margin: "-60px" }}
                  variants={{ hidden: {}, show: { transition: { staggerChildren: 0.09 } } }}
                  className="space-y-1.5"
                >
                  {story.steps.map((s, i) => (
                    <motion.li
                      key={`${s.who}-${s.what}`}
                      variants={{ hidden: { opacity: 0, x: 16 }, show: { opacity: 1, x: 0, transition: { duration: 0.45, ease: EASE } } }}
                      className="relative flex items-center gap-3"
                    >
                      <span
                        className={`z-10 flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full text-[9px] font-bold ${
                          s.approval
                            ? ink
                              ? "bg-white text-neutral-950"
                              : "bg-neutral-950 text-white"
                            : ink
                              ? "bg-neutral-800 text-neutral-300"
                              : "bg-neutral-100 text-neutral-500"
                        }`}
                      >
                        {s.approval ? "✓" : i + 1}
                      </span>
                      <span
                        className={`flex flex-1 items-baseline gap-2 rounded-lg border px-3 py-1.5 text-[13px] ${
                          s.approval
                            ? ink
                              ? "border-neutral-600 bg-neutral-800 text-white"
                              : "border-neutral-900 bg-white text-neutral-950"
                            : ink
                              ? "border-neutral-800 bg-neutral-900/60 text-neutral-200"
                              : "border-neutral-100 bg-neutral-50/80 text-neutral-600"
                        }`}
                      >
                        <span className={`shrink-0 text-[10.5px] font-semibold uppercase tracking-[0.12em] ${s.approval ? "" : ink ? "text-neutral-500" : "text-neutral-400"}`}>
                          {s.who}
                        </span>
                        <span>{s.what}</span>
                      </span>
                    </motion.li>
                  ))}
                </motion.ol>
              </div>
            </div>

            <div>
              <p className={label(ink)}>What changes</p>
              <div className="mt-3 grid grid-cols-3 gap-2 sm:gap-3">
                {story.outcomes.map((o) => (
                  <div
                    key={o.k}
                    className={`rounded-2xl border px-3 py-3 ${ink ? "border-neutral-800 bg-neutral-900/60" : "border-neutral-200 bg-neutral-50/70"}`}
                  >
                    <p className="text-lg font-semibold tracking-tight sm:text-2xl">{o.v}</p>
                    <p className={`mt-1 text-[11px] leading-snug ${ink ? "text-neutral-400" : "text-neutral-500"}`}>{o.k}</p>
                  </div>
                ))}
              </div>
              <div className="mt-6">
                <Magnetic>
                  <a
                    href={withBasePath("/signup")}
                    className={`inline-flex min-h-[44px] items-center gap-2 rounded-full px-6 py-3 text-[13px] font-semibold transition-transform duration-200 hover:scale-[1.03] ${
                      ink ? "bg-white text-neutral-950" : "bg-neutral-950 text-white"
                    }`}
                  >
                    {story.cta} <span aria-hidden>→</span>
                  </a>
                </Magnetic>
              </div>
            </div>
          </div>
        </div>
      </motion.article>
    </div>
  );
}

export function UseCases() {
  return (
    <section id="use-cases" className="relative mx-auto max-w-6xl scroll-mt-24 px-5 py-28 sm:px-8 sm:py-36">
      <SectionHeading
        eyebrow="Use cases"
        title="Hand Taurus a job. A [crew of AI Employees] does it together."
        description="Five jobs businesses give Taurus first. Each is run by a small crew of specialised AI Employees that pass work to one another, and stop for you wherever it matters."
      />

      <Reveal>
        <nav aria-label="Use cases" className="mx-auto mt-10 flex max-w-3xl flex-wrap justify-center gap-2">
          {CREW_STORIES.map((s) => (
            <a
              key={s.id}
              href={`#${s.id}`}
              className="rounded-full border border-neutral-200 px-4 py-2 text-[13px] font-medium text-neutral-600 transition-colors hover:border-neutral-900 hover:text-neutral-950"
            >
              {s.tag}
            </a>
          ))}
        </nav>
      </Reveal>

      <div className="mt-14">
        {CREW_STORIES.map((story, i) => (
          <CrewCard key={story.id} story={story} index={i} />
        ))}
      </div>

      <Reveal className="text-center">
        <p className="text-[12px] text-neutral-500">
          Illustrative crews — they show how Taurus AI Employees are designed to work together, not customer claims. Every crew
          starts from a template you can change.
        </p>
      </Reveal>
    </section>
  );
}
