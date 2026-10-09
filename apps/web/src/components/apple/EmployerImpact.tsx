import Link from "next/link";
import { Disclaimer } from "@/components/brand/Disclaimer";

const USUALLY = [
  "Sourcing",
  "Screening calls",
  "Scheduling rounds",
  "Interviews",
  "Background checks",
  "The offer",
  "Dropouts",
] as const;

const WITH_US = [
  "Pre-qualified candidates who already cleared the AI interview",
  "We call and screen shortlisted candidates",
  "L1 and L2 rounds run for you",
  "Pre-BGV",
  "An offer ready for your approval",
] as const;

const GETS = [
  {
    title: "Who does what.",
    body: "BrowseJobs, the team and the tools, runs sourcing through pre-BGV. You meet people who already cleared. A person always releases the offer.",
  },
  {
    title: "What you see.",
    body: "The hiring floor shows the role, the shortlist, calls, rounds, pre-BGV, and the offer waiting for you. The floor on this site is sample data.",
  },
  {
    title: "The outcome.",
    body: "Time saved. Fewer interviews for your team. Only pre-qualified candidates. You still decide the offer.",
  },
] as const;

export function EmployerImpact({ dark = false }: { dark?: boolean }) {
  const muted = dark ? "text-[#a1a1a6]" : "text-[#424245]";
  const card = dark ? "border-white/15 bg-white/5" : "border-black/10 bg-white";
  return (
    <section id="impact" className={dark ? "apple-dark apple-shade text-white" : "apple-mist text-[#1d1d1f]"}>
      <div className="apple-tile text-center">
        <h2 className="apple-display text-[clamp(2.75rem,6vw,5rem)] leading-[0.98]">
          90 days <span className="text-[#6e6e73]">→</span> 3 days.
          <sup className="align-super text-[0.35em] font-medium">1</sup>
        </h2>
        <p className={`apple-sub mt-5 ${muted}`}>Where those 90 days usually go, and what we take off your team.</p>
        <div className="apple-compare">
          <div className={`rounded-[22px] border p-6 text-left md:p-8 ${card}`}>
            <h3 className={`text-[13px] font-medium uppercase tracking-[0.08em] ${muted}`}>Usually</h3>
            <p className="mt-2 text-[28px] font-semibold tracking-[-0.03em]">90 days</p>
            <ul className="mt-6 space-y-3 text-[17px] leading-snug">
              {USUALLY.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>
          </div>
          <div className={`rounded-[22px] border p-6 text-left md:p-8 ${card}`}>
            <h3 className="text-[13px] font-medium uppercase tracking-[0.08em] text-[#1b6df0]">With BrowseJobs</h3>
            <p className="mt-2 text-[28px] font-semibold tracking-[-0.03em]">About 3 days</p>
            <ul className="mt-6 space-y-3 text-[17px] leading-snug">
              {WITH_US.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>
          </div>
        </div>
        <div className="apple-note mt-6">
          <Disclaimer />
        </div>
        <div className="mx-auto mt-14 grid max-w-[960px] gap-8 text-left md:grid-cols-3">
          {GETS.map((item) => (
            <div key={item.title}>
              <h3 className="text-[21px] font-semibold tracking-[-0.02em]">{item.title}</h3>
              <p className={`mt-2 text-[17px] leading-snug ${muted}`}>{item.body}</p>
            </div>
          ))}
        </div>
        <div className="mt-12">
          <Link href="/employers/enquire" className="apple-pill max-w-full whitespace-normal px-6 text-center leading-snug">
            Onboard with us for the future of hiring
          </Link>
        </div>
      </div>
    </section>
  );
}
