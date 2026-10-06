import Link from "next/link";
import { BookCta } from "@/components/landing/BookCta";
import { LoginMenu } from "@/components/landing/LoginMenu";
import { MobileMenu } from "@/components/landing/MobileMenu";
import { Wordmark } from "@/components/brand/Wordmark";
import type { LeadVariant } from "@/components/landing/leadModalBus";

const defaultLinks = [
  { href: "/courses", label: "Programs" },
  { href: "/jobs", label: "Jobs" },
  { href: "/#free-steps", label: "How it works" },
  { href: "/#verify", label: "Verify us" },
  { href: "/#fees", label: "Fees" },
  { href: "/reviews", label: "Reviews" },
];

export function Nav({
  links = defaultLinks,
  ctaLabel = "Book Free Masterclass",
  ctaVariant = "masterclass",
  ctaHref,
  tone = "light",
}: {
  links?: readonly { href: string; label: string }[];
  ctaLabel?: string;
  ctaVariant?: LeadVariant;
  ctaHref?: string;
  tone?: "light" | "night";
} = {}) {
  const night = tone === "night";
  return (
    <header className="sticky top-3 z-50 px-3 md:top-4">
      {/* Soft fade above the island so content dissolves as it passes behind. */}
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 -top-4 -z-10 h-24 bg-gradient-to-b from-paper via-paper/80 to-transparent"
      />
      {/* Floating glass island — detached from the top, blur only on this sticky element. */}
      <nav
        className={`mx-auto flex max-w-5xl items-center justify-between gap-3 rounded-full border py-2 pl-4 pr-2 shadow-soft backdrop-blur-xl ${
          night ? "border-white/10 bg-ink/70" : "border-line/70 bg-white/70"
        }`}
      >
        <Link href="/" aria-label="BrowseJobs home" className="shrink-0">
          <Wordmark tone={night ? "dark" : "light"} />
        </Link>

        <div className="hidden items-center gap-5 lg:flex">
          {links.map((l) => (
            <a
              key={l.href}
              href={l.href}
              className={`text-sm font-medium transition-colors ${
                night ? "text-white/70 hover:text-white" : "text-muted hover:text-ink"
              }`}
            >
              {l.label}
            </a>
          ))}
          <LoginMenu tone={tone} />
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
          <div className="hidden lg:block">
            {ctaHref ? (
              <a
                href={ctaHref}
                className="inline-flex items-center justify-center whitespace-nowrap rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white shadow-[0_6px_24px_rgba(27,109,240,0.35)] transition-colors hover:bg-deep"
              >
                {ctaLabel}
              </a>
            ) : (
              <BookCta variant={ctaVariant} size="sm" className="whitespace-nowrap">
                {ctaLabel}
              </BookCta>
            )}
          </div>
          {/* Mobile only: labelled menu button opens every destination + Login/Sign up */}
          <MobileMenu links={links} tone={tone} />
        </div>
      </nav>
    </header>
  );
}
