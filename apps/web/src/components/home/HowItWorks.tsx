import { Disclaimer } from "@/components/brand/Disclaimer";
import { ScreenScroll } from "@/components/home/ScreenScroll";
import { CLEAR_PICKUP } from "@/content/home";

export function HowItWorks() {
  return (
    <section id="how" className="scroll-mt-28 border-t border-white/10">
      <div className="mx-auto max-w-6xl px-5 pt-16 md:pt-24">
        <p className="kicker text-trust">How it works</p>
        <h2 className="display mt-3 max-w-3xl text-4xl text-fg md:text-6xl">
          Three steps you can tell a friend.
        </h2>
        <p className="mt-4 max-w-2xl text-lg text-muted">
          You take the interview. You get a score. Then one of two things happens. A course comes later, and only if you need it.
        </p>
        <div className="mt-8 max-w-3xl rounded-[14px] border border-white/10 bg-white/5 p-5 md:p-6">
          <p className="text-base leading-relaxed text-fg md:text-lg">{CLEAR_PICKUP}</p>
          <Disclaimer className="mt-3" />
        </div>
      </div>
      <div className="mx-auto max-w-6xl px-5 pb-16 md:pb-24">
        <ScreenScroll />
      </div>
    </section>
  );
}
