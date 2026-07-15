import Link from "next/link";

const links = [
  { href: "#journey", label: "How it works" },
  { href: "#features", label: "Platform" },
  { href: "#pricing", label: "Pricing" },
  { href: "#faq", label: "FAQ" },
];

export function Nav() {
  return (
    <header className="sticky top-0 z-50 border-b border-line/70 bg-paper/80 backdrop-blur-md">
      <nav className="mx-auto flex max-w-6xl items-center justify-between px-5 py-3.5">
        <Link href="/" className="flex items-center gap-2">
          <span className="grid h-8 w-8 place-items-center rounded-lg bg-ink text-white display text-lg">
            B
          </span>
          <span className="display text-lg text-ink">BrowseJobs</span>
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

        <a
          href="#masterclass"
          className="rounded-full bg-trust px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-deep"
        >
          Join free masterclass
        </a>
      </nav>
    </header>
  );
}
