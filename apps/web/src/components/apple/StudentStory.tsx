import { Disclaimer } from "@/components/brand/Disclaimer";
import { CounsellingEnquiry } from "@/components/apple/EnquiryForm";
import { BELOW_SEVENTY_FIVE, CLAIM_INTERVIEW_CALL, CLAIM_RECRUITERS, COUNSELLING_COPY, SEVENTY_FIVE, recruiterFaqs } from "@/content/home";

export function WhatSeventyFive() {
  return (
    <section id="what-75" className="apple-tile bg-white text-center">
      <h2 className="apple-display text-[clamp(2.75rem,5vw,4.5rem)]">What 75% means.</h2>
      <p className="apple-sub mt-4 text-[#424245]">Plain version. The score is a read of this interview. It is not a job offer.</p>
      <ol className="mx-auto mt-12 grid max-w-[880px] gap-8 text-left sm:grid-cols-2">
        {SEVENTY_FIVE.map((item, index) => (
          <li key={item.title}>
            <p className="text-[12px] font-medium text-[#6e6e73]">{String(index + 1).padStart(2, "0")}</p>
            <h3 className="mt-2 text-[22px] font-semibold tracking-[-0.02em]">{item.title}</h3>
            <p className="mt-2 text-[17px] leading-snug text-[#424245]">{item.body}</p>
          </li>
        ))}
      </ol>
      <div className="apple-note mt-8">
        <Disclaimer />
      </div>
    </section>
  );
}

export function BelowSeventyFive() {
  return (
    <section id="below-75" className="apple-tile bg-[#f5f5f7] text-center">
      <h2 className="apple-display mx-auto max-w-[16ch] text-[clamp(2.5rem,5vw,4.25rem)]">Below 75%? Here&apos;s your path.</h2>
      <p className="apple-sub mt-4 text-[#424245]">A score under 75% is a starting point. This is the path back to a clear.</p>
      <ol className="apple-road">
        {BELOW_SEVENTY_FIVE.map((step, index) => (
          <li key={step.title}>
            <span className="apple-road-n">{String(index + 1).padStart(2, "0")}</span>
            <div>
              <h3 className="text-[21px] font-semibold tracking-[-0.02em]">{step.title}</h3>
              <p className="mt-1 text-[17px] leading-snug text-[#424245]">{step.body}</p>
            </div>
          </li>
        ))}
      </ol>
      <div className="apple-note mt-4">
        <Disclaimer />
      </div>
    </section>
  );
}

export function CounsellingBlock() {
  return (
    <section id="counselling" className="apple-tile scroll-mt-20 bg-white text-center">
      <p className="text-[17px] font-medium text-[#0a7040]">{COUNSELLING_COPY.kicker}</p>
      <h2 className="apple-display mt-3 text-[clamp(2.5rem,5vw,4.25rem)]">{COUNSELLING_COPY.title}</h2>
      <p className="apple-sub mt-4 text-[#424245]">{COUNSELLING_COPY.body}</p>
      <div className="mt-10">
        <CounsellingEnquiry />
      </div>
    </section>
  );
}

export function AfterYouClear() {
  return (
    <section id="after-clear" className="apple-tile bg-[#f5f5f7] text-center">
      <h2 className="apple-display text-[clamp(2.5rem,5vw,4.25rem)]">After you clear.</h2>
      <ol className="apple-steps">
        <li>Pre-qualified</li>
        <li>CV sent</li>
        <li>Interview call</li>
      </ol>
      <div className="mx-auto mt-10 grid max-w-[880px] gap-8 text-left sm:grid-cols-3">
        <div>
          <h3 className="text-[21px] font-semibold tracking-[-0.02em]">You are pre-qualified.</h3>
          <p className="mt-2 text-[17px] leading-snug text-[#424245]">75% or more is the clear mark. Employers see people who already cleared.</p>
        </div>
        <div>
          <h3 className="text-[21px] font-semibold tracking-[-0.02em]">Your CV is sent.</h3>
          <p className="mt-2 text-[17px] leading-snug text-[#424245]">{CLAIM_RECRUITERS}</p>
        </div>
        <div>
          <h3 className="text-[21px] font-semibold tracking-[-0.02em]">The call is more likely.</h3>
          <p className="mt-2 text-[17px] leading-snug text-[#424245]">{CLAIM_INTERVIEW_CALL} The market still decides.</p>
        </div>
      </div>
      <div className="apple-note mt-8">
        <Disclaimer />
      </div>
    </section>
  );
}

export function HowTheInterviewWorks() {
  return (
    <section id="how" className="apple-tile bg-white text-center">
      <h2 className="apple-display text-[clamp(2.75rem,5vw,4.5rem)]">How it works.</h2>
      <ol className="apple-steps">
        <li>Interview</li>
        <li>Score</li>
        <li>Get seen</li>
      </ol>
      <div className="mx-auto mt-12 grid max-w-[880px] gap-10 text-left sm:grid-cols-3">
        <div>
          <h3 className="text-[21px] font-semibold tracking-[-0.02em]">Interview</h3>
          <p className="mt-2 text-[17px] leading-snug text-[#424245]">About 15 questions from your CV. You answer them. It is free.</p>
        </div>
        <div>
          <h3 className="text-[21px] font-semibold tracking-[-0.02em]">Score</h3>
          <p className="mt-2 text-[17px] leading-snug text-[#424245]">The AI scores the round out of 100. 75% or more is a clear.</p>
        </div>
        <div>
          <h3 className="text-[21px] font-semibold tracking-[-0.02em]">Get seen</h3>
          <p className="mt-2 text-[17px] leading-snug text-[#424245]">A clear sends your CV to 3,000 HR recruiters. Under 75%, the path is counselling, a plan, then a retake.</p>
        </div>
      </div>
      <div className="apple-note mt-8">
        <Disclaimer />
      </div>
    </section>
  );
}

export function StudentFaq() {
  return (
    <section id="faq" className="bg-white">
      <div className="apple-tile mx-auto max-w-[720px]">
        <h2 className="apple-display text-center text-[clamp(2.25rem,4vw,3rem)]">Questions.</h2>
        <div className="mt-8 divide-y divide-black/10 border-y border-black/10 text-left">
          {recruiterFaqs.map((item) => (
            <details key={item.q} className="group py-1">
              <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 py-3 text-[17px] font-medium tracking-[-0.015em] [&::-webkit-details-marker]:hidden">
                {item.q}
                <span aria-hidden className="text-[22px] font-normal text-[#6e6e73] transition-transform duration-150 group-open:rotate-45">
                  +
                </span>
              </summary>
              <p className="max-w-[60ch] pb-4 text-[17px] leading-relaxed text-[#424245]">{item.a}</p>
            </details>
          ))}
        </div>
        <div className="apple-note mt-6 text-left">
          <Disclaimer />
        </div>
      </div>
    </section>
  );
}
