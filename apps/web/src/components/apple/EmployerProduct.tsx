import Link from "next/link";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { EMPLOYER_FAQ, EMPLOYER_WAYS } from "@/content/employer-landing";
import { MacFloor } from "./MacFloor";
import { More } from "./More";
import { PhoneInterview } from "./PhoneInterview";

const HIGHLIGHTS = [
  {
    id: "talk",
    dark: false,
    title: "Talk to it.",
    line: "Type the role, or say it. The sample floor starts from that line.",
    href: "/employers/how-it-works",
    about: "about starting a role",
  },
  {
    id: "calls",
    dark: true,
    title: "It calls and screens.",
    line: "Coming soon. The demo shows who was called, the outcome, and what they said.",
    href: "/employers/how-it-works",
    about: "about calls and screening",
  },
  {
    id: "interview",
    dark: false,
    title: "They interview first.",
    line: "Candidates take a free AI interview. 75% or more counts as a clear.",
    href: "/how-it-works",
    about: "about the AI interview",
  },
  {
    id: "rounds",
    dark: true,
    title: "L1, L2, and pre-BGV.",
    line: "Tracked on the floor. Background checks are coming soon.",
    href: "/employers/how-it-works",
    about: "about the hiring stages",
  },
  {
    id: "offer",
    dark: false,
    title: "You release the offer.",
    line: "Needs your approval. A person must always release the offer letter.",
    href: "/employers/faq",
    about: "about who releases an offer",
  },
] as const;

export function EmployerProduct() {
  return (
    <>
      <div className="sticky top-12 z-30 border-b border-white/10 bg-black/80 text-[12px] text-white backdrop-blur-xl">
        <nav
          className="apple-subnav mx-auto flex h-12 max-w-[1100px] items-center gap-6 overflow-x-auto px-5"
          aria-label="BrowseJobs AI Recruiter"
        >
          <span className="shrink-0 font-semibold tracking-[-0.01em]">BrowseJobs AI Recruiter</span>
          <a className="inline-flex h-11 shrink-0 items-center text-white/80 hover:text-white" href="#overview">
            Overview
          </a>
          <Link className="inline-flex h-11 shrink-0 items-center text-white/80 hover:text-white" href="/employers/how-it-works">
            How it works
          </Link>
          <Link
            className="inline-flex h-11 shrink-0 items-center text-white/80 hover:text-white"
            href="/employers/mission-control-demo"
          >
            Demo
          </Link>
          <Link className="inline-flex h-11 shrink-0 items-center text-white/80 hover:text-white" href="/employers/faq">
            FAQ
          </Link>
          <a className="inline-flex h-11 shrink-0 items-center text-white/80 hover:text-white" href="#get-started">
            Get started
          </a>
        </nav>
      </div>

      <section id="overview" className="apple-dark scroll-mt-28 bg-black px-5 pb-16 pt-10 text-center text-white md:pb-20 md:pt-12">
        <p className="text-[17px] text-[#a1a1a6]">For employers</p>
        <h1 className="apple-display mx-auto mt-3 max-w-[10ch] text-[clamp(3rem,6.6vw,6rem)]">
          Your AI{" "}
          <br />
          Recruiter.
        </h1>
        <p className="apple-sub mt-5 text-[#a1a1a6]">
          From 90 days to 3 days.<sup>1</sup> You still release the offer.
        </p>
        <div className="apple-note mt-4">
          <Disclaimer />
        </div>
        <div className="mt-6 flex flex-wrap items-center justify-center gap-x-7 gap-y-2">
          <a href="#get-started" className="apple-pill">
            Get started
          </a>
          <More href="/employers/mission-control-demo" about="of the hiring floor">
            Watch the demo
          </More>
        </div>
        <div className="mt-10">
          <MacFloor credit={false} tone="dark" />
        </div>
      </section>

      {HIGHLIGHTS.map((item) => (
        <section
          key={item.id}
          id={item.id}
          className={item.dark ? "apple-dark bg-black text-white" : "bg-[#f5f5f7] text-[#1d1d1f]"}
        >
          <div className="apple-tile text-center">
            <h2 className="apple-display mx-auto max-w-[16ch] text-[clamp(2.75rem,5.2vw,4.75rem)]">{item.title}</h2>
            <p className={`apple-sub mt-4 ${item.dark ? "text-[#a1a1a6]" : "text-[#424245]"}`}>{item.line}</p>
            {item.id === "interview" ? (
              <div className="apple-note mt-4">
                <Disclaimer />
              </div>
            ) : null}
            <div className="mt-2">
              <More href={item.href} about={item.about}>
                Learn more
              </More>
            </div>
            <div className="mt-12">
              <HighlightShot id={item.id} dark={item.dark} />
            </div>
          </div>
        </section>
      ))}

      <section id="faq" className="scroll-mt-28 bg-white">
        <div className="apple-tile mx-auto max-w-[720px]">
          <h2 className="apple-display text-center text-[clamp(2.25rem,4vw,3rem)]">A few answers.</h2>
          <div className="mt-8 divide-y divide-black/10 border-y border-black/10">
            {EMPLOYER_FAQ.filter((_, index) => index === 0 || index === 1 || index === 5).map((item) => (
              <details key={item.q} className="group py-1">
                <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 py-3 text-left text-[17px] font-medium tracking-[-0.015em] [&::-webkit-details-marker]:hidden">
                  {item.q}
                  <span aria-hidden className="text-[22px] font-normal text-[#6e6e73] transition-transform duration-150 group-open:rotate-45">
                    +
                  </span>
                </summary>
                <p className="max-w-[60ch] pb-4 text-[17px] leading-relaxed text-[#424245]">{item.a}</p>
              </details>
            ))}
          </div>
          <div className="mt-2 text-center">
            <More href="/employers/faq" about="in the full FAQ">
              Learn more
            </More>
          </div>
        </div>
      </section>

      <section id="get-started" className="scroll-mt-28 bg-[#f5f5f7] text-center">
        <div className="apple-tile">
          <h2 className="apple-display text-[clamp(2.75rem,5vw,4.5rem)]">Get started.</h2>
          <p className="apple-sub mt-4 text-[#424245]">We can run hiring with you, or your team can use the tool.</p>
          <div className="mx-auto mt-8 flex max-w-[640px] flex-col items-center gap-2">
            {EMPLOYER_WAYS.map((way) =>
              way.primary ? (
                <Link key={way.id} href={way.href} className="apple-pill w-full max-w-[22rem] whitespace-normal px-6 text-center leading-snug">
                  {way.cta}
                </Link>
              ) : (
                <Link key={way.id} href={way.href} className="apple-more max-w-full whitespace-normal text-center">
                  {way.cta}
                  <span aria-hidden="true">›</span>
                </Link>
              ),
            )}
          </div>
        </div>
      </section>
    </>
  );
}

