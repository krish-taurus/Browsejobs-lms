import Link from "next/link";
import { BookCta } from "@/components/landing/BookCta";
import { MobileMenu } from "@/components/landing/MobileMenu";
import { Wordmark } from "@/components/brand/Wordmark";

const links = [
  { href: "/courses", label: "Programs" },
  { href: "/jobs", label: "Jobs" },
  { href: "/#free-steps", label: "How it works" },
  { href: "/#verify", label: "Verify us" },
  { href: "/#fees", label: "Fees" },
  { href: "/reviews", label: "Reviews" },
];

export function Nav() {
  return (
    <header className="sticky top-3 z-50 px-3 md:top-4">
      {/* Soft fade above the island so content dissolves as it passes behind. */}
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 -top-4 -z-10 h-24 bg-gradient-to-b from-paper via-paper/80 to-transparent"
      />
      {/* Floating glass island — detached from the top, blur only on this sticky element. */}
      <nav className="mx-auto flex max-w-5xl items-center justify-between gap-3 rounded-full border border-line/70 bg-white/70 py-2 pl-4 pr-2 shadow-soft backdrop-blur-xl">
        <Link href="/" aria-label="BrowseJobs home" className="shrink-0">
          <Wordmark />
        </Link>

        <div className="hidden items-center gap-6 md:flex">
          {links.map((l) => (
            <a
              key={l.href}
              href={l.href}
              className="text-sm font-medium text-muted transition-colors hover:text-ink"
            >
              {l.label}
            </a>
          ))}
          <Link
            href="/student"
            className="text-sm font-medium text-muted transition-colors hover:text-ink"
          >
            Login
          </Link>
          <Link
            href="/register"
            className="text-sm font-semibold text-trust transition-colors hover:text-deep"
          >
            Sign up
          </Link>
        </div>

        <div className="flex items-center gap-2">
          {/* Desktop keeps the primary CTA; on mobile the hero + sticky bottom bar
              carry it, so the header gives the menu room to breathe. */}
          <div className="hidden md:block">
            <BookCta className="whitespace-nowrap px-4 py-2 text-sm">
              Book Free Masterclass
            </BookCta>
          </div>
          {/* Mobile only: labelled menu button opens every destination + Login/Sign up */}
          <MobileMenu links={links} />
        </div>
      </nav>
    </header>
  );
}
