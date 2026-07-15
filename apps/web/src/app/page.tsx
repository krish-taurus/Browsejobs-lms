import { Nav } from "@/components/landing/Nav";
import { Hero } from "@/components/landing/Hero";
import { ProofEngine } from "@/components/landing/ProofEngine";
import { Journey } from "@/components/landing/Journey";
import { Features } from "@/components/landing/Features";
import { PromiseCards } from "@/components/landing/PromiseCards";
import { Pricing } from "@/components/landing/Pricing";
import { Faq } from "@/components/landing/Faq";
import { Footer } from "@/components/landing/Footer";
import { JsonLd } from "@/components/landing/JsonLd";
import { ScrollReveal } from "@/components/motion/ScrollReveal";

export default function Home() {
  return (
    <>
      <JsonLd />
      <Nav />
      <main>
        <Hero />
        <ProofEngine />
        <Journey />
        <Features />
        <PromiseCards />
        <Pricing />
        <Faq />

        {/* Masterclass CTA band */}
        <section id="masterclass" className="mx-auto max-w-6xl px-5 py-16">
          <ScrollReveal>
            <div className="rounded-3xl bg-trust px-8 py-14 text-center text-white shadow-xl">
              <p className="kicker text-white/80">Free live masterclass</p>
              <h2 className="display mx-auto mt-4 max-w-2xl text-3xl md:text-4xl">
                See the teaching before you spend a rupee
              </h2>
              <p className="mx-auto mt-4 max-w-xl text-white/85">
                Reserve a seat in the next live masterclass. Get reminders 12
                hours, 2 hours, and 5 minutes before — with a one-tap join link.
              </p>
              <a
                href="#"
                className="mt-8 inline-block rounded-full bg-white px-8 py-3.5 font-semibold text-trust transition-transform hover:-translate-y-0.5"
              >
                Reserve my free seat
              </a>
            </div>
          </ScrollReveal>
        </section>
      </main>
      <Footer />
    </>
  );
}
