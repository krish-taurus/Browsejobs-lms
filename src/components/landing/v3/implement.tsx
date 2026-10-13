"use client";

/**
 * Landing v3 — "Put Taurus to work in your business" (Sprint 058).
 *
 * The practical answer to "how would we actually roll this out?": a
 * five-stage plan over about three weeks, what the business needs to bring,
 * and who does what. Written for an operations or founder audience, not a
 * developer: no code, no integration jargon. The timeline is typical, not a
 * promise, and says so.
 */

import { motion } from "framer-motion";
import { EASE, PrimaryCta, Reveal, RevealGroup, RevealItem, SecondaryCta, SectionHeading } from "@/components/landing/v3/motion";

export interface PlanStage {
  when: string;
  title: string;
  body: string;
  done: string;
}

export const PLAN: PlanStage[] = [
  {
    when: "Day 1",
    title: "Pick one job and a crew template",
    body: "Start with the job that eats the most hours: social media, lead follow-up, support, hiring or reporting. Choose its crew template and name the person who owns the outcome.",
    done: "A crew of AI Employees, already set up for that job",
  },
  {
    when: "Days 2–3",
    title: "Teach it your business",
    body: "Upload the documents your team already uses (price lists, policies, FAQs, brand guide) or connect Google Drive, SharePoint or a database. Write the crew's DNA: goals, tone of voice, what it must never do.",
    done: "A Knowledge Vault and DNA the crew answers from",
  },
  {
    when: "Day 4",
    title: "Connect channels and set the guardrails",
    body: "Switch on the channels the job runs on: website chat, WhatsApp, email, Slack or phone. Decide where a person must approve (posts, refunds, offers, sends above a volume) and who gets escalations.",
    done: "Live channels, approval steps and escalation rules",
  },
  {
    when: "Week 2",
    title: "Pilot in shadow mode",
    body: "The crew drafts everything and your team approves every step for a week. Fix the knowledge gaps it finds, tighten the DNA, and watch the first Performance Review.",
    done: "A crew your team trusts, with its first scorecard",
  },
  {
    when: "Week 3 →",
    title: "Hand over, measure, add the next crew",
    body: "Relax the approvals that never needed changing, keep the ones that matter, and add the next job. Usage, workflow runs and reviews show what each crew is worth.",
    done: "A second job automated, with numbers to show for the first",
  },
];

export const BRING = [
  "One owner per job: someone who knows how it is done today",
  "The documents your team already answers from",
  "Access to the channels the job runs on (WhatsApp, email, chat…)",
  "Your rules: what needs a person's approval, and who to escalate to",
  "Optional: your own AI model key, if you prefer your own provider",
];

export const SPLIT: { who: string; items: string[] }[] = [
  {
    who: "Taurus does",
    items: [
      "Sets up the crew from a proven template",
      "Answers, drafts, follows up and hands work on, 24/7",
      "Pauses for approval wherever you said so",
      "Keeps a full audit trail and weekly performance reviews",
    ],
  },
  {
    who: "Your team does",
    items: [
      "Owns the outcome and the rules",
      "Approves what matters, in one tap",
      "Handles the escalations the crew hands over",
      "Decides which job to hand over next",
    ],
  },
];

export function Implement() {
  return (
    <section id="implement" className="relative scroll-mt-24 bg-neutral-50 py-28 sm:py-36">
      <div className="mx-auto max-w-6xl px-5 sm:px-8">
        <SectionHeading
          eyebrow="Implement it in your business"
          title="From first conversation to a [working crew] in about three weeks."
          description="No engineering project. One job at a time, piloted with your team before anything runs on its own."
        />

        {/* The plan */}
        <ol className="relative mt-16 space-y-4">
          <span aria-hidden className="absolute bottom-6 left-[19px] top-6 hidden w-px bg-neutral-200 sm:block" />
          {PLAN.map((stage, i) => (
            <motion.li
              key={stage.title}
              initial={{ opacity: 0, y: 24 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, margin: "-80px" }}
              transition={{ duration: 0.6, ease: EASE, delay: i * 0.05 }}
              className="relative grid gap-4 rounded-[1.5rem] border border-neutral-200 bg-white p-5 sm:grid-cols-[auto_1fr_0.8fr] sm:items-start sm:gap-6 sm:p-7"
            >
              <span className="z-10 flex h-10 w-10 items-center justify-center rounded-full bg-neutral-950 text-[13px] font-semibold text-white">
                {i + 1}
              </span>
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-neutral-400">{stage.when}</p>
                <h3 className="mt-1.5 text-lg font-semibold tracking-tight text-neutral-950 sm:text-xl">{stage.title}</h3>
                <p className="mt-2 text-[14.5px] leading-relaxed text-neutral-600">{stage.body}</p>
              </div>
              <div className="rounded-2xl border border-neutral-100 bg-neutral-50 px-4 py-3">
                <p className="text-[10.5px] font-semibold uppercase tracking-[0.2em] text-neutral-400">You end up with</p>
                <p className="mt-1 text-[13.5px] font-medium leading-snug text-neutral-800">{stage.done}</p>
              </div>
            </motion.li>
          ))}
        </ol>

        {/* What you bring + who does what */}
        <div className="mt-14 grid gap-4 lg:grid-cols-[1fr_1.3fr]">
          <Reveal>
            <div className="h-full rounded-[1.5rem] border border-neutral-200 bg-white p-6 sm:p-8">
              <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-neutral-400">What you bring</p>
              <ul className="mt-4 space-y-3">
                {BRING.map((item) => (
                  <li key={item} className="flex gap-3 text-[14.5px] leading-relaxed text-neutral-700">
                    <span aria-hidden className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-neutral-950" />
                    {item}
                  </li>
                ))}
              </ul>
            </div>
          </Reveal>
          <RevealGroup className="grid gap-4 sm:grid-cols-2">
            {SPLIT.map((side, i) => (
              <RevealItem key={side.who}>
                <div
                  className={`h-full rounded-[1.5rem] border p-6 sm:p-8 ${
                    i === 0 ? "border-neutral-900 bg-neutral-950 text-white" : "border-neutral-200 bg-white text-neutral-950"
                  }`}
                >
                  <p className={`text-[11px] font-semibold uppercase tracking-[0.24em] ${i === 0 ? "text-neutral-500" : "text-neutral-400"}`}>
                    {side.who}
                  </p>
                  <ul className="mt-4 space-y-3">
                    {side.items.map((item) => (
                      <li key={item} className={`text-[14.5px] leading-relaxed ${i === 0 ? "text-neutral-200" : "text-neutral-700"}`}>
                        {item}
                      </li>
                    ))}
                  </ul>
                </div>
              </RevealItem>
            ))}
          </RevealGroup>
        </div>

        <Reveal className="mt-12 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
          <PrimaryCta href="/signup">Start with one job, free</PrimaryCta>
          <SecondaryCta href="#contact">Plan a rollout with us</SecondaryCta>
        </Reveal>
        <p className="mt-5 text-center text-[12px] text-neutral-500">A typical first rollout. Your timeline depends on the job and how quickly your team reviews the pilot.</p>
      </div>
    </section>
  );
}
