import { ScreenScroll } from "@/components/home/ScreenScroll";

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
      </div>
      <div className="mx-auto max-w-6xl px-5 pb-8">
        <ScreenScroll />
      </div>
    </section>
  );
}
