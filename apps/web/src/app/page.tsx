import { Nav } from "@/components/landing/Nav";
import { Hero } from "@/components/landing/Hero";
import { ProofEngine } from "@/components/landing/ProofEngine";
import { Journey } from "@/components/landing/Journey";
import { Courses } from "@/components/landing/Courses";
import { AiShowcase } from "@/components/landing/AiShowcase";
import { Fees } from "@/components/landing/Fees";
import { VerifyUs } from "@/components/landing/VerifyUs";
import { PromiseCards } from "@/components/landing/PromiseCards";
import { SuccessWall } from "@/components/landing/SuccessWall";
import { Faq } from "@/components/landing/Faq";
import { Footer } from "@/components/landing/Footer";
import { JsonLd } from "@/components/landing/JsonLd";
import { LeadModal } from "@/components/landing/LeadModal";
import { StickyCta } from "@/components/landing/StickyCta";
import { SmoothScroll } from "@/components/motion/SmoothScroll";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { BookCta } from "@/components/landing/BookCta";

/**
 * Home — Apple-grade redesign (S1–S9): hero engine → proof engine → journey →
 * programs → AI showcase → pricing → verify → promise cards → success wall → FAQ →
 * CTA. Success wall renders only real seeded reviews.
 */
export default function Home() {
  return (
    <>
      <SmoothScroll />
      <JsonLd />
      <Nav />
      <main>
        <Hero />
        <ProofEngine />
        <Journey />
        <Courses />
        <AiShowcase />
        <Fees />
        <VerifyUs />
        <PromiseCards />
        <SuccessWall />
        <Faq />

        {/* Closing CTA band */}
        <section id="masterclass" className="mx-auto max-w-6xl px-5 py-16">
          <ScrollReveal>
            <div className="rounded-[22px] bg-ink px-8 py-14 text-center text-white shadow-soft">
              <p className="kicker text-sky/70">Step 01 of 3 free steps</p>
              <h2 className="display mx-auto mt-4 max-w-2xl text-3xl md:text-4xl">
                See the method live before you spend a rupee
              </h2>
              <p className="mx-auto mt-4 max-w-xl text-sky/70">
                A live masterclass on how the syllabus is reverse-engineered
                from real interviews. Reminders arrive 12h, 2h and 5min before —
                with a one-tap join link.
              </p>
              <div className="mt-8">
                <BookCta className="px-8 py-3.5" />
              </div>
            </div>
          </ScrollReveal>
        </section>
      </main>
      <Footer />
      <LeadModal />
      <StickyCta />
    </>
  );
}
