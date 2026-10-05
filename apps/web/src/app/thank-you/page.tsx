import Link from "next/link";
import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "You're booked — BrowseJobs",
  description: "Your free masterclass seat is confirmed. Here's what happens next.",
  // A thank-you page has no business in search results; it only makes sense
  // arriving from the form.
  robots: { index: false, follow: false },
};

/** What actually happens after the form, in the order it happens. */
const STEPS = [
  {
    when: "Right now",
    title: "A WhatsApp lands on your phone",
    body: "It carries your seat confirmation and the link you'll watch on. Save the number so it doesn't get lost in Other.",
  },
  {
    when: "Before the session",
    title: "We remind you",
    body: "A nudge goes out shortly before it starts. Nothing to diarise, nothing to remember.",
  },
  {
    when: "On the day",
    title: "Open the link and join",
    body: "No installs, no dial-in codes. Come five minutes early and you'll be settled before the first slide.",
  },
];

export default function ThankYouPage() {
  return (
    <main className="mx-auto flex min-h-[70vh] max-w-3xl flex-col justify-center px-5 py-20">
      <div className="grid size-14 place-items-center rounded-full bg-verify-bg">
        <svg viewBox="0 0 24 24" className="size-7 text-verify" fill="none" aria-hidden="true">
          <path d="M5 12.5l4.5 4.5L19 7.5" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </div>

      <p className="kicker mt-6 text-verify">Seat confirmed</p>
      <h1 className="display mt-2 text-4xl text-ink sm:text-5xl">You&apos;re in.</h1>
      <p className="mt-4 max-w-xl text-base text-muted">
        Your free masterclass seat is booked. It costs nothing and needs no card —
        it&apos;s the first of three free steps.
      </p>

      <ol className="mt-10 space-y-px overflow-hidden rounded-[14px] border border-line bg-surface">
        {STEPS.map((s) => (
          <li key={s.title} className="flex flex-col gap-1 border-b border-line px-6 py-5 last:border-b-0 sm:flex-row sm:gap-6">
            <span className="mono w-40 shrink-0 text-[11px] uppercase tracking-widest text-muted">{s.when}</span>
            <span>
              <span className="block font-semibold text-ink">{s.title}</span>
              <span className="mt-1 block text-sm text-muted">{s.body}</span>
            </span>
          </li>
        ))}
      </ol>

      <div className="mt-10 flex flex-wrap items-center gap-4">
        <Link
          href="/"
          className="rounded-full bg-trust px-6 py-3 text-sm font-semibold text-white transition hover:bg-deep"
        >
          Back to BrowseJobs
        </Link>
        <Link href="/courses" className="text-sm font-semibold text-trust hover:underline">
          Look at the programs while you wait →
        </Link>
      </div>

      <p className="mt-10 text-sm text-muted">
        Didn&apos;t get the WhatsApp within a few minutes? Check the number you
        entered, then message us — we&apos;ll sort it out.
      </p>
    </main>
  );
}
