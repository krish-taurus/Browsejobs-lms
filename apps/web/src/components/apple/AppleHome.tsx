import { Disclaimer } from "@/components/brand/Disclaimer";
import { InterviewStartForm } from "@/components/auth/InterviewStartForm";
import { recruiterFaqs } from "@/content/home";
import { MacFloor } from "./MacFloor";
import { More } from "./More";
import { PhoneInterview } from "./PhoneInterview";

export function AppleHome() {
  return (
    <>
      <section id="top" className="bg-white px-5 pb-16 pt-8 text-center md:pb-20 md:pt-10">
        <p className="text-[17px] font-medium text-[#0a7040]">Free</p>
        <h1 className="apple-display mx-auto mt-3 max-w-[12ch] text-[clamp(3rem,7vw,6rem)]">
          Take a free{" "}
          <br />
          AI interview.
        </h1>
        <p className="apple-sub mt-4 text-[#1d1d1f]">
          It&apos;s free. Fifteen questions from your CV. A score of 75% or more puts you in front of HR.
          <sup>1</sup>
        </p>
        <div className="apple-note mt-2">
          <Disclaimer />
        </div>
        <InterviewStartForm tone="apple" />
        <div className="mt-1">
          <More href="/employers">Hire with BrowseJobs</More>
        </div>
        <div className="mt-8 md:mt-10">
          <PhoneInterview />
        </div>
      </section>

      <section id="for-employers" className="apple-dark bg-black text-center text-white">
        <div id="ai-recruiter" className="apple-tile">
          <h2 className="apple-display text-[clamp(3rem,6.2vw,5.5rem)]">Your AI Recruiter.</h2>
          <p className="apple-sub mt-4 text-[#a1a1a6]">Tell it the role. Watch every stage on the floor.</p>
          <div className="mt-3 flex flex-wrap items-center justify-center gap-x-7">
            <More href="/employers/mission-control-demo" about="of the hiring floor">
              Watch the demo
            </More>
            <More href="/employers" about="about hiring">
              Learn more
            </More>
            <More href="/employers/enquire" about="to hire">
              Get started
            </More>
          </div>
          <div className="mt-10">
            <MacFloor tone="dark" />
          </div>
        </div>
      </section>

      <section className="apple-tile bg-[#f5f5f7] text-center">
        <h2 className="apple-display text-[clamp(3.25rem,8vw,6rem)] leading-[0.98]">
          <span className="block">90 days</span>
          <span className="block">
            → 3 days.
            <sup className="align-super text-[0.35em] font-medium">1</sup>
          </span>
        </h2>
        <p className="apple-sub mt-6 text-[#424245]">The old process, then this one. The market still decides.</p>
        <div className="apple-note mt-4">
          <Disclaimer />
        </div>
      </section>

      <section id="how" className="apple-tile bg-white text-center">
        <h2 className="apple-display text-[clamp(2.75rem,5vw,4.5rem)]">How it works.</h2>
        <ol className="apple-steps">
          <li>Interview</li>
          <li>Score</li>
          <li>Get seen</li>
        </ol>
        <p className="apple-sub mt-8 text-[#424245]">You sit the interview. You get a score. A clear puts you in front of HR.</p>
        <div className="mt-2">
          <More href="/how-it-works" about="about the interview">
            Learn more
          </More>
        </div>
      </section>

      <section id="faq" className="bg-[#f5f5f7]">
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
        </div>
      </section>

      <section className="apple-tile bg-white text-center">
        <h2 className="apple-display text-[clamp(2.75rem,5vw,4.5rem)]">
          Start with{" "}
          <br />
          the interview.
        </h2>
        <p className="apple-sub mt-4 text-[#1d1d1f]">It&apos;s free.</p>
        <div className="mt-2">
          <More href="#interview-start" about="from the top of this page">
            Take your free AI interview
          </More>
        </div>
      </section>
    </>
  );
}
