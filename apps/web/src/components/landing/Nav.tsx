import Link from "next/link";
import { BookCta } from "@/components/landing/BookCta";
import { Wordmark } from "@/components/brand/Wordmark";

const links = [
  { href: "/courses", label: "Programs" },
  { href: "/#free-steps", label: "How it works" },
  { href: "/#verify", label: "Verify us" },
  { href: "/#fees", label: "Fees" },
  { href: "/reviews", label: "Reviews" },
];

export function Nav() {
  return (
    <header className="sticky top-0 z-50 border-b border-line/70 bg-paper/80 backdrop-blur-md">
      <nav className="mx-auto flex max-w-6xl items-center justify-between px-5 py-3.5">
        <Link href="/" aria-label="BrowseJobs home">
          <Wordmark />
        </Link>

        <div className="hidden items-center gap-7 md:flex">
          {links.map((l) => (
            <a
              key={l.href}
              href={l.href}
              className="text-sm font-medium text-muted transition-colors hover:text-ink"
            >
              {l.label}
            </a>
          ))}
        </div>

        <BookCta className="px-4 py-2 text-sm">Book Free Masterclass</BookCta>
      </nav>
    </header>
  );
}
