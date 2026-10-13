"use client";

/**
 * Landing v3 — partners and white-label (Sprint 058).
 *
 * For agencies, consultancies and software companies that want to give
 * Taurus to their own clients under their own name. Labelled early access:
 * the partner console (branding, client workspaces, user groups) is being
 * built, so the section sells a programme to apply for, not a switch to flip
 * today. The preview swaps one console between partner brands to show what
 * "your brand, not ours" means.
 */

import { AnimatePresence, motion } from "framer-motion";
import { useState } from "react";
import { CONTACT_EMAIL } from "@/components/landing/v3/contact";
import { EASE, PrimaryCta, Reveal, RevealGroup, RevealItem, SectionHeading } from "@/components/landing/v3/motion";

const PARTNER_MAIL = `mailto:${CONTACT_EMAIL}?subject=${encodeURIComponent("Taurus partner programme (white-label)")}`;

export const PARTNER_FEATURES = [
  { title: "Your brand", body: "Your logo, name and domain on the console, the chat widget and every message. Taurus stays invisible." },
  { title: "A workspace per client", body: "Each client gets their own crews, knowledge and channels, fully isolated from every other client." },
  { title: "User groups and roles", body: "Decide who builds crews, who approves, and who only watches, per client and per team." },
  { title: "One partner console", body: "See every client's crews, usage and health in one place, and step into any workspace to help." },
  { title: "Your pricing", body: "Bill clients your way. Taurus bills you on wholesale usage, so the margin is yours." },
  { title: "Templates you own", body: "Package your best crews as templates and roll them out to every client in a click." },
];

const BRANDS = [
  { name: "Northwind Digital", initial: "N", client: "Bloom Dental", crews: ["Front desk", "Reviews", "Recall reminders"] },
  { name: "Atlas Growth Co.", initial: "A", client: "Kite Logistics", crews: ["Lead generator", "Booker", "Weekly report"] },
  { name: "Your agency", initial: "Y", client: "Your client", crews: ["Social media", "Support desk", "Hiring"] },
];

function BrandPreview() {
  const [i, setI] = useState(0);
  const brand = BRANDS[i];

  return (
    <div className="rounded-[1.75rem] border border-neutral-800 bg-neutral-900 p-3 shadow-[0_40px_100px_-40px_rgba(0,0,0,0.6)]">
      <div role="tablist" aria-label="Preview a partner brand" className="mb-3 flex flex-wrap gap-1.5">
        {BRANDS.map((b, n) => (
          <button
            key={b.name}
            type="button"
            role="tab"
            aria-selected={n === i}
            onClick={() => setI(n)}
            className={`min-h-[36px] rounded-full px-3.5 text-[12px] font-semibold transition-colors ${
              n === i ? "bg-white text-neutral-950" : "text-neutral-400 hover:text-white"
            }`}
          >
            {b.name}
          </button>
        ))}
      </div>

      <div className="overflow-hidden rounded-[1.25rem] bg-white text-neutral-950">
        <AnimatePresence mode="wait">
          <motion.div
            key={brand.name}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            transition={{ duration: 0.35, ease: EASE }}
          >
            <div className="flex items-center justify-between border-b border-neutral-100 px-4 py-3">
              <div className="flex items-center gap-2.5">
                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-neutral-950 text-[14px] font-bold text-white">{brand.initial}</span>
                <span className="text-[14px] font-semibold">{brand.name}</span>
              </div>
              <span className="rounded-full border border-neutral-200 px-2.5 py-1 text-[11px] text-neutral-500">{brand.client}</span>
            </div>
            <div className="grid gap-2 p-4 sm:grid-cols-3">
              {brand.crews.map((c, n) => (
                <div key={c} className="rounded-xl border border-neutral-200 p-3">
                  <p className="text-[12.5px] font-semibold">{c}</p>
                  <p className="mt-1 flex items-center gap-1.5 text-[11px] text-neutral-500">
                    <span className={`h-1.5 w-1.5 rounded-full ${n === 1 ? "bg-neutral-400" : "bg-neutral-950"}`} />
                    {n === 1 ? "Waiting for approval" : "Working"}
                  </p>
                </div>
              ))}
            </div>
            <p className="border-t border-neutral-100 px-4 py-2.5 text-[11px] text-neutral-400">
              app.{brand.name.toLowerCase().replace(/[^a-z]+/g, "")}.com · your domain, your brand
            </p>
          </motion.div>
        </AnimatePresence>
      </div>
    </div>
  );
}

export function Partners() {
  return (
    <section id="partners" className="relative scroll-mt-24 bg-neutral-950 py-28 text-white sm:py-36">
      <div className="mx-auto max-w-6xl px-5 sm:px-8">
        <div className="grid items-center gap-12 lg:grid-cols-[1fr_1fr]">
          <div>
            <Reveal>
              <span className="inline-flex rounded-full border border-neutral-700 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-neutral-300">
                Partner programme · early access
              </span>
            </Reveal>
            <div className="mt-6">
              <SectionHeading
                align="left"
                dark
                eyebrow="Partners & white-label"
                title="Give your clients AI crews, [under your own brand]."
                description="For agencies, consultancies and software companies. Run Taurus as your own product: your name on it, a workspace per client, and one console to manage them all."
              />
            </div>
            <Reveal className="mt-8 flex flex-wrap gap-3">
              <PrimaryCta href={PARTNER_MAIL} dark>
                Apply for early access
              </PrimaryCta>
            </Reveal>
          </div>
          <Reveal>
            <BrandPreview />
          </Reveal>
        </div>

        <RevealGroup className="mt-16 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {PARTNER_FEATURES.map((f) => (
            <RevealItem key={f.title}>
              <div className="h-full rounded-[1.25rem] border border-neutral-800 bg-neutral-900/60 p-5">
                <p className="text-[15px] font-semibold">{f.title}</p>
                <p className="mt-1.5 text-[13.5px] leading-relaxed text-neutral-400">{f.body}</p>
              </div>
            </RevealItem>
          ))}
        </RevealGroup>
      </div>
    </section>
  );
}