function HighlightShot({ id, dark }: { id: (typeof HIGHLIGHTS)[number]["id"]; dark: boolean }) {
  if (id === "talk") {
    return (
      <div
        className={`mx-auto flex min-h-16 max-w-[560px] items-center rounded-full border px-6 text-left text-[21px] tracking-[-0.02em] ${
          dark ? "border-white/25" : "border-black/10 bg-white"
        }`}
      >
        <span className={dark ? "text-[#a1a1a6]" : "text-[#6e6e73]"}>Sample</span>
        <span className="ml-2">Backend engineers, Hyderabad</span>
      </div>
    );
  }
  if (id === "calls") {
    return (
      <div className="mx-auto w-[260px] rounded-[2.2rem] border border-white/15 bg-[#2c2c2e] p-[10px] text-white">
        <div className="rounded-[1.7rem] px-5 pb-8 pt-6">
          <div className="mx-auto h-5 w-20 rounded-full bg-black" aria-hidden />
          <p className="mt-8 text-[12px] text-[#a1a1a6]">Calls</p>
          <p className="mt-2 text-[28px] font-semibold tracking-[-0.03em]">Coming soon</p>
          <p className="mt-6 text-[14px] leading-snug text-[#a1a1a6]">The demo shows a sample call. Nothing is dialled.</p>
        </div>
      </div>
    );
  }
  if (id === "interview") return <PhoneInterview />;
  if (id === "rounds") {
    return (
      <ol className="mx-auto grid max-w-[640px] grid-cols-3 gap-3 text-[clamp(1.35rem,3vw,2.25rem)] font-semibold tracking-[-0.03em]">
        <li>L1</li>
        <li>L2</li>
        <li>
          Pre-BGV
          <span className="mt-2 block text-[13px] font-normal text-[#a1a1a6]">Coming soon</span>
        </li>
      </ol>
    );
  }
  return (
    <div className="mx-auto w-[280px] rounded-[22px] border border-black/10 bg-white px-7 py-9 text-left text-[#1d1d1f]">
      <p className="text-[12px] text-[#6e6e73]">Offer letter</p>
      <p className="mt-4 text-[24px] font-semibold tracking-[-0.03em]">Waiting</p>
      <p className="mt-2 text-[15px] leading-snug text-[#424245]">A person releases it. Nothing is emailed.</p>
    </div>
  );
}
