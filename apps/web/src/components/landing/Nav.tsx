import Link from "next/link";
import { BookCta } from "@/components/landing/BookCta";
import { LoginMenu } from "@/components/landing/LoginMenu";
import { MobileMenu } from "@/components/landing/MobileMenu";
import { Wordmark } from "@/components/brand/Wordmark";

const links = [
  { href: "/courses", label: "Programs" },
  { href: "/jobs", label: "Jobs" },
  { href: "/#free-steps", label: "How it works" },
  { href: "/#verify", label: "Verify us" },
  { href: "/#fees", label: "Fees" },
  { href: "/reviews", label: "Reviews" },
  { href: "/for-employers", label: "For employers" },
];

/**
 * `tone="dark"` is the employer page's skin: the island becomes ink glass
 * instead of white glass so the nav belongs to the dark page under it rather
 * than floating over it as a leftover from the light site.
 */
export function Nav({ tone = "light" }: { tone?: "light" | "dark" } = {}) {
  const dark = tone === "dark";

  return (
    <header className="sticky top-3 z-50 px-3 md:top-4">
      {/* Soft fade above the island so content dissolves as it passes behind. */}
      <div
        aria-hidden
        className={`pointer-events-none absolute inset-x-0 -top-4 -z-10 h-24 bg-gradient-to-b to-transparent ${
          dark ? "from-deck via-deck/80" : "from-paper via-paper/80"
        }`}
      />
      {/* Floating glass island — detached from the top, blur only on this sticky element. */}
      <nav
        className={`mx-auto flex max-w-6xl items-center justify-between gap-3 rounded-full border py-2 pl-4 pr-2 shadow-soft backdrop-blur-xl ${
          dark ? "border-white/10 bg-white/[0.06]" : "border-line/70 bg-white/70"
        }`}
      >
        <Link href="/" aria-label="BrowseJobs home" className="shrink-0">
          <Wordmark tone={tone} />
        </Link>

        <div className="hidden items-center gap-4 lg:flex xl:gap-6">
          {links.map((l) => (
            <a
              key={l.href}
              href={l.href}
              className={`whitespace-nowrap text-sm font-medium transition-colors ${
                dark ? "text-white/60 hover:text-white" : "text-muted hover:text-ink"
              }`}
            >
              {l.label}
            </a>
          ))}
          <LoginMenu tone={tone} />
          <Link
            href="/register"
            className={`whitespace-nowrap text-sm font-semibold transition-colors ${
              dark ? "text-white/60 hover:text-white" : "text-trust hover:text-deep"
            }`}
          >
            Sign up
          </Link>
        </div>

        <div className="flex items-center gap-2">
          {/* Desktop keeps the primary CTA; on smaller screens the hero + sticky
              bottom bar carry it, so the header gives the menu room to breathe. */}
          <div className="hidden lg:block">
            <BookCta className="whitespace-nowrap px-4 py-2 text-sm">
              Book Free Masterclass
            </BookCta>
          </div>
          {/* Below lg: labelled menu button opens every destination + Login/Sign up */}
          <MobileMenu links={links} tone={tone} />
        </div>
      </nav>
    </header>
  );
}
