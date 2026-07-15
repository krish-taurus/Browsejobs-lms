import { Nav } from "@/components/landing/Nav";
import { Hero } from "@/components/landing/Hero";
import { ProofEngine } from "@/components/landing/ProofEngine";
import { Courses } from "@/components/landing/Courses";
import { FreeLadder } from "@/components/landing/FreeLadder";
import { VerifyUs } from "@/components/landing/VerifyUs";
import { Fees } from "@/components/landing/Fees";
import { Faq } from "@/components/landing/Faq";
import { Footer } from "@/components/landing/Footer";
import { JsonLd } from "@/components/landing/JsonLd";
import { LeadModal } from "@/components/landing/LeadModal";
import { StickyCta } from "@/components/landing/StickyCta";
import { ScrollReveal } from "@/components/motion/ScrollReveal";
import { BookCta } from "@/components/landing/BookCta";

/**
 * Home, in the spec §6.1 order: hook → Proof Engine + stats → courses →
 * free ladder → verify-us → fees → FAQ → CTA. (Testimonials slot pending the
 * six real testimonials — requirements §14.7.)
 */
export default function Home() {
  return (
    <>
      <JsonLd />
      <Nav />
      <main>
        <Hero />
        <ProofEngine />
        <Courses />
        <FreeLadder />
        <VerifyUs />
        <Fees />
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
