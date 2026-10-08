import { InterviewStartForm } from "@/components/auth/InterviewStartForm";
import { Disclaimer } from "@/components/brand/Disclaimer";
import { More } from "./More";
import { PhoneInterview } from "./PhoneInterview";
import { MacFloor } from "./MacFloor";
import { EmployerImpact } from "./EmployerImpact";
import { AfterYouClear, BelowSeventyFive, CounsellingBlock, HowTheInterviewWorks, StudentFaq, WhatSeventyFive } from "./StudentStory";

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
          It&apos;s free. About 15 questions from your CV. A score of 75% or more puts you in front of HR.
        </p>
        <div className="apple-note mt-2">
          <Disclaimer />
        </div>
        <InterviewStartForm tone="apple" />
        <div className="mt-8 md:mt-10">
          <PhoneInterview />
        </div>
      </section>

      <WhatSeventyFive />
      <BelowSeventyFive />
      <CounsellingBlock />
      <HowTheInterviewWorks />
      <AfterYouClear />

      <section id="for-employers" className="apple-dark bg-black text-center text-white">
        <div id="ai-recruiter" className="apple-tile">
          <h2 className="apple-display text-[clamp(3rem,6.2vw,5.5rem)]">Your AI Recruiter.</h2>
          <p className="apple-sub mt-4 text-[#a1a1a6]">Tell it the role. Watch every stage on the floor. The floor below is sample data.</p>
          <div className="mt-3 flex flex-wrap items-center justify-center gap-x-7">
            <More href="/employers/mission-control-demo" about="of the hiring floor">
              Watch the demo
            </More>
            <More href="/employers" about="about hiring">
              Learn more
            </More>
          </div>
          <p className="mt-8 text-[12px] text-[#a1a1a6]">Sample data</p>
          <div className="mt-3">
            <MacFloor tone="dark" />
          </div>
        </div>
      </section>

      <EmployerImpact />

      <StudentFaq />

      <section className="apple-tile bg-white text-center">
        <h2 className="apple-display text-[clamp(2.75rem,5vw,4.5rem)]">
          Start with{" "}
          <br />
          the interview.
        </h2>
        <p className="apple-sub mt-4 text-[#1d1d1f]">It&apos;s free.</p>
        <div className="mt-2">
          <More href="/#interview-start" about="from the top of this page">
            Take your free AI interview
          </More>
        </div>
        <div className="apple-note mt-4">
          <Disclaimer />
        </div>
      </section>
    </>
  );
}
